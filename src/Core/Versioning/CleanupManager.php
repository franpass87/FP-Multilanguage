<?php
/**
 * Translation Versioning Cleanup Manager - Cleans up old versions.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Core\Versioning;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cleans up old translation versions.
 *
 * @since 0.10.0
 */
class CleanupManager {
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
	 * Cleanup old versions.
	 *
	 * @since 0.10.0
	 *
	 * @param int $days           Days to retain (default 90).
	 * @param int $keep_per_field Minimum versions to keep per field (default 5).
	 * @return int Number of deleted rows.
	 */
	public function cleanup_old_versions( int $days = 90, int $keep_per_field = 5 ): int {
		global $wpdb;

		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// First, delete versions older than cutoff, but keep at least $keep_per_field per field
		$sql = $wpdb->prepare(
			"DELETE v1 FROM {$this->table} v1
			INNER JOIN (
				SELECT object_type, object_id, field, id
				FROM {$this->table}
				WHERE created_at < %s
				ORDER BY created_at DESC
			) v2 ON v1.object_type = v2.object_type 
				AND v1.object_id = v2.object_id 
				AND v1.field = v2.field
			WHERE v1.created_at < %s
				AND v1.id NOT IN (
					SELECT id FROM (
						SELECT id FROM {$this->table}
						WHERE object_type = v1.object_type
							AND object_id = v1.object_id
							AND field = v1.field
							AND created_at < %s
						ORDER BY created_at DESC
						LIMIT %d
					) AS keep_versions
				)",
			$cutoff_date,
			$cutoff_date,
			$cutoff_date,
			$keep_per_field
		);

		$wpdb->query( $sql );

		return (int) $wpdb->rows_affected;
	}
}















