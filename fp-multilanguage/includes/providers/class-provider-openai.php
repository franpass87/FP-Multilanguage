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
                $model = $this->get_option( 'openai_model', 'gpt-5' );

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
		$marketing  = (bool) $this->get_option( 'marketing_tone', false );

		foreach ( $chunks as $chunk ) {
			$chunk_to_send = $this->apply_glossary_pre( $chunk, $source, $target, $domain );
			$result        = $this->request_translation( $chunk_to_send, $source, $target, $domain, $marketing );

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

		$body = wp_json_encode( $payload );
		if ( false === $body ) {
			return new WP_Error( 'fpml_openai_encoding_error', __( 'Impossibile codificare il payload JSON per OpenAI.', 'fp-multilanguage' ) );
		}

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->get_option( 'openai_api_key' ),
				'Content-Type'  => 'application/json',
			),
			'body'    => $body,
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

                        // Errori temporanei - retry ha senso
                        if ( in_array( $code, array( 429, 500, 502, 503, 504 ), true ) ) {
                                if ( $attempt === $max_attempts ) {
                                        return new WP_Error( 'fpml_openai_rate_limit', __( 'OpenAI ha restituito un errore di rate limit o temporaneo.', 'fp-multilanguage' ) );
                                }

                                if ( class_exists( 'FPML_Logger' ) ) {
                                        FPML_Logger::instance()->log(
                                                'warning',
                                                sprintf( 'OpenAI tentativo %d/%d fallito con codice %d', $attempt, $max_attempts, $code ),
                                                array(
                                                        'provider' => 'openai',
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
			$body = wp_remote_retrieve_body( $response );
			$error_code = 'fpml_openai_client_error';
			$error_message = '';

			// Parse JSON error for better messaging
			$error_data = json_decode( $body, true );
			$error_type = isset( $error_data['error']['type'] ) ? $error_data['error']['type'] : '';
			$api_message = isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : $body;

			if ( 401 === $code || 403 === $code ) {
				$error_code = 'fpml_openai_auth_error';
				$error_message = sprintf(
					__( 'Errore di autenticazione OpenAI: La chiave API non è valida o non ha i permessi necessari. Verifica la tua chiave su https://platform.openai.com/api-keys', 'fp-multilanguage' )
				);
			} elseif ( 400 === $code ) {
				$error_code = 'fpml_openai_invalid_request';
				$error_message = sprintf(
					__( 'Richiesta non valida: %s', 'fp-multilanguage' ),
					wp_kses_post( $api_message )
				);
			} elseif ( 'insufficient_quota' === $error_type || false !== stripos( $api_message, 'quota' ) || false !== stripos( $api_message, 'billing' ) ) {
				// Errore di quota specifico
				$error_code = 'fpml_openai_quota_exceeded';
				$error_message = __( '❌ Quota OpenAI superata o non configurata.', 'fp-multilanguage' ) . "\n\n";
				$error_message .= __( '📋 Cosa significa:', 'fp-multilanguage' ) . "\n";
				$error_message .= __( '• Il tuo account OpenAI non ha crediti disponibili', 'fp-multilanguage' ) . "\n";
				$error_message .= __( '• OpenAI non offre più crediti gratuiti per i nuovi account', 'fp-multilanguage' ) . "\n";
				$error_message .= __( '• Devi configurare un metodo di pagamento prima di usare l\'API', 'fp-multilanguage' ) . "\n\n";
				$error_message .= __( '✅ Come risolvere:', 'fp-multilanguage' ) . "\n";
				$error_message .= __( '1. Vai su https://platform.openai.com/account/billing/overview', 'fp-multilanguage' ) . "\n";
				$error_message .= __( '2. Clicca su "Add payment details" e aggiungi una carta di credito', 'fp-multilanguage' ) . "\n";
				$error_message .= __( '3. Carica crediti (minimo $5) cliccando su "Add to credit balance"', 'fp-multilanguage' ) . "\n";
				$error_message .= __( '4. Attendi 1-2 minuti affinché i crediti vengano attivati', 'fp-multilanguage' ) . "\n";
				$error_message .= __( '5. Riprova il test', 'fp-multilanguage' ) . "\n\n";
				$error_message .= __( '💰 Costi: ~$0.15 per 1000 caratteri con gpt-4o-mini (molto economico)', 'fp-multilanguage' ) . "\n\n";
				$error_message .= __( '💡 Alternative gratuite: DeepL offre 500.000 caratteri/mese gratis!', 'fp-multilanguage' );
			} else {
				$error_message = sprintf(
					__( 'Errore client OpenAI (%1$d): %2$s', 'fp-multilanguage' ),
					$code,
					wp_kses_post( $api_message )
				);
			}

			return new WP_Error( $error_code, $error_message );
		}

                        // Altri errori
                        if ( $code < 200 || $code >= 300 ) {
                                $body = wp_remote_retrieve_body( $response );
                                return new WP_Error( 'fpml_openai_error', sprintf( __( 'Risposta non valida da OpenAI (%1$d): %2$s', 'fp-multilanguage' ), $code, wp_kses_post( $body ) ) );
                        }

			$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( null === $data ) {
			return new WP_Error( 'fpml_openai_invalid_json', __( 'Risposta JSON non valida da OpenAI.', 'fp-multilanguage' ) );
		}

		if ( ! isset( $data['choices'][0]['message']['content'] ) || empty( $data['choices'][0]['message']['content'] ) ) {
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

	/**
	 * Verify OpenAI API key and billing status.
	 *
	 * @since 0.4.2
	 *
	 * @return array|WP_Error Array with status info or WP_Error on failure.
	 */
	public function verify_billing_status() {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'fpml_openai_not_configured', __( 'Chiave API OpenAI non configurata.', 'fp-multilanguage' ) );
		}

		// Try a minimal request to check quota
		$test_payload = array(
			'model'       => $this->get_option( 'openai_model', 'gpt-4o-mini' ),
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => 'Hi',
				),
			),
			'max_tokens'  => 5,
		);

		$body = wp_json_encode( $test_payload );
		if ( false === $body ) {
			return new WP_Error( 'fpml_encoding_error', __( 'Errore di codifica JSON.', 'fp-multilanguage' ) );
		}

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->get_option( 'openai_api_key' ),
				'Content-Type'  => 'application/json',
			),
			'body'    => $body,
			'timeout' => 15,
		);

		$response = wp_remote_post( self::API_ENDPOINT, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data = json_decode( $response_body, true );

		if ( 200 === $code ) {
			return array(
				'status'  => 'ok',
				'message' => __( '✅ API key valida e billing configurato correttamente.', 'fp-multilanguage' ),
			);
		}

		// Check for quota/billing errors
		$error_type = isset( $data['error']['type'] ) ? $data['error']['type'] : '';
		$error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : '';

		if ( 'insufficient_quota' === $error_type || false !== stripos( $error_message, 'quota' ) || false !== stripos( $error_message, 'billing' ) ) {
			return array(
				'status'  => 'quota_exceeded',
				'message' => __( '❌ Quota superata o billing non configurato. Aggiungi crediti su https://platform.openai.com/account/billing/overview', 'fp-multilanguage' ),
				'details' => $error_message,
			);
		}

		if ( 401 === $code || 403 === $code ) {
			return array(
				'status'  => 'auth_error',
				'message' => __( '❌ Chiave API non valida o senza permessi. Verifica su https://platform.openai.com/api-keys', 'fp-multilanguage' ),
				'details' => $error_message,
			);
		}

		return array(
			'status'  => 'error',
			'message' => sprintf( __( 'Errore %d: %s', 'fp-multilanguage' ), $code, $error_message ),
		);
	}
}
