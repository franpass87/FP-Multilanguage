<?php
/**
 * Queue Table Manager - Handles database table installation and management.
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
 * Manages the queue database table.
 *
 * @since 0.10.0
 */
class QueueTableManager {
	/**
	 * Schema version for the queue table.
	 *
	 * @since 0.3.1
	 */
	const SCHEMA_VERSION = '3';

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
	 * Ensure global $wpdb has the custom table registered for caching purposes.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function register_table_name(): void {
		global $wpdb;

		if ( ! in_array( 'FPML_queue', $wpdb->tables, true ) ) {
			$wpdb->tables[] = 'FPML_queue';
		}

		$wpdb->FPML_queue = $this->table;
	}

	/**
	 * Get the fully qualified table name.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_table(): string {
		return $this->table;
	}

	/**
	 * Install database table using dbDelta.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function install(): void {
		global $wpdb;

		// Check if upgrade.php exists before requiring it
		$upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';
		if ( file_exists( $upgrade_file ) ) {
			require_once $upgrade_file;
		}

		// Check if dbDelta function is available
		if ( ! function_exists( 'dbDelta' ) ) {
			// Cannot create table without dbDelta
			return;
		}

		$charset_collate = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';
		$table           = $this->get_table();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			object_type varchar(32) NOT NULL,
			object_id bigint(20) unsigned NOT NULL,
			field varchar(191) NOT NULL,
			hash_source varchar(64) NOT NULL DEFAULT '',
			state varchar(20) NOT NULL DEFAULT 'pending',
			retries smallint(5) unsigned NOT NULL DEFAULT 0,
			last_error text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY object_lookup (object_type, object_id),
			KEY state_lookup (state),
			KEY state_updated_lookup (state, updated_at),
			KEY hash_lookup (hash_source),
			KEY created_lookup (created_at),
			KEY state_created (state, created_at),
			KEY retry_lookup (state, retries, updated_at),
			UNIQUE KEY object_field (object_type, object_id, field)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( '\FPML_queue_schema_version', self::SCHEMA_VERSION, false );
	}

	/**
	 * Ensure the database schema is up to date.
	 *
	 * @since 0.3.1
	 *
	 * @return void
	 */
	public function maybe_upgrade(): void {
		$stored_version = get_option( '\FPML_queue_schema_version', '' );

		if ( version_compare( (string) $stored_version, self::SCHEMA_VERSION, '>=' ) ) {
			return;
		}

		$this->install();
	}
}
















