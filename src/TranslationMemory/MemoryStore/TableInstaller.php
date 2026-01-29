<?php
/**
 * Translation Memory Store Table Installer - Installs translation memory table.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\TranslationMemory\MemoryStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installs translation memory table.
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
	 * Maybe install table.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function maybe_install(): void {
		if ( get_option( 'fpml_tm_installed' ) ) {
			return;
		}

		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_text text NOT NULL,
			target_text text NOT NULL,
			source_lang varchar(10) NOT NULL DEFAULT 'it',
			target_lang varchar(10) NOT NULL DEFAULT 'en',
			provider varchar(50) NOT NULL,
			context varchar(100) DEFAULT 'general',
			created_at datetime NOT NULL,
			use_count int unsigned DEFAULT 1,
			quality_score tinyint unsigned DEFAULT NULL,
			PRIMARY KEY (id),
			KEY source_hash (source_lang, target_lang, source_text(100)),
			KEY context_lookup (context, source_lang, target_lang),
			FULLTEXT KEY source_search (source_text)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'fpml_tm_installed', '1', false );
	}
}















