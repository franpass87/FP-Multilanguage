<?php
/**
 * Translation Versioning Table Installer - Installs versioning table.
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
 * Installs translation versioning table.
 *
 * @since 0.10.0
 */
class TableInstaller {
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
	 * Install versions table.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function install_table(): void {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			object_type varchar(20) NOT NULL,
			object_id bigint(20) unsigned NOT NULL,
			field varchar(100) NOT NULL,
			old_value longtext NULL,
			new_value longtext NULL,
			translation_provider varchar(50) NULL,
			user_id bigint(20) unsigned NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY object_lookup (object_type, object_id),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'fpml_versioning_table_version', '1', false );
		delete_option( '\FPML_versioning_table_version' );
	}

	/**
	 * Maybe install table if not exists.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function maybe_install_table(): void {
		$version = get_option( 'fpml_versioning_table_version', '' );

		if ( '' === $version ) {
			$legacy_version = get_option( '\FPML_versioning_table_version', '' );
			if ( '' !== $legacy_version ) {
				update_option( 'fpml_versioning_table_version', $legacy_version, false );
				delete_option( '\FPML_versioning_table_version' );
				$version = $legacy_version;
			}
		}

		global $wpdb;
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table ) );

		if ( '' === $version || ! $table_exists ) {
			$this->install_table();
		}
	}
}















