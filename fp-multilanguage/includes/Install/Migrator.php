<?php
namespace FPMultilanguage\Install;

use FPMultilanguage\Services\Logger;

class Migrator {

	private const DB_VERSION_OPTION = 'fp_multilanguage_db_version';

	private const DB_VERSION = '1.0.0';

	private ?Logger $logger = null;

	public function set_logger( Logger $logger ): void {
		$this->logger = $logger;
	}

	public function maybe_migrate(): void {
		if ( ! function_exists( 'maybe_create_table' ) ) {
			if ( defined( 'ABSPATH' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}
		}

		if ( ! function_exists( 'maybe_create_table' ) ) {
			return;
		}

		global $wpdb;
		if ( ! isset( $wpdb ) || ! $wpdb instanceof \wpdb ) {
			return;
		}

		$charset = $wpdb->get_charset_collate();
		$table   = $wpdb->prefix . 'fp_multilanguage_strings';
		$sql     = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            string_key VARCHAR(64) NOT NULL,
            context VARCHAR(191) NULL,
            original LONGTEXT NOT NULL,
            translations LONGTEXT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY string_key (string_key)
        ) {$charset};";

		maybe_create_table( $table, $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	public function drop_tables(): void {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! $wpdb instanceof \wpdb ) {
			return;
		}

		$table = $wpdb->prefix . 'fp_multilanguage_strings';
				$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name constructed from prefix.
		delete_option( self::DB_VERSION_OPTION );
	}
}
