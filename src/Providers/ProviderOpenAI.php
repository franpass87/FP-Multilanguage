<?php
/**
 * OpenAI translation provider.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.2.0
 * @since 0.10.0 Refactored to use modular components.
 */

namespace FP\Multilanguage\Providers;

use FP\Multilanguage\Core\Container;
use FP\Multilanguage\Providers\OpenAI\ApiClient;
use FP\Multilanguage\Providers\OpenAI\ErrorHandler;
use FP\Multilanguage\Providers\OpenAI\RetryManager;
use FP\Multilanguage\Providers\OpenAI\PromptBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Translate content using OpenAI Chat Completions.
 *
 * @since 0.2.0
 * @since 0.10.0 Refactored to use modular components.
 */
class ProviderOpenAI extends BaseProvider {
	const CHAT_COMPLETIONS_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
	const RESPONSES_ENDPOINT        = 'https://api.openai.com/v1/responses';

	/**
	 * API client instance.
	 *
	 * @since 0.10.0
	 *
	 * @var ApiClient
	 */
	protected ApiClient $api_client;

	/**
	 * Constructor.
	 *
	 * @since 0.10.0
	 */
	public function __construct() {
		parent::__construct();

		// Initialize modules
		$error_handler = new ErrorHandler();
		$retry_manager = new RetryManager();
		$prompt_builder = new PromptBuilder();
		$this->api_client = new ApiClient( $error_handler, $retry_manager, $prompt_builder );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'openai';
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		$key   = $this->get_option( 'openai_api_key' );
		$model = $this->get_option( 'openai_model', 'gpt-5.4-mini' );

		return ! empty( $key ) && ! empty( $model );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $text   Text to translate.
	 * @param string $source Source language code.
	 * @param string $target Target language code.
	 * @param string $domain Context domain.
	 * @return string|\WP_Error
	 */
	public function translate( string $text, string $source = '', string $target = '', string $domain = 'general' ): string|\WP_Error {
		if ( '' === trim( (string) $text ) ) {
			return '';
		}

		if ( '' === $source ) {
			$source = function_exists( 'fpml_get_source_language' ) ? fpml_get_source_language() : 'it';
		}
		if ( '' === $target ) {
			$target = function_exists( 'fpml_get_primary_target_language' ) ? fpml_get_primary_target_language() : 'en';
		}

		if ( ! $this->is_configured() ) {
			return new \WP_Error( 'fpml_openai_missing_key', __( 'Configura una chiave API OpenAI valida per procedere con la traduzione.', 'fp-multilanguage' ) );
		}

		// Check cache first
		$cache = Container::get( 'translation_cache' );
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
			$result        = $this->api_client->request_translation(
				$chunk_to_send,
				$source,
				$target,
				$domain,
				$marketing,
				$this->get_option( 'openai_api_key' ),
				$this->get_option( 'openai_model', 'gpt-5.4-mini' ),
				$this->get_option( 'openai_api_method', 'responses' )
			);

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
	 * {@inheritdoc}
	 *
	 * @return string
	 */
	protected function get_rate_option_key(): string {
		return 'rate_openai';
	}

	/**
	 * Verify OpenAI API key and billing status.
	 *
	 * @since 0.4.2
	 *
	 * @return array|\WP_Error Array with status info or WP_Error on failure.
	 */
	public function verify_billing_status(): array|\WP_Error {
		if ( ! $this->is_configured() ) {
			return new \WP_Error( 'fpml_openai_not_configured', __( 'Chiave API OpenAI non configurata.', 'fp-multilanguage' ) );
		}

		$api_method = $this->normalize_api_method( (string) $this->get_option( 'openai_api_method', 'responses' ) );
		$test_payload = $this->build_verification_payload(
			(string) $this->get_option( 'openai_model', 'gpt-5.4-mini' ),
			$api_method
		);

		$body = wp_json_encode( $test_payload );
		if ( false === $body ) {
			return new \WP_Error( 'fpml_encoding_error', __( 'Errore di codifica JSON.', 'fp-multilanguage' ) );
		}

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->get_option( 'openai_api_key' ),
				'Content-Type'  => 'application/json',
			),
			'body'    => $body,
			'timeout' => 15,
		);

		$response = wp_remote_post( $this->get_api_endpoint( $api_method ), $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data = json_decode( $response_body, true );
		
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			\FP\Multilanguage\Logger::warning(
				'JSON decode error in OpenAI billing verification',
				array(
					'error'     => json_last_error(),
					'error_msg' => json_last_error_msg(),
					'http_code' => $code,
				)
			);
			$data = array();
		}

		if ( 200 === $code ) {
			return array(
				'status'  => 'ok',
				'message' => __( 'Chiave API OpenAI valida e billing configurato.', 'fp-multilanguage' ),
			);
		}

		$error_type = $data['error']['type'] ?? '';
		$api_message = $data['error']['message'] ?? '';

		if ( 429 === $code && ( 'insufficient_quota' === $error_type || false !== stripos( $api_message, 'quota' ) ) ) {
			return new \WP_Error( 'fpml_openai_quota_exceeded', __( 'Quota OpenAI superata o non configurata.', 'fp-multilanguage' ) );
		}

		return new \WP_Error( 'fpml_openai_verification_failed', sprintf( __( 'Verifica fallita (codice %d): %s', 'fp-multilanguage' ), $code, wp_kses_post( $api_message ) ) );
	}

	/**
	 * Normalize API method to supported values.
	 *
	 * @param string $api_method Raw API method value.
	 * @return string
	 */
	protected function normalize_api_method( string $api_method ): string {
		return in_array( $api_method, array( 'responses', 'chat_completions' ), true ) ? $api_method : 'responses';
	}

	/**
	 * Resolve endpoint based on configured API method.
	 *
	 * @param string $api_method API method.
	 * @return string
	 */
	protected function get_api_endpoint( string $api_method ): string {
		return 'chat_completions' === $api_method ? self::CHAT_COMPLETIONS_ENDPOINT : self::RESPONSES_ENDPOINT;
	}

	/**
	 * Build minimal verification payload for selected API method.
	 *
	 * @param string $model      OpenAI model.
	 * @param string $api_method API method.
	 * @return array<string,mixed>
	 */
	protected function build_verification_payload( string $model, string $api_method ): array {
		if ( 'chat_completions' === $api_method ) {
			return array(
				'model'      => $model,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => 'Hi',
					),
				),
				'max_tokens' => 5,
			);
		}

		return array(
			'model'             => $model,
			'input'             => 'Hi',
			'max_output_tokens' => 5,
		);
	}
}
