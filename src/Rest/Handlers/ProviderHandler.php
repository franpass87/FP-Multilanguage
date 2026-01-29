<?php
/**
 * REST Provider Handler - Handles provider-related REST endpoints.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Rest\Handlers;

use FP\Multilanguage\Core\Container;
use FP\Multilanguage\Core\ContainerAwareTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles provider-related REST endpoints.
 *
 * @since 0.10.0
 */
class ProviderHandler {
	use ContainerAwareTrait;

	/**
	 * Get settings instance.
	 *
	 * @return \FPML_Settings|mixed
	 */
	protected function getSettings() {
		$container = $this->getContainer();
		if ( $container && $container->has( 'options' ) ) {
			return $container->get( 'options' );
		}
		// Fallback to singleton with null check
		if ( class_exists( '\FPML_Settings' ) ) {
			$settings = function_exists( 'fpml_get_options' ) ? fpml_get_options() : \FPML_Settings::instance();
			return $settings ? $settings : null;
		}
		return null;
	}
	/**
	 * Execute a provider test translation.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_REST_Request $request Request instance.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_test_provider( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$processor  = \FPML_fpml_get_processor();
		$translator = $processor->get_translator_instance();

		if ( is_wp_error( $translator ) ) {
			$translator->add_data( array( 'status' => 400 ), $translator->get_error_code() );

			return $translator;
		}

		$sample  = __( 'Questa è una frase di prova per FP Multilanguage.', 'fp-multilanguage' );
		$start   = microtime( true );
		$output  = $translator->translate( $sample, 'it', 'en', 'general' );
		$elapsed = microtime( true ) - $start;

		if ( is_wp_error( $output ) ) {
			$output->add_data( array( 'status' => 400 ), $output->get_error_code() );

			return $output;
		}

		$cost = $translator->estimate_cost( $sample );

		$settings = $this->getSettings();
		return rest_ensure_response(
			array(
				'success'        => true,
				'provider'       => $settings ? $settings->get( 'provider', '' ) : '',
				'elapsed'        => round( $elapsed, 4 ),
				'characters'     => mb_strlen( $sample, 'UTF-8' ),
				'estimated_cost' => $cost,
				'sample'         => $sample,
				'translation'    => wp_kses_post( $output ),
			)
		);
	}

	/**
	 * Preview translation without saving.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_REST_Request $request Request instance.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_preview_translation( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$text     = $request->get_param( 'text' );
		$provider = $request->get_param( 'provider' );
		$source   = $request->get_param( 'source' );
		$target   = $request->get_param( 'target' );

		if ( empty( $text ) ) {
			return new \WP_Error(
				'\FPML_empty_text',
				__( 'Testo da tradurre mancante.', 'fp-multilanguage' ),
				array( 'status' => 400 )
			);
		}

		$processor  = \FPML_fpml_get_processor();
		$translator = $processor->get_translator_instance();

		// Override provider if specified
		if ( ! empty( $provider ) ) {
			$settings = $this->getSettings();
			$current_provider = $settings ? $settings->get( 'provider', '' ) : '';
			
			if ( $provider !== $current_provider ) {
				// Temporarily switch provider for preview
				$translator = $this->get_translator_by_slug( $provider );
			}
		}

		if ( is_wp_error( $translator ) ) {
			$translator->add_data( array( 'status' => 400 ), $translator->get_error_code() );
			return $translator;
		}

		// Check cache first
		$cache = Container::get( 'translation_cache' );
		if ( $cache ) {
			$cached = $cache->get( $text, $provider ?: 'current', $source, $target );
			if ( false !== $cached ) {
				return rest_ensure_response(
					array(
						'success'        => true,
						'original'       => $text,
						'translated'     => $cached,
						'provider'       => $provider ?: $this->get_default_provider(),
						'cached'         => true,
						'characters'     => mb_strlen( $text, 'UTF-8' ),
						'estimated_cost' => 0,
					)
				);
			}
		}

		// Translate
		$start       = microtime( true );
		$translated  = $translator->translate( $text, $source, $target, 'general' );
		$elapsed     = microtime( true ) - $start;

		if ( is_wp_error( $translated ) ) {
			$translated->add_data( array( 'status' => 400 ), $translated->get_error_code() );
			return $translated;
		}

		$cost = $translator->estimate_cost( $text );

		// Cache the result
		if ( $cache ) {
			$cache->set( $text, $provider ?: 'current', $translated, $source, $target );
		}

		return rest_ensure_response(
			array(
				'success'        => true,
				'original'       => $text,
				'translated'     => wp_kses_post( $translated ),
				'provider'       => $provider ?: $this->get_default_provider(),
				'cached'         => false,
				'elapsed'        => round( $elapsed, 4 ),
				'characters'     => mb_strlen( $text, 'UTF-8' ),
				'estimated_cost' => $cost,
			)
		);
	}

	/**
	 * Check billing status for OpenAI provider.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_REST_Request $request Request instance.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_check_billing( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$provider = $request->get_param( 'provider' );
		
		if ( empty( $provider ) ) {
			$settings = $this->getSettings();
			$provider = $settings ? $settings->get( 'provider', '' ) : '';
		}

		if ( 'openai' !== $provider ) {
			return new \WP_Error(
				'\FPML_unsupported_provider',
				__( 'Il controllo billing è disponibile solo per OpenAI al momento.', 'fp-multilanguage' ),
				array( 'status' => 400 )
			);
		}

		$translator = $this->get_translator_by_slug( $provider );

		if ( is_wp_error( $translator ) ) {
			$translator->add_data( array( 'status' => 400 ), $translator->get_error_code() );
			return $translator;
		}

		if ( ! method_exists( $translator, 'verify_billing_status' ) ) {
			return new \WP_Error(
				'\FPML_method_not_supported',
				__( 'Il provider selezionato non supporta il controllo billing.', 'fp-multilanguage' ),
				array( 'status' => 400 )
			);
		}

		$result = $translator->verify_billing_status();

		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ), $result->get_error_code() );
			return $result;
		}

		return rest_ensure_response(
			array(
				'success'  => true,
				'provider' => $provider,
				'billing'  => $result,
			)
		);
	}

	/**
	 * Refresh the REST API nonce.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_REST_Request $request Request instance.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_refresh_nonce( \WP_REST_Request $request ): \WP_REST_Response { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$new_nonce = wp_create_nonce( 'wp_rest' );

		return rest_ensure_response(
			array(
				'success' => true,
				'nonce'   => $new_nonce,
			)
		);
	}

	/**
	 * Get default provider from settings.
	 *
	 * @since 0.10.0
	 *
	 * @return string Provider slug or empty string.
	 */
	protected function get_default_provider(): string {
		if ( class_exists( '\FPML_Settings' ) ) {
			$settings = function_exists( 'fpml_get_options' ) ? fpml_get_options() : \FPML_Settings::instance();
			return $settings ? $settings->get( 'provider', '' ) : '';
		}
		return '';
	}

	/**
	 * Get translator instance by provider slug.
	 *
	 * @since 0.10.0
	 *
	 * @param string $provider Provider slug.
	 *
	 * @return \FPML_Translator_Interface|\WP_Error
	 */
	protected function get_translator_by_slug( string $provider ) {
		$settings = class_exists( '\FPML_Settings' ) ? \FPML_Settings::instance() : null;
		if ( ! $settings ) {
			return new \WP_Error( '\FPML_settings_error', __( 'Impossibile caricare le impostazioni.', 'fp-multilanguage' ) );
		}

		switch ( $provider ) {
		case 'openai':
			$api_key = $settings->get( 'openai_api_key', '' );
			if ( empty( $api_key ) ) {
				return new \WP_Error( '\FPML_no_api_key', __( 'API key OpenAI mancante.', 'fp-multilanguage' ) );
			}
			return new \FPML_Provider_OpenAI();

		case 'google':
			$api_key = $settings->get( 'google_api_key', '' );
			if ( empty( $api_key ) ) {
				return new \WP_Error( '\FPML_no_api_key', __( 'API key Google mancante.', 'fp-multilanguage' ) );
			}
			return new \FPML_Provider_Google();

		default:
			return new \WP_Error( '\FPML_invalid_provider', __( 'Provider non valido.', 'fp-multilanguage' ) );
		}
	}
}
















