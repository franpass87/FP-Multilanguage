<?php
namespace FPMultilanguage\Services\Providers;

use FPMultilanguage\Services\Logger;
use FPMultilanguage\Services\TranslationResponse;

class DeepLProvider implements TranslationProviderInterface {

	private Logger $logger;

	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	public function get_name(): string {
		return 'deepl';
	}

	public function translate( string $text, string $source, string $target, array $options = array() ): ?TranslationResponse {
		$apiKey = (string) ( $options['api_key'] ?? '' );
		if ( $apiKey === '' ) {
			return null;
		}

		$endpoint = (string) ( $options['endpoint'] ?? 'https://api.deepl.com/v2/translate' );
		$body     = array(
			'auth_key'    => $apiKey,
			'text'        => $text,
			'source_lang' => strtoupper( $source ),
			'target_lang' => strtoupper( $target ),
		);

		if ( ! empty( $options['formality'] ) ) {
			$body['formality'] = $options['formality'];
		}

		if ( ! empty( $options['glossary'] ) ) {
			$body['glossary_id'] = $options['glossary'];
		}

		$format = strtolower( (string) ( $options['format'] ?? 'text' ) );
		if ( $format === 'html' ) {
			$body['tag_handling'] = 'html';
			if ( ! empty( $options['preserve_tags'] ) ) {
				$body['non_splitting_tags'] = implode( ',', (array) $options['preserve_tags'] );
			}
		}

		$timeout = isset( $options['timeout'] ) ? (int) $options['timeout'] : 20;

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => max( 5, $timeout ),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->warning( 'DeepL returned WP_Error', array( 'error' => $response->get_error_message() ) );

			return null;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status !== 200 ) {
			$this->logger->warning( 'DeepL HTTP error', array( 'status' => $status ) );

			return null;
		}

		$payload    = json_decode( wp_remote_retrieve_body( $response ), true );
		$translated = $payload['translations'][0]['text'] ?? '';
		if ( $translated === '' ) {
			return null;
		}

		return new TranslationResponse( (string) $translated, true, array( 'provider' => $this->get_name() ) );
	}
}
