<?php
/**
 * Queue State Manager - Handles state management operations for queue jobs.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Queue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles state management operations for queue jobs.
 *
 * @since 0.10.0
 */
class QueueStateManager {
	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected string $table;

	/**
	 * Constructor.
	 *
	 * @param string $table Table name.
	 */
	public function __construct( string $table ) {
		$this->table = $table;
	}

	/**
	 * Update job state.
	 *
	 * @since 0.2.0
	 *
	 * @param int    $job_id Job ID.
	 * @param string $state New state.
	 * @param string $error Error message (optional).
	 * @return bool True if updated, false otherwise.
	 */
	public function update_state( int $job_id, string $state, string $error = '' ): bool {
		global $wpdb;

		$job_id = absint( $job_id );
		$state  = sanitize_key( $state );
		$error  = wp_kses_post( $error );

		if ( ! $job_id || empty( $state ) ) {
			return false;
		}

		$table = $this->table;
		$now   = current_time( 'mysql', true );

		$current = $wpdb->get_row(
			$wpdb->prepare( "SELECT retries FROM {$table} WHERE id = %d", $job_id )
		);

		$retries = $current ? (int) $current->retries : 0;

		if ( in_array( $state, array( 'pending', 'done' ), true ) ) {
			$retries = 0;
		} elseif ( 'error' === $state ) {
			$retries++; // count failures.
		}

		$data = array(
			'state'      => $state,
			'updated_at' => $now,
			'retries'    => $retries,
		);

		if ( ! empty( $error ) ) {
			$data['last_error'] = $error;
		} elseif ( 'pending' === $state ) {
			$data['last_error'] = '';
		}

		$formats = array( '%s', '%s', '%d' );

		if ( array_key_exists( 'last_error', $data ) ) {
			$formats[] = '%s';
		}

		$updated = (bool) $wpdb->update(
			$table,
			$data,
			array( 'id' => $job_id ),
			$formats,
			array( '%d' )
		);

		// Invalidate state counts cache when state changes
		if ( $updated ) {
			wp_cache_delete( 'fpml_queue_state_counts', 'fpml_queue' );
		}

		return $updated;
	}

	/**
	 * Reset retries counter manually.
	 *
	 * @since 0.2.0
	 *
	 * @param int $job_id Job ID.
	 * @return bool True if updated, false otherwise.
	 */
	public function reset_retries( int $job_id ): bool {
		global $wpdb;

		$job_id = absint( $job_id );

		if ( ! $job_id ) {
			return false;
		}

		return (bool) $wpdb->update(
			$this->table,
			array( 'retries' => 0 ),
			array( 'id' => $job_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Retrieve jobs by state.
	 *
	 * @since 0.2.0
	 *
	 * @param array $states States list.
	 * @param int   $limit Limit.
	 * @return array Array of job objects.
	 */
	public function get_by_state( array $states, int $limit = 50 ): array {
		global $wpdb;

		$states = array_filter( array_map( 'sanitize_key', (array) $states ) );
		$limit  = max( 1, absint( $limit ) );

		if ( empty( $states ) ) {
			return array();
		}

		$table        = $this->table;
		$placeholders = implode( ',', array_fill( 0, count( $states ), '%s' ) );
		$sql          = "SELECT * FROM {$table} WHERE state IN ({$placeholders}) ORDER BY updated_at DESC LIMIT %d";
		$prepared     = array_merge( $states, array( $limit ) );

		$sql = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $prepared ) );

		return $wpdb->get_results( $sql );
	}

	/**
	 * Retrieve counts grouped by state.
	 * Cached for 2 minutes to reduce database load.
	 *
	 * @since 0.2.0
	 * @since 0.10.0 Optimized with caching
	 *
	 * @return array<string,int> Array with state as key and count as value.
	 */
	public function get_state_counts(): array {
		// Cache state counts for 2 minutes to reduce database load
		$cache_key = 'fpml_queue_state_counts';
		$cached = wp_cache_get( $cache_key, 'fpml_queue' );
		if ( false !== $cached ) {
			return (array) $cached;
		}

		global $wpdb;

		// WordPress 6.2+ supports %i identifier for table names
		global $wp_version;
		if ( version_compare( $wp_version, '6.2', '>=' ) ) {
			$sql = $wpdb->prepare( 'SELECT state, COUNT(*) AS total FROM %i GROUP BY state', $this->table );
		} else {
			// Fallback for older WordPress versions - escape table name
			$table = esc_sql( $this->table );
			$sql = "SELECT state, COUNT(*) AS total FROM {$table} GROUP BY state";
		}

		$results = $wpdb->get_results( $sql );

		$counts = array();

		foreach ( (array) $results as $row ) {
			if ( isset( $row->state ) ) {
				$counts[ $row->state ] = isset( $row->total ) ? (int) $row->total : 0;
			}
		}

		// Cache result for 2 minutes
		wp_cache_set( $cache_key, $counts, 'fpml_queue', 2 * MINUTE_IN_SECONDS );

		return $counts;
	}

	/**
	 * Reset specific states back to pending.
	 *
	 * @since 0.2.0
	 *
	 * @param array $states States to reset.
	 *
	 * @return int Updated rows.
	 */
	public function reset_states( $states = array( 'translating' ) ): int {
		global $wpdb;

		$states = array_filter( array_map( 'sanitize_key', (array) $states ) );

		if ( empty( $states ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $states ), '%s' ) );

		// WordPress 6.2+ supports %i identifier for table names
		global $wp_version;
		if ( version_compare( $wp_version, '6.2', '>=' ) ) {
			$sql = "UPDATE %i SET state = 'pending', retries = 0, last_error = '', updated_at = %s WHERE state IN ({$placeholders})";
			$params = array_merge( array( $this->table, current_time( 'mysql', true ) ), $states );
		} else {
			$table = esc_sql( $this->table );
			$sql = "UPDATE {$table} SET state = 'pending', retries = 0, last_error = '', updated_at = %s WHERE state IN ({$placeholders})";
			$params = array_merge( array( current_time( 'mysql', true ) ), $states );
		}

		$prepared = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $params ) );

		return (int) $wpdb->query( $prepared );
	}

	/**
	 * Fetch jobs for a given set of states.
	 *
	 * @since 0.2.0
	 *
	 * @param array $states States to include.
	 * @param int   $limit  Batch size.
	 * @param int   $offset Offset for pagination.
	 *
	 * @return array
	 */
	public function get_jobs_for_states( $states, $limit = 50, $offset = 0 ): array {
		global $wpdb;

		$states = array_filter( array_map( 'sanitize_key', (array) $states ) );
		$limit  = max( 1, absint( $limit ) );
		$offset = max( 0, absint( $offset ) );

		if ( empty( $states ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $states ), '%s' ) );

		// WordPress 6.2+ supports %i identifier for table names
		global $wp_version;
		if ( version_compare( $wp_version, '6.2', '>=' ) ) {
			$sql = "SELECT * FROM %i WHERE state IN ({$placeholders}) ORDER BY id ASC LIMIT %d OFFSET %d";
			$params = array_merge( array( $this->table ), $states, array( $limit, $offset ) );
		} else {
			$table = esc_sql( $this->table );
			$sql = "SELECT * FROM {$table} WHERE state IN ({$placeholders}) ORDER BY id ASC LIMIT %d OFFSET %d";
			$params = array_merge( $states, array( $limit, $offset ) );
		}

		$sql = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $params ) );

		return $wpdb->get_results( $sql );
	}
}
















