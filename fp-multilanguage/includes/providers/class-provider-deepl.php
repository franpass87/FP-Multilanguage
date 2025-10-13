<?php
/**
 * DeepL translation provider.
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
 * Translate using DeepL API v2.
 *
 * @since 0.2.0
 */
class FPML_Provider_DeepL extends FPML_Base_Provider {
        const API_BASE      = 'https://api.deepl.com/v2/translate';
        const API_BASE_FREE = 'https://api-free.deepl.com/v2/translate';

        /**
         * {@inheritdoc}
         */
        public function get_slug() {
                return 'deepl';
        }

        /**
         * {@inheritdoc}
         */
        public function is_configured() {
                return ! empty( $this->get_option( 'deepl_api_key' ) );
        }

        /**
         * {@inheritdoc}
         */
        public function translate( $text, $source = 'it', $target = 'en', $domain = 'general' ) {
                if ( '' === trim( (string) $text ) ) {
                        return '';
                }

                if ( ! $this->is_configured() ) {
                        return new WP_Error( 'fpml_deepl_missing_key', __( 'Configura una chiave API DeepL valida per procedere con la traduzione.', 'fp-multilanguage' ) );
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
                $source    = strtoupper( $source );
                $target    = strtoupper( $target );

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
         * Perform DeepL request.
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
                $endpoint = $this->get_option( 'deepl_use_free', false ) ? self::API_BASE_FREE : self::API_BASE;

                $body = array(
                        'text'                 => $text,
                        'source_lang'          => strtoupper( $source ),
                        'target_lang'          => ('EN' === strtoupper( $target ) ? 'EN-US' : strtoupper( $target )),
                        'preserve_formatting'  => 1,
                        'tag_handling'         => 'html',
                        'outline_detection'    => 0,
                        'formality'            => 'default',
                        'split_sentences'      => 'nonewlines',
                        'context'              => $domain,
                );

                $args = array(
                        'headers' => array(
                                'Authorization' => 'DeepL-Auth-Key ' . $this->get_option( 'deepl_api_key' ),
                        ),
                        'timeout' => 45,
                        'body'    => $body,
                );

                $max_attempts = 3;

                for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
                        $response = wp_remote_post( $endpoint, $args );

                        if ( is_wp_error( $response ) ) {
                                if ( $attempt === $max_attempts ) {
                                        return new WP_Error( 'fpml_deepl_http_error', sprintf( __( 'Errore di connessione a DeepL: %s', 'fp-multilanguage' ), $response->get_error_message() ) );
                                }

                                $this->backoff( $attempt );
                                continue;
                        }

                        $code = (int) wp_remote_retrieve_response_code( $response );

                        // Errori temporanei - retry ha senso
                        if ( in_array( $code, array( 429, 500, 502, 503, 504 ), true ) ) {
                                if ( $attempt === $max_attempts ) {
                                        return new WP_Error( 'fpml_deepl_rate_limit', __( 'DeepL ha restituito un errore di rate limit o temporaneo.', 'fp-multilanguage' ) );
                                }

                                if ( class_exists( 'FPML_Logger' ) ) {
                                        FPML_Logger::instance()->log(
                                                'warning',
                                                sprintf( 'DeepL tentativo %d/%d fallito con codice %d', $attempt, $max_attempts, $code ),
                                                array(
                                                        'provider' => 'deepl',
                                                        'attempt'  => $attempt,
                                                        'http_code' => $code,
                                                )
                                        );
                                }

                                $this->backoff( $attempt );
                                continue;
                        }

                        // Errori client (4xx eccetto 429) - NON ritentare
                        if ( $code >= 400 && $code < 500 ) {
                                $body_content = wp_remote_retrieve_body( $response );
                                $error_code = 'fpml_deepl_client_error';

                                if ( 401 === $code || 403 === $code ) {
                                        $error_code = 'fpml_deepl_auth_error';
                                } elseif ( 400 === $code ) {
                                        $error_code = 'fpml_deepl_invalid_request';
                                } elseif ( 456 === $code ) {
                                        $error_code = 'fpml_deepl_quota_exceeded';
                                }

                                return new WP_Error( $error_code, sprintf( __( 'Errore client DeepL (%1$d): %2$s', 'fp-multilanguage' ), $code, wp_kses_post( $body_content ) ) );
                        }

                        // Altri errori
                        if ( $code < 200 || $code >= 300 ) {
                                $body_content = wp_remote_retrieve_body( $response );
                                return new WP_Error( 'fpml_deepl_error', sprintf( __( 'Risposta non valida da DeepL (%1$d): %2$s', 'fp-multilanguage' ), $code, wp_kses_post( $body_content ) ) );
                        }

			$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( null === $data ) {
			return new WP_Error( 'fpml_deepl_invalid_json', __( 'Risposta JSON non valida da DeepL.', 'fp-multilanguage' ) );
		}

		if ( ! isset( $data['translations'][0]['text'] ) || empty( $data['translations'][0]['text'] ) ) {
			return new WP_Error( 'fpml_deepl_empty', __( 'DeepL non ha restituito alcun contenuto traducibile.', 'fp-multilanguage' ) );
		}

		return (string) $data['translations'][0]['text'];
                }

                return new WP_Error( 'fpml_deepl_unexpected', __( 'Errore imprevisto durante la traduzione con DeepL.', 'fp-multilanguage' ) );
        }

        /**
         * {@inheritdoc}
         */
        protected function get_rate_option_key() {
                return 'rate_deepl';
        }
}
