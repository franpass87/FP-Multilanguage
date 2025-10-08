<?php
/**
 * Google Cloud Translation provider.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

require_once __DIR__ . '/interface-translator.php';

/**
 * Translate using Google Cloud Translation API (v3 with v2 fallback).
 *
 * @since 0.2.0
 */
class FPML_Provider_Google extends FPML_Base_Provider {
        const API_V3 = 'https://translation.googleapis.com/v3/projects/%s/locations/global:translateText';
        const API_V2 = 'https://translation.googleapis.com/language/translate/v2';

        /**
         * {@inheritdoc}
         */
        public function get_slug() {
                return 'google';
        }

        /**
         * {@inheritdoc}
         */
        public function is_configured() {
                return ! empty( $this->get_option( 'google_api_key' ) );
        }

        /**
         * {@inheritdoc}
         */
        public function translate( $text, $source = 'it', $target = 'en', $domain = 'general' ) {
                if ( '' === trim( (string) $text ) ) {
                        return '';
                }

                if ( ! $this->is_configured() ) {
                        return new WP_Error( 'fpml_google_missing_key', __( 'Configura un API key di Google Cloud Translation per procedere con la traduzione.', 'fp-multilanguage' ) );
                }

                // Check cache first
                $cache = FPML_Container::get( 'translation_cache' );
                if ( $cache ) {
                        $cached = $cache->get( $text, $this->get_slug(), $source, $target );
                        if ( false !== $cached ) {
                                return $cached;
                        }
                }

                $max_chars = (int) $this->get_option( 'max_chars', 4500 );
                $chunks    = $this->chunk_text( $text, $max_chars );
                $source    = strtolower( $source );
                $target    = strtolower( $target );

                $translated = '';

                foreach ( $chunks as $chunk ) {
                        $chunk_to_send = $this->apply_glossary_pre( $chunk, $source, $target, $domain );
                        $result        = $this->request_translation( $chunk_to_send, $source, $target, $domain );

                        if ( is_wp_error( $result ) ) {
                                return $result;
                        }

                        $translated .= $this->apply_glossary_post( $result, $source, $target, $domain );
                }

                // Store in cache
                if ( $cache && '' !== $translated ) {
                        $cache->set( $text, $this->get_slug(), $translated, $source, $target );
                }

                return $translated;
        }

        /**
         * Perform Google Translation API request.
         *
         * @since 0.2.0
         *
         * @param string $text   Chunk text.
         * @param string $source Source language.
         * @param string $target Target language.
         * @param string $domain Context domain.
         *
         * @return string|WP_Error
         */
        protected function request_translation( $text, $source, $target, $domain ) {
                $api_key    = $this->get_option( 'google_api_key' );
                $project_id = $this->get_option( 'google_project_id' );

                $v3_response = null;
                if ( ! empty( $project_id ) ) {
                        $v3_response = $this->request_v3( $text, $source, $target, $domain, $project_id, $api_key );
                        if ( is_string( $v3_response ) || is_wp_error( $v3_response ) ) {
                                return $v3_response;
                        }
                        // On null fallback to v2.
                }

                return $this->request_v2( $text, $source, $target, $api_key );
        }

        /**
         * Call Google Translation API v3.
         *
         * @since 0.2.0
         *
         * @param string $text       Chunk text.
         * @param string $source     Source language.
         * @param string $target     Target language.
         * @param string $domain     Context domain.
         * @param string $project_id Project identifier.
         * @param string $api_key    API key.
         *
         * @return string|WP_Error|null Null when fallback is required.
         */
        protected function request_v3( $text, $source, $target, $domain, $project_id, $api_key ) {
                $url  = sprintf( self::API_V3, rawurlencode( $project_id ) );
                $url .= '?key=' . rawurlencode( $api_key );

                $body = array(
                        'contents'            => array( $text ),
                        'sourceLanguageCode'  => $source,
                        'targetLanguageCode'  => 'en' === $target ? 'en-US' : $target,
                        'mimeType'            => 'text/html',
                        'labels'              => array( 'fpml-domain' => sanitize_key( $domain ) ),
                );

                $args = array(
                        'headers' => array(
                                'Content-Type' => 'application/json',
                        ),
                        'timeout' => 45,
                        'body'    => wp_json_encode( $body ),
                );

                $max_attempts = 3;

                for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
                        $response = wp_remote_post( $url, $args );

                        if ( is_wp_error( $response ) ) {
                                if ( $attempt === $max_attempts ) {
                                        return new WP_Error( 'fpml_google_http_error', sprintf( __( 'Errore di connessione a Google Translate: %s', 'fp-multilanguage' ), $response->get_error_message() ) );
                                }

                                $this->backoff( $attempt );
                                continue;
                        }

                        $code = (int) wp_remote_retrieve_response_code( $response );

                        // Errori temporanei - retry ha senso
                        if ( in_array( $code, array( 429, 500, 502, 503, 504 ), true ) ) {
                                if ( $attempt === $max_attempts ) {
                                        return new WP_Error( 'fpml_google_rate_limit', __( 'Google Translate ha restituito un errore di rate limit o temporaneo.', 'fp-multilanguage' ) );
                                }

                                if ( class_exists( 'FPML_Logger' ) ) {
                                        FPML_Logger::instance()->log(
                                                'warning',
                                                sprintf( 'Google v3 tentativo %d/%d fallito con codice %d', $attempt, $max_attempts, $code ),
                                                array(
                                                        'provider' => 'google',
                                                        'api_version' => 'v3',
                                                        'attempt'  => $attempt,
                                                        'http_code' => $code,
                                                )
                                        );
                                }

                                $this->backoff( $attempt );
                                continue;
                        }

                        $body_content = wp_remote_retrieve_body( $response );

                        // Errori client (4xx) - check for fallback to v2
                        if ( $code >= 400 && $code < 500 ) {
                                // If v3 is not enabled fall back to v2
                                if ( in_array( $code, array( 400, 401, 403, 404 ), true ) ) {
                                        return null;
                                }
                                
                                $error_code = 'fpml_google_client_error';
                                if ( 401 === $code || 403 === $code ) {
                                        $error_code = 'fpml_google_auth_error';
                                }

                                return new WP_Error( $error_code, sprintf( __( 'Errore client Google Translate v3 (%1$d): %2$s', 'fp-multilanguage' ), $code, wp_kses_post( $body_content ) ) );
                        }

                        // Altri errori
                        if ( $code < 200 || $code >= 300 ) {
                                return new WP_Error( 'fpml_google_error', sprintf( __( 'Risposta non valida da Google Translate v3 (%1$d): %2$s', 'fp-multilanguage' ), $code, wp_kses_post( $body_content ) ) );
                        }

                        $data = json_decode( $body_content, true );
                        if ( empty( $data['translations'][0]['translatedText'] ) ) {
                                return new WP_Error( 'fpml_google_empty', __( 'Google Translate v3 non ha restituito alcun contenuto traducibile.', 'fp-multilanguage' ) );
                        }

                        return (string) $data['translations'][0]['translatedText'];
                }

                return new WP_Error( 'fpml_google_unexpected', __( 'Errore imprevisto durante la traduzione con Google Translate v3.', 'fp-multilanguage' ) );
        }

        /**
         * Call Google Translation API v2.
         *
         * @since 0.2.0
         *
         * @param string $text    Chunk text.
         * @param string $source  Source language.
         * @param string $target  Target language.
         * @param string $api_key API key.
         *
         * @return string|WP_Error
         */
        protected function request_v2( $text, $source, $target, $api_key ) {
                $args = array(
                        'body'    => array(
                                'q'      => $text,
                                'source' => $source,
                                'target' => 'en' === $target ? 'en' : $target,
                                'format' => 'html',
                                'key'    => $api_key,
                        ),
                        'timeout' => 45,
                );

                $max_attempts = 3;

                for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
                        $response = wp_remote_post( self::API_V2, $args );

                        if ( is_wp_error( $response ) ) {
                                if ( $attempt === $max_attempts ) {
                                        return new WP_Error( 'fpml_google_http_error', sprintf( __( 'Errore di connessione a Google Translate: %s', 'fp-multilanguage' ), $response->get_error_message() ) );
                                }

                                $this->backoff( $attempt );
                                continue;
                        }

                        $code = (int) wp_remote_retrieve_response_code( $response );

                        // Errori temporanei - retry ha senso
                        if ( in_array( $code, array( 429, 500, 502, 503, 504 ), true ) ) {
                                if ( $attempt === $max_attempts ) {
                                        return new WP_Error( 'fpml_google_rate_limit', __( 'Google Translate ha restituito un errore di rate limit o temporaneo.', 'fp-multilanguage' ) );
                                }

                                if ( class_exists( 'FPML_Logger' ) ) {
                                        FPML_Logger::instance()->log(
                                                'warning',
                                                sprintf( 'Google v2 tentativo %d/%d fallito con codice %d', $attempt, $max_attempts, $code ),
                                                array(
                                                        'provider' => 'google',
                                                        'api_version' => 'v2',
                                                        'attempt'  => $attempt,
                                                        'http_code' => $code,
                                                )
                                        );
                                }

                                $this->backoff( $attempt );
                                continue;
                        }

                        $body_content = wp_remote_retrieve_body( $response );

                        // Errori client (4xx eccetto 429) - NON ritentare
                        if ( $code >= 400 && $code < 500 ) {
                                $error_code = 'fpml_google_client_error';

                                if ( 401 === $code || 403 === $code ) {
                                        $error_code = 'fpml_google_auth_error';
                                } elseif ( 400 === $code ) {
                                        $error_code = 'fpml_google_invalid_request';
                                }

                                return new WP_Error( $error_code, sprintf( __( 'Errore client Google Translate v2 (%1$d): %2$s', 'fp-multilanguage' ), $code, wp_kses_post( $body_content ) ) );
                        }

                        // Altri errori
                        if ( $code < 200 || $code >= 300 ) {
                                return new WP_Error( 'fpml_google_error', sprintf( __( 'Risposta non valida da Google Translate v2 (%1$d): %2$s', 'fp-multilanguage' ), $code, wp_kses_post( $body_content ) ) );
                        }

                        $data = json_decode( $body_content, true );
                        if ( empty( $data['data']['translations'][0]['translatedText'] ) ) {
                                return new WP_Error( 'fpml_google_empty', __( 'Google Translate v2 non ha restituito alcun contenuto traducibile.', 'fp-multilanguage' ) );
                        }

                        return (string) $data['data']['translations'][0]['translatedText'];
                }

                return new WP_Error( 'fpml_google_unexpected', __( 'Errore imprevisto durante la traduzione con Google Translate v2.', 'fp-multilanguage' ) );
        }

        /**
         * {@inheritdoc}
         */
        protected function get_rate_option_key() {
                return 'rate_google';
        }
}
