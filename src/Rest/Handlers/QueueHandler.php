<?php
/**
 * REST Queue Handler - Handles queue-related REST endpoints.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Rest\Handlers;

use FP\Multilanguage\Core\ContainerAwareTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles queue-related REST endpoints.
 *
 * @since 0.10.0
 */
class QueueHandler {
	use ContainerAwareTrait;
	/**
	 * Process a queue batch via REST.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_REST_Request $request Request instance.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_run_queue( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$plugin = class_exists( '\FPML_Plugin' ) ? \FPML_Plugin::instance() : null;
		if ( ! $plugin ) {
			return new \WP_Error(
				'\FPML_plugin_error',
				__( 'Impossibile caricare il plugin.', 'fp-multilanguage' ),
				array( 'status' => 500 )
			);
		}

		if ( $plugin->is_assisted_mode() ) {
			return new \WP_Error(
				'\FPML_assisted_mode',
				__( 'Modalità assistita attiva: la coda interna è disabilitata.', 'fp-multilanguage' ),
				array( 'status' => 409 )
			);
		}

		$processor = class_exists( '\FPML_Processor' ) ? \FPML_fpml_get_processor() : null;
		if ( ! $processor ) {
			return new \WP_Error(
				'\FPML_processor_error',
				__( 'Impossibile caricare il processore.', 'fp-multilanguage' ),
				array( 'status' => 500 )
			);
		}
		$result = $processor->run_queue();

		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 409 ), $result->get_error_code() );

			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'summary' => $result,
			)
		);
	}

	/**
	 * Execute a manual cleanup using the configured retention settings.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_REST_Request $request Request instance.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_cleanup( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$plugin = class_exists( '\FPML_Plugin' ) ? \FPML_Plugin::instance() : null;
		if ( ! $plugin ) {
			return new \WP_Error(
				'\FPML_plugin_error',
				__( 'Impossibile caricare il plugin.', 'fp-multilanguage' ),
				array( 'status' => 500 )
			);
		}

		if ( $plugin->is_assisted_mode() ) {
			return new \WP_Error(
				'\FPML_assisted_mode',
				__( 'Modalità assistita attiva: la coda interna è disabilitata.', 'fp-multilanguage' ),
				array( 'status' => 409 )
			);
		}

		$container = $this->getContainer();
		if ( $container && $container->has( 'options' ) ) {
			$settings = $container->get( 'options' );
		} else {
			$settings = class_exists( '\FPML_Settings' ) ? \FPML_Settings::instance() : null;
		}
		$days = $settings ? (int) $settings->get( 'queue_retention_days', 0 ) : 0;

		if ( $days <= 0 ) {
			return new \WP_Error(
				'\FPML_cleanup_disabled',
				__( 'Configura prima la retention della coda dalle impostazioni.', 'fp-multilanguage' ),
				array( 'status' => 400 )
			);
		}

		$states = $plugin->get_queue_cleanup_states();

		if ( empty( $states ) ) {
			return new \WP_Error(
				'\FPML_cleanup_states_empty',
				__( 'Nessuno stato valido configurato per la pulizia della coda.', 'fp-multilanguage' ),
				array( 'status' => 400 )
			);
		}

		$queue = class_exists( '\FPML_Queue' ) ? fpml_get_queue() : null;
		if ( ! $queue ) {
			return new \WP_Error(
				'\FPML_queue_error',
				__( 'Impossibile caricare la coda.', 'fp-multilanguage' ),
				array( 'status' => 500 )
			);
		}
		$deleted = $queue->cleanup_old_jobs( $states, $days, 'updated_at' );

		if ( is_wp_error( $deleted ) ) {
			$deleted->add_data( array( 'status' => 500 ), $deleted->get_error_code() );

			return $deleted;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'deleted' => (int) $deleted,
				'states'  => $states,
				'days'    => $days,
			)
		);
	}
}
















