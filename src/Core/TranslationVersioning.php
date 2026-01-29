<?php
/**
 * Translation Versioning - Backup and rollback translations.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.1
 * @since 0.10.0 Refactored to use modular components.
 */

namespace FP\Multilanguage\Core;

use FP\Multilanguage\Core\Versioning\TableInstaller;
use FP\Multilanguage\Core\Versioning\VersionSaver;
use FP\Multilanguage\Core\Versioning\VersionRetriever;
use FP\Multilanguage\Core\Versioning\RollbackManager;
use FP\Multilanguage\Core\Versioning\CleanupManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Version control for translations to allow rollback.
 *
 * @since 0.4.1
 * @since 0.10.0 Refactored to use modular components.
 */
class TranslationVersioning {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table;

	/**
	 * Table installer.
	 *
	 * @since 0.10.0
	 *
	 * @var TableInstaller
	 */
	protected TableInstaller $table_installer;

	/**
	 * Version saver.
	 *
	 * @since 0.10.0
	 *
	 * @var VersionSaver
	 */
	protected VersionSaver $version_saver;

	/**
	 * Version retriever.
	 *
	 * @since 0.10.0
	 *
	 * @var VersionRetriever
	 */
	protected VersionRetriever $version_retriever;

	/**
	 * Rollback manager.
	 *
	 * @since 0.10.0
	 *
	 * @var RollbackManager
	 */
	protected RollbackManager $rollback_manager;

	/**
	 * Cleanup manager.
	 *
	 * @since 0.10.0
	 *
	 * @var CleanupManager
	 */
	protected CleanupManager $cleanup_manager;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.1
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 0.10.0
	 */
	protected function __construct() {
		global $wpdb;

		$this->table = $wpdb->prefix . 'fpml_translation_versions';

		// Initialize modules
		$this->table_installer = new TableInstaller( $this->table );
		$this->version_saver = new VersionSaver( $this->table );
		$this->version_retriever = new VersionRetriever( $this->table );
		$this->rollback_manager = new RollbackManager();
		$this->cleanup_manager = new CleanupManager( $this->table );

		// Install table if needed
		$this->table_installer->maybe_install_table();

		// Hook into translation save
		add_action( '\FPML_post_translated', array( $this, 'save_post_version' ), 10, 4 );
		add_action( '\FPML_term_translated', array( $this, 'save_term_version' ), 10, 3 );
	}

	/**
	 * Install versions table.
	 *
	 * @since 0.4.1
	 * @since 0.10.0 Delegates to TableInstaller.
	 *
	 * @return void
	 */
	public function install_table(): void {
		$this->table_installer->install_table();
	}

	/**
	 * Save post translation version.
	 *
	 * @since 0.4.1
	 * @since 0.10.0 Delegates to VersionSaver.
	 *
	 * @param int    $source_post_id Source post ID.
	 * @param int    $target_post_id Target post ID.
	 * @param string $field          Field name.
	 * @param array  $data           Translation data.
	 * @return void
	 */
	public function save_post_version( int $source_post_id, int $target_post_id, string $field, array $data ): void {
		$this->version_saver->save_post_version( $source_post_id, $target_post_id, $field, $data );
	}

	/**
	 * Save term translation version.
	 *
	 * @since 0.4.1
	 * @since 0.10.0 Delegates to VersionSaver.
	 *
	 * @param int    $source_term_id Source term ID.
	 * @param int    $target_term_id Target term ID.
	 * @param array  $data           Translation data.
	 * @return void
	 */
	public function save_term_version( int $source_term_id, int $target_term_id, array $data ): void {
		$this->version_saver->save_term_version( $source_term_id, $target_term_id, $data );
	}

	/**
	 * Save a version entry.
	 *
	 * @since 0.4.1
	 * @since 0.10.0 Delegates to VersionSaver.
	 *
	 * @param string $object_type Object type (post, term, menu, etc).
	 * @param int    $object_id   Object ID.
	 * @param string $field       Field name.
	 * @param string $old_value   Previous value.
	 * @param string $new_value   New value.
	 * @param string $provider    Translation provider.
	 * @return int|false Insert ID or false on failure.
	 */
	public function save_version( string $object_type, int $object_id, string $field, string $old_value, string $new_value, string $provider = '' ): int|false {
		return $this->version_saver->save_version( $object_type, $object_id, $field, $old_value, $new_value, $provider );
	}

	/**
	 * Get version history for an object.
	 *
	 * @since 0.4.1
	 * @since 0.10.0 Delegates to VersionRetriever.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id   Object ID.
	 * @param string $field       Optional. Specific field to filter.
	 * @param int    $limit       Maximum versions to return.
	 * @return array
	 */
	public function get_versions( string $object_type, int $object_id, string $field = '', int $limit = 20 ): array {
		return $this->version_retriever->get_versions( $object_type, $object_id, $field, $limit );
	}

	/**
	 * Rollback to a specific version.
	 *
	 * @since 0.4.1
	 * @since 0.10.0 Delegates to RollbackManager.
	 *
	 * @param int $version_id Version ID to rollback to.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function rollback( int $version_id ): bool|\WP_Error {
		$version = $this->version_retriever->get_version( $version_id );

		if ( ! $version ) {
			return new \WP_Error( 'invalid_version', __( 'Versione non trovata.', 'fp-multilanguage' ) );
		}

		return $this->rollback_manager->rollback( $version );
	}

	/**
	 * Cleanup old versions.
	 *
	 * @since 0.4.1
	 * @since 0.10.0 Delegates to CleanupManager.
	 *
	 * @param int $days           Days to retain (default 90).
	 * @param int $keep_per_field Minimum versions to keep per field (default 5).
	 * @return int Number of deleted rows.
	 */
	public function cleanup_old_versions( int $days = 90, int $keep_per_field = 5 ): int {
		return $this->cleanup_manager->cleanup_old_versions( $days, $keep_per_field );
	}
}
