<?php
namespace FPMultilanguage\Admin\Settings;

use FPMultilanguage\Services\Logger;
use WP_Error;

class ProviderTester {
	private Logger $logger;

	private Repository $repository;

	public function __construct( Logger $logger, Repository $repository ) {
		$this->logger     = $logger;
		$this->repository = $repository;
	}

	/**
	 * @param array<string, mixed> $options
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function sanitize_options( string $provider, array $options ) {
		$defaults = $this->repository->get_provider_defaults( $provider );
		if ( empty( $defaults ) ) {
			$message = __( 'Provider non valido.', 'fp-multilanguage' );

			return new WP_Error( 'invalid_provider', $message, array( 'status' => 400 ) );
		}

		$sanitized            = wp_parse_args( $options, $defaults );
		$sanitized['enabled'] = ! empty( $sanitized['enabled'] );
		$sanitized['api_key'] = sanitize_text_field( (string) ( $sanitized['api_key'] ?? '' ) );

		if ( isset( $sanitized['timeout'] ) ) {
			$sanitized['timeout'] = max( 5, (int) $sanitized['timeout'] );
		} else {
			$sanitized['timeout'] = 20;
		}

		if ( isset( $sanitized['endpoint'] ) ) {
			$sanitized['endpoint'] = esc_url_raw( (string) $sanitized['endpoint'] );
		}

		if ( isset( $sanitized['glossary_id'] ) ) {
			$glossaryId = sanitize_text_field( (string) $sanitized['glossary_id'] );
			$glossaryId = html_entity_decode( $glossaryId, ENT_QUOTES, 'UTF-8' );
			$glossaryId = preg_replace( '/[\r\n]+/', '', $glossaryId );
			if ( null === $glossaryId ) {
				$glossaryId = '';
			}

			$sanitized['glossary_id'] = trim( $glossaryId );
		}

		if ( 'google' === $provider ) {
			$sanitized['glossary_ignore_case'] = ! empty( $sanitized['glossary_ignore_case'] ) && '' !== $sanitized['glossary_id'];
		}

		if ( 'deepl' === $provider ) {
			$formality = strtolower( sanitize_text_field( (string) ( $sanitized['formality'] ?? '' ) ) );
			$allowed   = array( 'default', 'more', 'less' );
			if ( ! in_array( $formality, $allowed, true ) ) {
				$formality = 'default';
			}

			$sanitized['formality'] = $formality;
		}

		return $sanitized;
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array{success:bool,message:string,details:array<string,mixed>}
	 */
	public function test_credentials( string $provider, array $options ): array {
		switch ( $provider ) {
			case 'google':
				return $this->test_google_credentials( $options );
			case 'deepl':
				return $this->test_deepl_credentials( $options );
			default:
				return $this->provider_test_response( false, __( 'Provider non supportato.', 'fp-multilanguage' ) );
		}
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array{success:bool,message:string,details:array<string,mixed>}
	 */
	private function test_google_credentials( array $options ): array {
		$apiKey = (string) ( $options['api_key'] ?? '' );

		if ( '' === $apiKey ) {
			return $this->provider_test_response( false, __( 'Inserisci una chiave API Google prima di avviare la verifica.', 'fp-multilanguage' ) );
		}

		if ( ! function_exists( 'wp_remote_get' ) ) {
			return $this->provider_test_response( false, __( 'La funzione di rete di WordPress non è disponibile.', 'fp-multilanguage' ) );
		}

		$endpoint = add_query_arg(
			array(
				'key'    => $apiKey,
				'target' => 'en',
			),
			'https://translation.googleapis.com/language/translate/v2/languages'
		);

		$timeout  = isset( $options['timeout'] ) ? max( 5, (int) $options['timeout'] ) : 20;
		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout' => $timeout,
			)
		);

		if ( is_wp_error( $response ) ) {
			$errorMessage = $response->get_error_message();
			$this->logger->warning( 'Google credential test failed with WP_Error', array( 'error' => $errorMessage ) );

			return $this->provider_test_response(
				false,
				sprintf(
					/* translators: %s error message */
					__( 'Errore di connessione a Google: %s', 'fp-multilanguage' ),
					$errorMessage
				)
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );

		if ( 200 !== $status ) {
			$message = $this->extract_google_error_message( $body );
			$this->logger->warning(
				'Google credential test returned non-200 status',
				array(
					'status' => $status,
					'body'   => $body,
				)
			);

			return $this->provider_test_response(
				false,
				sprintf(
					/* translators: %s error message */
					__( 'Verifica Google non riuscita: %s', 'fp-multilanguage' ),
					$message
				)
			);
		}

		$decoded        = json_decode( $body, true );
		$languagesCount = 0;
		if ( is_array( $decoded ) && isset( $decoded['data']['languages'] ) && is_array( $decoded['data']['languages'] ) ) {
			$languagesCount = count( $decoded['data']['languages'] );
		}

		return $this->provider_test_response(
			true,
			__( 'Connessione a Google riuscita.', 'fp-multilanguage' ),
			array(
				'languages' => $languagesCount,
			)
		);
	}

	/**
	 * @param array<string, mixed> $options
	 * @return array{success:bool,message:string,details:array<string,mixed>}
	 */
	private function test_deepl_credentials( array $options ): array {
		$apiKey = (string) ( $options['api_key'] ?? '' );

		if ( '' === $apiKey ) {
			return $this->provider_test_response( false, __( 'Inserisci una chiave API DeepL prima di avviare la verifica.', 'fp-multilanguage' ) );
		}

		if ( ! function_exists( 'wp_remote_get' ) ) {
			return $this->provider_test_response( false, __( 'La funzione di rete di WordPress non è disponibile.', 'fp-multilanguage' ) );
		}

		$endpoint = (string) ( $options['endpoint'] ?? $this->repository->get_provider_defaults( 'deepl' )['endpoint'] ?? 'https://api.deepl.com/v2/translate' );
		if ( '' === $endpoint ) {
			$endpoint = 'https://api.deepl.com/v2/translate';
		}

		$baseEndpoint = rtrim( $endpoint, '/' );
		if ( '' === $baseEndpoint ) {
			$baseEndpoint = 'https://api.deepl.com/v2/translate';
		}

		$usageEndpoint = preg_replace( '/\/translate$/', '/usage', $baseEndpoint );
		if ( ! is_string( $usageEndpoint ) || '' === $usageEndpoint ) {
			$usageEndpoint = $baseEndpoint;
			if ( substr( $usageEndpoint, -6 ) !== '/usage' ) {
				$usageEndpoint .= '/usage';
			}
		}

		$usageEndpoint = rtrim( $usageEndpoint, '/' );
		if ( '' === $usageEndpoint ) {
			$usageEndpoint = 'https://api.deepl.com/v2/usage';
		}

		$timeout = isset( $options['timeout'] ) ? max( 5, (int) $options['timeout'] ) : 20;

		$response = wp_remote_get(
			$usageEndpoint,
			array(
				'timeout' => $timeout,
				'headers' => array(
					'Authorization' => 'DeepL-Auth-Key ' . $apiKey,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$errorMessage = $response->get_error_message();
			$this->logger->warning( 'DeepL credential test failed with WP_Error', array( 'error' => $errorMessage ) );

			return $this->provider_test_response(
				false,
				sprintf(
					/* translators: %s error message */
					__( 'Errore di connessione a DeepL: %s', 'fp-multilanguage' ),
					$errorMessage
				)
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );

		if ( 200 !== $status ) {
			$message = $this->extract_deepl_error_message( $body );
			$this->logger->warning(
				'DeepL credential test returned non-200 status',
				array(
					'status' => $status,
					'body'   => $body,
				)
			);

			return $this->provider_test_response(
				false,
				sprintf(
					/* translators: %s error message */
					__( 'Verifica DeepL non riuscita: %s', 'fp-multilanguage' ),
					$message
				)
			);
		}

		$decoded        = json_decode( $body, true );
		$charactersUsed = (int) ( $decoded['character_count'] ?? 0 );
		$characterLimit = isset( $decoded['character_limit'] ) ? (int) $decoded['character_limit'] : 0;
		$remaining      = $characterLimit > 0 ? max( 0, $characterLimit - $charactersUsed ) : null;
		$message        = __( 'Connessione a DeepL riuscita.', 'fp-multilanguage' );
		if ( null !== $remaining ) {
			$formatted = function_exists( 'number_format_i18n' ) ? number_format_i18n( $remaining ) : (string) $remaining;
			$message   = sprintf(
				/* translators: %s remaining characters */
				__( 'Connessione a DeepL riuscita. Caratteri residui: %s', 'fp-multilanguage' ),
				$formatted
			);
		}

		return $this->provider_test_response(
			true,
			$message,
			array(
				'character_count' => $charactersUsed,
				'character_limit' => $characterLimit,
			)
		);
	}

	private function extract_google_error_message( mixed $body ): string {
		if ( is_string( $body ) ) {
			$decoded = json_decode( $body, true );
			if ( is_array( $decoded ) && isset( $decoded['error']['message'] ) ) {
				return (string) $decoded['error']['message'];
			}
		}

		return __( 'Risposta non valida da Google.', 'fp-multilanguage' );
	}

	private function extract_deepl_error_message( mixed $body ): string {
		if ( is_string( $body ) ) {
			$decoded = json_decode( $body, true );
			if ( is_array( $decoded ) && isset( $decoded['message'] ) ) {
				return (string) $decoded['message'];
			}
		}

		return __( 'Risposta non valida da DeepL.', 'fp-multilanguage' );
	}

	/**
	 * @param array<string, mixed> $details
	 * @return array{success:bool,message:string,details:array<string,mixed>}
	 */
	private function provider_test_response( bool $success, string $message, array $details = array() ): array {
		return array(
			'success' => $success,
			'message' => $message,
			'details' => $details,
		);
	}
}
