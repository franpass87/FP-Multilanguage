<?php
/**
 * OpenAI translation provider.
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
 * Translate content using OpenAI Chat Completions.
 *
 * @since 0.2.0
 */
class FPML_Provider_OpenAI extends FPML_Base_Provider {
        const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

        /**
         * {@inheritdoc}
         */
        public function get_slug() {
                return 'openai';
        }

        /**
         * {@inheritdoc}
         */
        public function is_configured() {
                $key   = $this->get_option( 'openai_api_key' );
                $model = $this->get_option( 'openai_model', 'gpt-4o-mini' );

                return ! empty( $key ) && ! empty( $model );
        }

        /**
         * {@inheritdoc}
         */
        public function translate( $text, $source = 'it', $target = 'en', $domain = 'general' ) {
                if ( '' === trim( (string) $text ) ) {
                        return '';
                }

                if ( ! $this->is_configured() ) {
                        return new WP_Error( 'fpml_openai_missing_key', __( 'Configura una chiave API OpenAI valida per procedere con la traduzione.', 'fp-multilanguage' ) );
                }

                $max_chars = (int) $this->get_option( 'max_chars', 4500 );
                $chunks    = $this->chunk_text( $text, $max_chars );
                $source    = strtolower( $source );
                $target    = strtolower( $target );

                $translated = '';
                $marketing  = (bool) $this->get_option( 'marketing_tone', false );

                foreach ( $chunks as $chunk ) {
                        $chunk_to_send = $this->apply_glossary_pre( $chunk, $source, $target, $domain );
                        $result        = $this->request_translation( $chunk_to_send, $source, $target, $domain, $marketing );

                        if ( is_wp_error( $result ) ) {
                                return $result;
                        }

                        $translated .= $this->apply_glossary_post( $result, $source, $target, $domain );
                }

                return $translated;
        }

        /**
         * Perform request to OpenAI API.
         *
         * @since 0.2.0
         *
         * @param string $text      Chunk text.
         * @param string $source    Source language.
         * @param string $target    Target language.
         * @param string $domain    Context domain.
         * @param bool   $marketing Whether to apply a marketing tone.
         *
         * @return string|WP_Error
         */
        protected function request_translation( $text, $source, $target, $domain, $marketing ) {
                $payload = array(
                        'model'       => $this->get_option( 'openai_model', 'gpt-4o-mini' ),
                        'temperature' => 0.2,
                        'messages'    => array(
                                array(
                                        'role'    => 'system',
                                        'content' => $this->get_system_prompt( $marketing ),
                                ),
                                array(
                                        'role'    => 'user',
                                        'content' => $this->build_user_prompt( $text, $source, $target, $domain ),
                                ),
                        ),
                );

                $args = array(
                        'headers' => array(
                                'Authorization' => 'Bearer ' . $this->get_option( 'openai_api_key' ),
                                'Content-Type'  => 'application/json',
                        ),
                        'body'    => wp_json_encode( $payload ),
                        'timeout' => 45,
                );

                $max_attempts = 3;

                for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
                        $response = wp_remote_post( self::API_ENDPOINT, $args );

                        if ( is_wp_error( $response ) ) {
                                if ( $attempt === $max_attempts ) {
                                        return new WP_Error( 'fpml_openai_http_error', sprintf( __( 'Errore di connessione a OpenAI: %s', 'fp-multilanguage' ), $response->get_error_message() ) );
                                }

                                $this->backoff( $attempt );
                                continue;
                        }

                        $code = (int) wp_remote_retrieve_response_code( $response );
                        if ( in_array( $code, array( 429, 500, 502, 503 ), true ) ) {
                                if ( $attempt === $max_attempts ) {
                                        return new WP_Error( 'fpml_openai_rate_limit', __( 'OpenAI ha restituito un errore di rate limit o temporaneo.', 'fp-multilanguage' ) );
                                }

                                $this->backoff( $attempt );
                                continue;
                        }

                        if ( $code < 200 || $code >= 300 ) {
                                $body = wp_remote_retrieve_body( $response );
                                return new WP_Error( 'fpml_openai_error', sprintf( __( 'Risposta non valida da OpenAI (%1$d): %2$s', 'fp-multilanguage' ), $code, wp_kses_post( $body ) ) );
                        }

                        $data = json_decode( wp_remote_retrieve_body( $response ), true );

                        if ( empty( $data['choices'][0]['message']['content'] ) ) {
                                return new WP_Error( 'fpml_openai_empty', __( 'OpenAI non ha restituito alcun contenuto traducibile.', 'fp-multilanguage' ) );
                        }

                        return (string) $data['choices'][0]['message']['content'];
                }

                return new WP_Error( 'fpml_openai_unexpected', __( 'Errore imprevisto durante la traduzione con OpenAI.', 'fp-multilanguage' ) );
        }

        /**
         * Build system prompt.
         *
         * @since 0.2.0
         *
         * @param bool $marketing Whether marketing tone is enabled.
         *
         * @return string
         */
        protected function get_system_prompt( $marketing ) {
                $prompt  = 'You are a professional Italian to English (United States) translator. Preserve HTML tags, attributes, shortcodes, and URLs. Never translate shortcode names, attribute values, or code samples. Respond with English content only.';
                $prompt .= ' Maintain neutral, clear language suitable for a broad audience.';

                if ( $marketing ) {
                        $prompt .= ' When possible, adopt a slightly promotional tone while remaining natural and trustworthy.';
                }

                return $prompt;
        }

        /**
         * Build user prompt.
         *
         * @since 0.2.0
         *
         * @param string $text   Input chunk.
         * @param string $source Source language.
         * @param string $target Target language.
         * @param string $domain Context domain.
         *
         * @return string
         */
        protected function build_user_prompt( $text, $source, $target, $domain ) {
                $instructions  = sprintf( 'Translate the following %1$s content from %2$s to %3$s. Preserve formatting, HTML structure, and shortcodes exactly.', $domain, $source, $target );
                $instructions .= ' Do not translate URLs, HTML attributes, CSS classes, IDs, or shortcode parameters. Return only the translated content without additional commentary.';

                return $instructions . "\n\n" . $text;
        }

        /**
         * {@inheritdoc}
         */
        protected function get_rate_option_key() {
                return 'rate_openai';
        }
}
