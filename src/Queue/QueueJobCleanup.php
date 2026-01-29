<?php
/**
 * Queue Job Cleanup - Handles cleanup operations for queue jobs.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Queue;

use FP\Multilanguage\Core\ContainerAwareTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles cleanup operations for queue jobs.
 *
 * @since 0.10.0
 */
class QueueJobCleanup {
	use ContainerAwareTrait;
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
	 * Clean up old jobs from the queue.
	 *
	 * @since 0.3.1
	 *
	 * @param array  $states Queue states to target.
	 * @param int    $days Retention window in days.
	 * @param string $column Date column used for comparison. Default 'updated_at'.
	 * @return int|\WP_Error Number of deleted jobs or WP_Error on failure.
	 */
	public function cleanup_old_jobs( array $states, int $days, string $column = 'updated_at' ): int|\WP_Error {
		global $wpdb;

		$days   = (int) $days;
		$column = in_array( $column, array( 'created_at', 'updated_at' ), true ) ? $column : 'updated_at';
		$states = array_filter( array_map( 'sanitize_key', (array) $states ) );

		if ( $days <= 0 || empty( $states ) ) {
			return 0;
		}

		$table        = $this->table;
		$now          = current_time( 'timestamp', true );
		$cutoff       = gmdate( 'Y-m-d H:i:s', $now - ( $days * DAY_IN_SECONDS ) );
		$placeholders = implode( ',', array_fill( 0, count( $states ), '%s' ) );

		/**
		 * Filters the batch size used when deleting queue jobs during cleanup.
		 *
		 * @since 0.3.1
		 *
		 * @param int    $batch_size Suggested batch size.
		 * @param array  $states     States targeted for cleanup.
		 * @param int    $days       Retention window in days.
		 * @param string $column     Date column used for comparison.
		 */
		$batch_size = (int) apply_filters( '\FPML_queue_cleanup_batch_size', 500, $states, $days, $column );

		$batch_size = max( 1, $batch_size );
		$total      = 0;

		do {
			$prepare_args = array_merge(
				array( "DELETE FROM {$table} WHERE state IN ({$placeholders}) AND {$column} < %s LIMIT %d" ),
				$states,
				array( $cutoff, $batch_size )
			);

			$sql = call_user_func_array( array( $wpdb, 'prepare' ), $prepare_args );

			$deleted = $wpdb->query( $sql );

			if ( false === $deleted ) {
				$error = $wpdb->last_error ? $wpdb->last_error : __( 'Errore database sconosciuto.', 'fp-multilanguage' );

				$container = $this->getContainer();
				$logger = $container && $container->has( 'logger' ) ? $container->get( 'logger' ) : ( class_exists( '\FPML_Logger' ) ? \FPML_fpml_get_logger() : null );
				if ( $logger ) {
					$logger->log(
						'error',
						__( 'Pulizia coda non riuscita a causa di un errore del database.', 'fp-multilanguage' ),
						array(
							'states'  => implode( ',', $states ),
							'days'    => $days,
							'column'  => $column,
							'message' => $error,
						)
					);
				} else {
					\FP\Multilanguage\Logger::error( 'Queue cleanup failed', array( 'error' => $error ) );
				}

				return new \WP_Error(
					'\FPML_queue_cleanup_failed',
					__( 'Impossibile completare la pulizia della coda.', 'fp-multilanguage' ),
					array(
						'states' => $states,
						'days'   => $days,
						'column' => $column,
						'error'  => $error,
					)
				);
			}

			$total += (int) $deleted;
		} while ( $deleted >= $batch_size );

		/**
		 * Fires after the queue cleanup finishes.
		 *
		 * @since 0.3.1
		 *
		 * @param array  $states States targeted by the cleanup.
		 * @param int    $days   Retention window in days.
		 * @param int    $total  Total deleted rows.
		 * @param string $column Date column used for comparison.
		 */
		do_action( '\FPML_queue_after_cleanup', $states, $days, $total, $column );

		return $total;
	}

	/**
	 * Count jobs matching the cleanup criteria.
	 *
	 * @since 0.3.1
	 *
	 * @param array  $states Queue states to target.
	 * @param int    $days   Retention window in days.
	 * @param string $column Date column used for comparison.
	 *
	 * @return int|\WP_Error Number of matching jobs or WP_Error on failure.
	 */
	public function count_old_jobs( $states, $days, $column = 'updated_at' ) {
		global $wpdb;

		$days   = (int) $days;
		$column = in_array( $column, array( 'created_at', 'updated_at' ), true ) ? $column : 'updated_at';
		$states = array_filter( array_map( 'sanitize_key', (array) $states ) );

		if ( $days <= 0 || empty( $states ) ) {
			return 0;
		}

		$table        = $this->table;
		$now          = current_time( 'timestamp', true );
		$cutoff       = gmdate( 'Y-m-d H:i:s', $now - ( $days * DAY_IN_SECONDS ) );
		$placeholders = implode( ',', array_fill( 0, count( $states ), '%s' ) );

		$prepare_args = array_merge(
			array( "SELECT COUNT(*) FROM {$table} WHERE state IN ({$placeholders}) AND {$column} < %s" ),
			$states,
			array( $cutoff )
		);

		$sql   = call_user_func_array( array( $wpdb, 'prepare' ), $prepare_args );
		$count = $wpdb->get_var( $sql );

		if ( null === $count && $wpdb->last_error ) {
			return new \WP_Error(
				'\FPML_queue_count_failed',
				__( 'Impossibile contare i job per la pulizia della coda.', 'fp-multilanguage' ),
				array(
					'states' => $states,
					'days'   => $days,
					'column' => $column,
					'error'  => $wpdb->last_error,
				)
			);
		}

		return (int) $count;
	}

	/**
	 * Get oldest job for specified states.
	 *
	 * @since 0.3.0
	 *
	 * @param array  $states States list.
	 * @param string $column Date column. Default 'created_at'.
	 * @return object|null Job object or null if not found.
	 */
	public function get_oldest_job_for_states( array $states, string $column = 'created_at' ): ?object {
		global $wpdb;

		$states = array_filter( array_map( 'sanitize_key', (array) $states ) );
		$column = in_array( $column, array( 'created_at', 'updated_at' ), true ) ? $column : 'created_at';

		if ( empty( $states ) ) {
			return null;
		}

		$table        = $this->table;
		$placeholders = implode( ',', array_fill( 0, count( $states ), '%s' ) );

		$prepare_args = array_merge(
			array( "SELECT * FROM {$table} WHERE state IN ({$placeholders}) ORDER BY {$column} ASC LIMIT 1" ),
			$states
		);

		$sql = call_user_func_array( array( $wpdb, 'prepare' ), $prepare_args );

		$job = $wpdb->get_row( $sql );

		return $job ? $job : null;
	}

	/**
	 * Purge completed jobs older than a threshold.
	 *
	 * @since 0.2.0
	 *
	 * @param int $days Days threshold. Default 30.
	 * @return int Number of deleted rows.
	 */
	public function purge_completed( int $days = 30 ): int {
		global $wpdb;

		$days = max( 1, absint( $days ) );

		$threshold = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) );

		// WordPress 6.2+ supports %i identifier for table names
		global $wp_version;
		if ( version_compare( $wp_version, '6.2', '>=' ) ) {
			$sql = $wpdb->prepare( "DELETE FROM %i WHERE state IN ('done','skipped') AND updated_at < %s", $this->table, $threshold );
		} else {
			$table = esc_sql( $this->table );
			$sql = $wpdb->prepare( "DELETE FROM {$table} WHERE state IN ('done','skipped') AND updated_at < %s", $threshold );
		}

		return (int) $wpdb->query( $sql );
	}
}
















