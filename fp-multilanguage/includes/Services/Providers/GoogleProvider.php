<?php
namespace FPMultilanguage\Services\Providers;

use FPMultilanguage\Services\Logger;
use FPMultilanguage\Services\TranslationResponse;

class GoogleProvider implements TranslationProviderInterface {

	private Logger $logger;

	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	public function get_name(): string {
		return 'google';
	}

	public function translate( string $text, string $source, string $target, array $options = array() ): ?TranslationResponse {
		$apiKey = (string) ( $options['api_key'] ?? '' );
		if ( $apiKey === '' ) {
			return null;
		}

		$url     = add_query_arg( array( 'key' => $apiKey ), 'https://translation.googleapis.com/language/translate/v2' );
		$format  = strtolower( (string) ( $options['format'] ?? 'text' ) ) === 'html' ? 'html' : 'text';
		$timeout = isset( $options['timeout'] ) ? (int) $options['timeout'] : 20;

		$body = array(
			'q'      => $text,
			'source' => $source,
			'target' => $target,
			'format' => $format,
		);

                $glossaryConfig = array();
                if ( ! empty( $options['glossary'] ) && is_array( $options['glossary'] ) ) {
                        $glossaryConfig = $options['glossary'];
                }

                $glossaryId = isset( $options['glossary_id'] ) ? (string) $options['glossary_id'] : '';
                if ( $glossaryId !== '' ) {
                        $glossaryConfig = array( 'glossary' => $glossaryId );
                        if ( ! empty( $options['glossary_ignore_case'] ) ) {
                                $glossaryConfig['ignoreCase'] = true;
                        }
                }

                if ( ! empty( $glossaryConfig ) ) {
                        $body['glossaryConfig'] = $glossaryConfig;
                }

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => max( 5, $timeout ),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->warning( 'Google Translate returned WP_Error', array( 'error' => $response->get_error_message() ) );

			return null;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status !== 200 ) {
			$this->logger->warning( 'Google Translate HTTP error', array( 'status' => $status ) );

			return null;
		}

				$body = wp_remote_retrieve_body( $response );
		if ( ! is_string( $body ) || $body === '' ) {
				$this->logger->warning( 'Google Translate empty body response' );

				return null;
		}

				$payload = json_decode( $body, true );
		if ( ! is_array( $payload ) ) {
				$this->logger->warning( 'Google Translate invalid JSON response' );

				return null;
		}

				$translations = $payload['data']['translations'] ?? array();
		if ( ! is_array( $translations ) || ! isset( $translations[0]['translatedText'] ) ) {
				$this->logger->warning( 'Google Translate missing translatedText field' );

				return null;
		}

				$translated = (string) $translations[0]['translatedText'];
		if ( $translated === '' ) {
				return null;
		}

				return new TranslationResponse( $translated, true, array( 'provider' => $this->get_name() ) );
	}
}
