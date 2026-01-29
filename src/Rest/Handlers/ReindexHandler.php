<?php
/**
 * REST Reindex Handler - Handles reindex-related REST endpoints.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Rest\Handlers;

use FP\Multilanguage\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles reindex-related REST endpoints.
 *
 * @since 0.10.0
 */
class ReindexHandler {
	/**
	 * Trigger a full reindex via REST.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_REST_Request $request Request instance.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_reindex( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		// Aumenta il timeout per il reindex - può richiedere diversi minuti
		// se ci sono molti contenuti da processare
		if ( function_exists( 'set_time_limit' ) && false === strpos( ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // 5 minuti
		}

		$plugin = function_exists( 'fpml_get_plugin' ) ? fpml_get_plugin() : \FPML_Plugin::instance();

		if ( $plugin->is_assisted_mode() ) {
			return new \WP_Error(
				'\FPML_assisted_mode',
				__( 'Modalità assistita attiva: il reindex automatico è disabilitato.', 'fp-multilanguage' ),
				array( 'status' => 409 )
			);
		}

		$summary = $plugin->reindex_content();

		if ( is_wp_error( $summary ) ) {
			$summary->add_data( array( 'status' => 409 ), $summary->get_error_code() );

			return $summary;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'summary' => $summary,
			)
		);
	}

	/**
	 * Handle incremental reindex with progress tracking.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_REST_Request $request Request instance.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_reindex_batch( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$step = $request->get_param( 'step' );

		if ( function_exists( 'set_time_limit' ) && false === strpos( ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // 2 minuti per batch
		}

		$plugin = function_exists( 'fpml_get_plugin' ) ? fpml_get_plugin() : \FPML_Plugin::instance();

		if ( $plugin->is_assisted_mode() ) {
			return new \WP_Error(
				'\FPML_assisted_mode',
				__( 'Modalità assistita attiva: il reindex automatico è disabilitato.', 'fp-multilanguage' ),
				array( 'status' => 409 )
			);
		}

		$indexer = Container::get( 'content_indexer' );

		if ( ! $indexer ) {
			$indexer = function_exists( 'fpml_get_content_indexer' ) ? fpml_get_content_indexer() : ( function_exists( 'fpml_get_content_indexer' ) ? fpml_get_content_indexer() : \FPML_Content_Indexer::instance() );
		}

		$result = $indexer->reindex_batch( $step );

		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 500 ), $result->get_error_code() );
			return $result;
		}

		return rest_ensure_response( $result );
	}
}
















