<?php
/**
 * Queue Job Operations - Handles job operations (delete, mark outdated, etc.).
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
 * Handles job operations.
 *
 * @since 0.10.0
 */
class QueueJobOperations {
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
	 * Delete a job from the queue.
	 *
	 * @since 0.2.0
	 *
	 * @param int $job_id Job ID.
	 *
	 * @return bool
	 */
	public function delete( $job_id ): bool {
		global $wpdb;

		$job_id = absint( $job_id );

		if ( ! $job_id ) {
			return false;
		}

		$deleted = (bool) $wpdb->delete( $this->table, array( 'id' => $job_id ), array( '%d' ) );

		// Invalidate state counts cache when job is deleted
		if ( $deleted ) {
			wp_cache_delete( 'fpml_queue_state_counts', 'fpml_queue' );
		}

		return $deleted;
	}

	/**
	 * Bulk mark jobs for a specific object as outdated.
	 *
	 * @since 0.2.0
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id Object ID.
	 * @return void
	 */
	public function mark_outdated( string $object_type, int $object_id ): void {
		global $wpdb;

		$object_type = sanitize_key( $object_type );
		$object_id   = absint( $object_id );

		if ( empty( $object_type ) || ! $object_id ) {
			return;
		}

		$wpdb->update(
			$this->table,
			array(
				'state'      => 'outdated',
				'updated_at' => current_time( 'mysql', true ),
			),
			array(
				'object_type' => $object_type,
				'object_id'   => $object_id,
			),
			array( '%s', '%s' ),
			array( '%s', '%d' )
		);
	}

	/**
	 * Check if a specific post has pending translation jobs.
	 *
	 * @since 0.5.1
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool True if there are pending jobs for this post.
	 */
	public function has_pending_jobs( $post_id ): bool {
		global $wpdb;

		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return false;
		}

		$table = $this->table;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE object_type = 'post' AND object_id = %d AND state IN ('pending', 'translating', 'outdated')",
				$post_id
			)
		);

		return ( $count && (int) $count > 0 );
	}

	/**
	 * Count completed jobs for a specific object type and optional field.
	 *
	 * @since 0.3.0
	 *
	 * @param string      $object_type Object type slug.
	 * @param string|null $field       Optional field identifier.
	 *
	 * @return int
	 */
	public function count_completed_jobs( $object_type, $field = null ): int {
		global $wpdb;

		$object_type = sanitize_key( $object_type );

		if ( '' === $object_type ) {
			return 0;
		}

		$table = $this->table;

		if ( null === $field || '' === $field ) {
			$sql = $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE state = %s AND object_type = %s",
				'done',
				$object_type
			);
		} else {
			$field = sanitize_text_field( $field );

			$sql = $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE state = %s AND object_type = %s AND field = %s",
				'done',
				$object_type,
				$field
			);
		}

		$count = $wpdb->get_var( $sql );

		return $count ? (int) $count : 0;
	}
}
















