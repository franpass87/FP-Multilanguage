<?php
/**
 * LibreTranslate provider.
 *
 * @package FP_Multilanguage
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

require_once __DIR__ . '/interface-translator.php';

/**
 * Translate using LibreTranslate API.
 *
 * @since 0.2.0
 */
class FPML_Provider_LibreTranslate extends FPML_Base_Provider {
        /**
         * {@inheritdoc}
         */
        public function get_slug() {
                return 'libretranslate';
        }

        /**
         * {@inheritdoc}
         */
        public function is_configured() {
                return ! empty( $this->get_option( 'libretranslate_api_url' ) );
        }

        /**
         * {@inheritdoc}
         */
        public function translate( $text, $source = 'it', $target = 'en', $domain = 'general' ) {
                if ( '' === trim( (string) $text ) ) {
                        return '';
                }

                if ( ! $this->is_configured() ) {
                        return new WP_Error( 'fpml_libretranslate_missing_url', __( 'Configura un endpoint LibreTranslate valido per procedere con la traduzione.', 'fp-multilanguage' ) );
                }

                $max_chars = (int) $this->get_option( 'max_chars', 4500 );
                $chunks    = $this->chunk_text( $text, $max_chars );
                $source    = strtolower( $source );
                $target    = strtolower( $target );

                $translated = '';

                foreach ( $chunks as $chunk ) {
                        $chunk_to_send = $this->apply_glossary_pre( $chunk, $source, $target, $domain );
                        $result        = $this->request_translation( $chunk_to_send, $source, $target );

                        if ( is_wp_error( $result ) ) {
                                return $result;
                        }

                        $translated .= $this->apply_glossary_post( $result, $source, $target, $domain );
                }

                return $translated;
        }

        /**
         * Perform LibreTranslate request.
         *
         * @since 0.2.0
         *
         * @param string $text   Chunk text.
         * @param string $source Source language.
         * @param string $target Target language.
         *
         * @return string|WP_Error
         */
        protected function request_translation( $text, $source, $target ) {
                $base_url = rtrim( $this->get_option( 'libretranslate_api_url' ), '/' );

                if ( empty( $base_url ) ) {
                        return new WP_Error( 'fpml_libretranslate_missing_url', __( 'Specificare un endpoint LibreTranslate valido.', 'fp-multilanguage' ) );
                }

                $url = $base_url . '/translate';

                $body = array(
                        'q'      => $text,
                        'source' => $source,
                        'target' => 'en' === $target ? 'en' : $target,
                        'format' => 'html',
                );

                $api_key = $this->get_option( 'libretranslate_api_key' );
                if ( ! empty( $api_key ) ) {
                        $body['api_key'] = $api_key;
                }

                $args = array(
                        'timeout' => 45,
                        'headers' => array(
                                'Accept'       => 'application/json',
                                'Content-Type' => 'application/json',
                        ),
                        'body'    => wp_json_encode( $body ),
                );

                $max_attempts = 3;

                for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
                        $response = wp_remote_post( $url, $args );

                        if ( is_wp_error( $response ) ) {
                                if ( $attempt === $max_attempts ) {
                                        return new WP_Error( 'fpml_libretranslate_http_error', sprintf( __( 'Errore di connessione a LibreTranslate: %s', 'fp-multilanguage' ), $response->get_error_message() ) );
                                }

                                $this->backoff( $attempt );
                                continue;
                        }

                        $code = (int) wp_remote_retrieve_response_code( $response );
                        if ( in_array( $code, array( 429, 500, 502, 503 ), true ) ) {
                                if ( $attempt === $max_attempts ) {
                                        return new WP_Error( 'fpml_libretranslate_rate_limit', __( 'LibreTranslate ha restituito un errore di rate limit o temporaneo.', 'fp-multilanguage' ) );
                                }

                                $this->backoff( $attempt );
                                continue;
                        }

                        $body_content = wp_remote_retrieve_body( $response );
                        if ( $code < 200 || $code >= 300 ) {
                                return new WP_Error( 'fpml_libretranslate_error', sprintf( __( 'Risposta non valida da LibreTranslate (%1$d): %2$s', 'fp-multilanguage' ), $code, wp_kses_post( $body_content ) ) );
                        }

                        $data = json_decode( $body_content, true );
                        if ( isset( $data['translatedText'] ) ) {
                                return (string) $data['translatedText'];
                        }

                        if ( isset( $data['error'] ) ) {
                                return new WP_Error( 'fpml_libretranslate_error', sprintf( __( 'LibreTranslate ha restituito un errore: %s', 'fp-multilanguage' ), wp_kses_post( $data['error'] ) ) );
                        }

                        return new WP_Error( 'fpml_libretranslate_empty', __( 'LibreTranslate non ha restituito alcun contenuto traducibile.', 'fp-multilanguage' ) );
                }

                return new WP_Error( 'fpml_libretranslate_unexpected', __( 'Errore imprevisto durante la traduzione con LibreTranslate.', 'fp-multilanguage' ) );
        }

        /**
         * {@inheritdoc}
         */
        protected function get_rate_option_key() {
                return 'rate_libretranslate';
        }
}
