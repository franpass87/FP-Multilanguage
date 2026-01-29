<?php
/**
 * REST System Handler - Handles system-related REST endpoints (health, stats, logs).
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Rest\Handlers;

use FP\Multilanguage\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles system-related REST endpoints.
 *
 * @since 0.10.0
 */
class SystemHandler {
	/**
	 * Health check endpoint for monitoring.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_REST_Request $request Request instance.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_health_check( \WP_REST_Request $request ): \WP_REST_Response { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$queue = fpml_get_queue();
		$processor = \FPML_fpml_get_processor();
		$plugin = function_exists( 'fpml_get_plugin' ) ? fpml_get_plugin() : \FPML_Plugin::instance();

		global $wpdb;
		$table = $wpdb->prefix . '\FPML_queue';

		// Check database accessibility
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		// WordPress 6.2+ supports %i identifier for table names
		global $wp_version;
		if ( version_compare( $wp_version, '6.2', '>=' ) ) {
			$db_check = $wpdb->query( $wpdb->prepare( 'SELECT 1 FROM %i LIMIT 1', $table ) );
		} else {
			// Fallback for WordPress < 6.2
			$table_escaped = esc_sql( $table );
			$db_check = $wpdb->query( "SELECT 1 FROM {$table_escaped} LIMIT 1" );
		}

		$health = array(
			'status' => 'ok',
			'version' => defined( '\FPML_PLUGIN_VERSION' ) ? \FPML_PLUGIN_VERSION : 'unknown',
			'checks' => array(
				'database' => array(
					'accessible' => false !== $db_check,
				),
				'queue' => array(
					'accessible' => true,
					'locked' => $processor->is_locked(),
					'pending_jobs' => $queue->count_by_state( 'pending' ),
					'error_jobs' => $queue->count_by_state( 'error' ),
				),
				'provider' => array(
					'configured' => ! is_wp_error( $processor->get_translator_instance() ),
				),
				'assisted_mode' => $plugin->is_assisted_mode(),
			),
			'timestamp' => current_time( 'mysql', true ),
		);

		// Determine overall status
		$pending = isset( $health['checks']['queue']['pending_jobs'] ) ? (int) $health['checks']['queue']['pending_jobs'] : 0;
		$errors = isset( $health['checks']['queue']['error_jobs'] ) ? (int) $health['checks']['queue']['error_jobs'] : 0;

		if ( $pending > 10000 || $errors > 100 ) {
			$health['status'] = 'warning';
		}

		if ( ! $health['checks']['database']['accessible'] || ! $health['checks']['provider']['configured'] ) {
			$health['status'] = 'error';
		}

		return rest_ensure_response( $health );
	}

	/**
	 * Handle GET /stats endpoint.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_get_stats( \WP_REST_Request $request ): \WP_REST_Response { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		global $wpdb;

		$stats = array(
			'total_posts'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish'" ),
			'translated_posts'   => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_fpml_pair_id'" ),
			'pending_translations' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE pm.meta_key = '_fpml_translation_status' AND pm.meta_value = 'pending'" ),
			'queue_size'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}FPML_queue WHERE status = 'pending'" ),
		);

		return rest_ensure_response( $stats );
	}

	/**
	 * Handle GET /logs endpoint.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_get_logs( \WP_REST_Request $request ): \WP_REST_Response {
		$args = array(
			'level'  => $request->get_param( 'level' ),
			'limit'  => $request->get_param( 'limit' ) ?: 50,
			'offset' => $request->get_param( 'offset' ) ?: 0,
		);

		$logs = Logger::get_logs( $args );

		return rest_ensure_response( array(
			'logs' => $logs,
		) );
	}
}
















