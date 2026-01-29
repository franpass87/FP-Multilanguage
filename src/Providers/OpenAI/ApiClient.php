<?php
/**
 * OpenAI Provider API Client - Handles HTTP requests to OpenAI API.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Providers\OpenAI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles HTTP requests to OpenAI API with retry logic.
 *
 * @since 0.10.0
 */
class ApiClient {
	/**
	 * API endpoint.
	 */
	const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Error handler instance.
	 *
	 * @var ErrorHandler
	 */
	protected ErrorHandler $error_handler;

	/**
	 * Retry manager instance.
	 *
	 * @var RetryManager
	 */
	protected RetryManager $retry_manager;

	/**
	 * Prompt builder instance.
	 *
	 * @var PromptBuilder
	 */
	protected PromptBuilder $prompt_builder;

	/**
	 * Constructor.
	 *
	 * @param ErrorHandler  $error_handler Error handler instance.
	 * @param RetryManager   $retry_manager Retry manager instance.
	 * @param PromptBuilder  $prompt_builder Prompt builder instance.
	 */
	public function __construct( ErrorHandler $error_handler, RetryManager $retry_manager, PromptBuilder $prompt_builder ) {
		$this->error_handler = $error_handler;
		$this->retry_manager = $retry_manager;
		$this->prompt_builder = $prompt_builder;
	}

	/**
	 * Request translation from OpenAI API.
	 *
	 * @since 0.10.0
	 *
	 * @param string $text      Text to translate.
	 * @param string $source    Source language.
	 * @param string $target    Target language.
	 * @param string $domain    Context domain.
	 * @param bool   $marketing Whether to apply marketing tone.
	 * @param string $api_key   API key.
	 * @param string $model     Model name.
	 * @return string|\WP_Error
	 */
	public function request_translation( string $text, string $source, string $target, string $domain, bool $marketing, string $api_key, string $model ): string|\WP_Error {
		$payload = array(
			'model'       => $model,
			'temperature' => 1,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $this->prompt_builder->build_system_prompt( $marketing ),
				),
				array(
					'role'    => 'user',
					'content' => $this->prompt_builder->build_user_prompt( $text, $source, $target, $domain ),
				),
			),
		);

		$body = wp_json_encode( $payload );
		if ( false === $body ) {
			return new \WP_Error( '\FPML_openai_encoding_error', __( 'Impossibile codificare il payload JSON per OpenAI.', 'fp-multilanguage' ) );
		}

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => $body,
			'timeout' => 45,
		);

		$max_attempts = 5;

		for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
			$response = wp_remote_post( self::API_ENDPOINT, $args );

			if ( is_wp_error( $response ) ) {
				if ( $attempt === $max_attempts ) {
					return new \WP_Error( '\FPML_openai_http_error', sprintf( __( 'Errore di connessione a OpenAI: %s', 'fp-multilanguage' ), $response->get_error_message() ) );
				}

				$this->retry_manager->backoff( $attempt );
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			$error_data = $this->error_handler->parse_error_data( $body );
			
			$error_type = $error_data['error']['type'] ?? '';
			$api_message = $error_data['error']['message'] ?? '';

			// Check quota error first (don't retry)
			if ( $this->retry_manager->is_quota_error( $code, $error_type, $api_message ) ) {
				return $this->error_handler->handle_quota_error( $api_message );
			}

			// Retryable errors
			if ( $this->retry_manager->is_retryable_error( $code ) ) {
				if ( $attempt === $max_attempts ) {
					$retry_after = wp_remote_retrieve_header( $response, 'retry-after' );
					return $this->error_handler->handle_rate_limit_error( $code, $retry_after, $api_message, $max_attempts );
				}

				\FP\Multilanguage\Logger::warning(
					sprintf( 'OpenAI tentativo %d/%d fallito con codice %d', $attempt, $max_attempts, $code ),
					array(
						'provider'  => 'openai',
						'attempt'   => $attempt,
						'http_code' => $code,
					)
				);

				$this->retry_manager->backoff( $attempt );
				continue;
			}

			// Client errors (4xx except 429) - don't retry
			if ( $code >= 400 && $code < 500 ) {
				return $this->error_handler->handle_client_error( $code, $api_message );
			}

			// Other errors
			if ( $code < 200 || $code >= 300 ) {
				return new \WP_Error( '\FPML_openai_error', sprintf( __( 'Risposta non valida da OpenAI (%1$d): %2$s', 'fp-multilanguage' ), $code, wp_kses_post( $body ) ) );
			}

			// Success - parse response
			$data = json_decode( $body, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return new \WP_Error(
					'\FPML_openai_invalid_json',
					sprintf(
						__( 'Risposta JSON non valida da OpenAI: %s', 'fp-multilanguage' ),
						json_last_error_msg()
					)
				);
			}

			if ( null === $data && json_last_error() === JSON_ERROR_NONE ) {
				$data = array();
			}

			if ( ! isset( $data['choices'][0]['message']['content'] ) || empty( $data['choices'][0]['message']['content'] ) ) {
				return new \WP_Error( '\FPML_openai_empty', __( 'OpenAI non ha restituito alcun contenuto traducibile.', 'fp-multilanguage' ) );
			}

			return (string) $data['choices'][0]['message']['content'];
		}

		return new \WP_Error( '\FPML_openai_unexpected', __( 'Errore imprevisto durante la traduzione con OpenAI.', 'fp-multilanguage' ) );
	}
}















