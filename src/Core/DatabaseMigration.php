<?php
/**
 * Database Migration System
 *
 * Gestisce le migrazioni dello schema database tra versioni del plugin.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.9.6
 */

namespace FP\Multilanguage\Core;

use FP\Multilanguage\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database Migration Handler
 *
 * @since 0.9.6
 */
class DatabaseMigration {
	/**
	 * Option key for database version.
	 *
	 * @var string
	 */
	const DB_VERSION_KEY = 'fpml_db_version';

	/**
	 * Current database schema version.
	 *
	 * @var string
	 */
	const CURRENT_VERSION = '0.9.6';

	/**
	 * Singleton instance.
	 *
	 * @var DatabaseMigration|null
	 */
	protected static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return DatabaseMigration
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		// Check and run migrations on admin_init
		add_action( 'admin_init', array( $this, 'check_and_migrate' ), 5 );
	}

	/**
	 * Check if migrations are needed and run them.
	 *
	 * @since 0.9.6
	 *
	 * @return void
	 */
	public function check_and_migrate() {
		$installed_version = get_option( self::DB_VERSION_KEY, '0.0.0' );

		if ( version_compare( $installed_version, self::CURRENT_VERSION, '<' ) ) {
			Logger::info(
				'Database migration started',
				array(
					'from' => $installed_version,
					'to'   => self::CURRENT_VERSION,
				)
			);

			$this->run_migrations( $installed_version );

			// Update database version
			update_option( self::DB_VERSION_KEY, self::CURRENT_VERSION, false );

			Logger::info(
				'Database migration completed',
				array(
					'from' => $installed_version,
					'to'   => self::CURRENT_VERSION,
				)
			);
		}
	}

	/**
	 * Run migrations from a specific version.
	 *
	 * @since 0.9.6
	 *
	 * @param string $from_version Version to migrate from.
	 * @return void
	 */
	protected function run_migrations( $from_version ) {
		// Migrations are run in order from oldest to newest
		// Add new migrations here as the plugin evolves

		// Example migration structure:
		// if ( version_compare( $from_version, '1.0.0', '<' ) ) {
		//     $this->migrate_to_1_0_0();
		// }

		// For now, we just ensure tables are up to date via dbDelta
		// which is already handled by Queue, TranslationVersioning, etc.
		// This migration system is ready for future schema changes

		// Force table upgrades if needed
		$this->maybe_upgrade_tables();
	}

	/**
	 * Force upgrade of database tables using dbDelta.
	 *
	 * @since 0.9.6
	 *
	 * @return void
	 */
	protected function maybe_upgrade_tables() {
		// Queue table upgrade
		$queue = fpml_get_queue();
		if ( $queue && method_exists( $queue, 'maybe_upgrade' ) ) {
			$queue->maybe_upgrade();
		}

		// Translation versioning table upgrade
		if ( class_exists( '\FP\Multilanguage\Core\TranslationVersioning' ) ) {
			$versioning = fpml_get_translation_versioning();
			if ( $versioning && method_exists( $versioning, 'maybe_upgrade' ) ) {
				$versioning->maybe_upgrade();
			}
		}

		// Translation memory table upgrade
		if ( class_exists( '\FP\Multilanguage\TranslationMemory\MemoryStore' ) ) {
			$memory = \FP\Multilanguage\TranslationMemory\MemoryStore::instance();
			if ( $memory && method_exists( $memory, 'maybe_upgrade' ) ) {
				$memory->maybe_upgrade();
			}
		}

		// Audit log table upgrade
		if ( class_exists( '\FP\Multilanguage\Security\AuditLog' ) ) {
			$audit = \FP\Multilanguage\Security\AuditLog::instance();
			if ( $audit && method_exists( $audit, 'maybe_upgrade' ) ) {
				$audit->maybe_upgrade();
			}
		}
	}

	/**
	 * Get current database version.
	 *
	 * @since 0.9.6
	 *
	 * @return string
	 */
	public function get_db_version() {
		return get_option( self::DB_VERSION_KEY, '0.0.0' );
	}

	/**
	 * Get target database version.
	 *
	 * @since 0.9.6
	 *
	 * @return string
	 */
	public function get_target_version() {
		return self::CURRENT_VERSION;
	}

	/**
	 * Force a specific migration (for admin use).
	 *
	 * @since 0.9.6
	 *
	 * @param string $version Version to migrate to.
	 * @return bool True on success, false on failure.
	 */
	public function force_migration( $version = null ) {
		if ( null === $version ) {
			$version = self::CURRENT_VERSION;
		}

		$installed_version = $this->get_db_version();
		$this->run_migrations( $installed_version );
		update_option( self::DB_VERSION_KEY, $version, false );

		return true;
	}
}







