<?php
/**
 * Queue Batch Manager - Handles batch operations for queue jobs.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Queue;

use FP\Multilanguage\Core\HostingDetector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles batch operations for queue jobs.
 *
 * @since 0.10.0
 */
class QueueBatchManager {
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
	 * Claim a batch of jobs for processing.
	 *
	 * @since 0.2.0
	 *
	 * @param int $limit Limit (0 = auto-detect).
	 * @return array Array of job objects.
	 */
	public function claim_batch( int $limit = 0 ): array {
		global $wpdb;

		// Auto-detect batch size se non specificato
		if ( 0 === $limit || null === $limit ) {
			$limit = $this->get_adaptive_batch_size();
		}

		$limit = max( 1, absint( $limit ) );
		$table = $this->table;

		$states       = array( 'pending', 'outdated' );
		$placeholders = implode( ',', array_fill( 0, count( $states ), '%s' ) );

		// Priorità: post_content, post_title, post_excerpt prima dei meta fields
		// Usa FIELD() per dare priorità ai campi principali
		$sql      = "SELECT * FROM {$table} WHERE state IN ({$placeholders}) ORDER BY 
			FIELD(field, 'post_content', 'post_title', 'post_excerpt') DESC,
			created_at ASC LIMIT %d";
		$prepared = array_merge( $states, array( $limit ) );

		$sql   = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $prepared ) );
		$items = $wpdb->get_results( $sql );

		if ( empty( $items ) ) {
			return array();
		}

		$now = current_time( 'mysql', true );

		foreach ( $items as $item ) {
			$wpdb->update(
				$table,
				array(
					'state'      => 'translating',
					'updated_at' => $now,
				),
				array( 'id' => (int) $item->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			$item->state      = 'translating';
			$item->updated_at = $now;
		}

		// Invalidate state counts cache when jobs are claimed
		if ( ! empty( $items ) ) {
			wp_cache_delete( 'fpml_queue_state_counts', 'fpml_queue' );
		}

		return $items;
	}

	/**
	 * Get adaptive batch size based on hosting capabilities.
	 *
	 * @since 0.5.0
	 *
	 * @return int
	 */
	protected function get_adaptive_batch_size(): int {
		// Usa HostingDetector se disponibile
		if ( class_exists( HostingDetector::class ) ) {
			$detector = HostingDetector::instance();
			$size     = $detector->get_recommended_batch_size();

			/**
			 * Filters the adaptive batch size.
			 *
			 * @since 0.5.0
			 *
			 * @param int    $size            Recommended batch size.
			 * @param string $hosting_type    Hosting type.
			 * @param int    $performance_score Performance score.
			 */
			return apply_filters(
				'fpml_queue_adaptive_batch_size',
				$size,
				$detector->get_hosting_type(),
				$detector->get_performance_score()
			);
		}

		// Fallback conservativo
		return 5;
	}
}
















