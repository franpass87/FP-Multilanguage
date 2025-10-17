<?php
/**
 * Settings Migration - Backup and restore settings during plugin updates.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle settings backup and migration during plugin updates.
 *
 * @since 0.4.1
 */
class FPML_Settings_Migration {
	/**
	 * Singleton instance.
	 *
	 * @var FPML_Settings_Migration|null
	 */
	protected static $instance = null;

	/**
	 * Backup option key.
	 */
	const BACKUP_OPTION_KEY = 'fpml_settings_backup';

	/**
	 * Migration version option key.
	 */
	const MIGRATION_VERSION_KEY = 'fpml_settings_migration_version';

	/**
	 * Current migration version.
	 */
	const CURRENT_MIGRATION_VERSION = '0.4.1';

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.1
	 *
	 * @return FPML_Settings_Migration
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
		// Hook into plugin activation to backup settings
		add_action( 'fpml_before_activation', array( $this, 'backup_settings' ) );
		
		// Hook into plugin initialization to restore settings
		add_action( 'fpml_after_initialization', array( $this, 'maybe_restore_settings' ) );
		
		// Hook into settings save to update migration version
		add_action( 'update_option_fpml_settings', array( $this, 'update_migration_version' ), 10, 2 );
	}

	/**
	 * Backup current settings before plugin update.
	 *
	 * @since 0.4.1
	 *
	 * @return bool True if backup was successful.
	 */
	public function backup_settings() {
		$current_settings = get_option( FPML_Settings::OPTION_KEY, array() );
		
		if ( empty( $current_settings ) ) {
			return false;
		}

		// Create backup with timestamp
		$backup = array(
			'timestamp' => current_time( 'mysql' ),
			'version'   => get_option( self::MIGRATION_VERSION_KEY, '0.0.0' ),
			'settings'  => $current_settings,
		);

		// Save backup
		$success = update_option( self::BACKUP_OPTION_KEY, $backup, false );
		
		if ( $success ) {
			error_log( 'FPML: Settings backup created successfully' );
		}

		return $success;
	}

	/**
	 * Restore settings after plugin update if needed.
	 *
	 * @since 0.4.1
	 *
	 * @return bool True if restoration was successful.
	 */
	public function maybe_restore_settings() {
		// Check if we have a backup
		$backup = get_option( self::BACKUP_OPTION_KEY, array() );
		
		if ( empty( $backup ) || ! isset( $backup['settings'] ) ) {
			return false;
		}

		// Check if current settings are empty or reset to defaults
		$current_settings = get_option( FPML_Settings::OPTION_KEY, array() );
		$defaults = FPML_Settings::instance()->get_defaults();
		
		// If current settings are mostly defaults, restore from backup
		if ( $this->settings_are_defaults( $current_settings, $defaults ) ) {
			return $this->restore_from_backup( $backup['settings'], $current_settings );
		}

		// Settings are not defaults, just migrate new options
		return $this->migrate_new_options( $current_settings, $backup['settings'] );
	}

	/**
	 * Check if current settings are mostly default values.
	 *
	 * @since 0.4.1
	 *
	 * @param array $current_settings Current settings.
	 * @param array $defaults Default settings.
	 *
	 * @return bool True if settings are mostly defaults.
	 */
	protected function settings_are_defaults( $current_settings, $defaults ) {
		// Key settings that indicate if plugin was configured
		$critical_settings = array(
			'provider',
			'openai_api_key',
			'google_api_key',
			'routing_mode',
			'setup_completed',
		);

		$default_count = 0;
		$total_count = count( $critical_settings );

		foreach ( $critical_settings as $key ) {
			$current_value = isset( $current_settings[ $key ] ) ? $current_settings[ $key ] : null;
			$default_value = isset( $defaults[ $key ] ) ? $defaults[ $key ] : null;

			// API keys are special - if they're empty in current but not in backup, it's a reset
			if ( in_array( $key, array( 'openai_api_key', 'google_api_key' ), true ) ) {
				if ( empty( $current_value ) ) {
					$default_count++;
				}
			} else {
				if ( $current_value === $default_value ) {
					$default_count++;
				}
			}
		}

		// If 80% or more critical settings are defaults, consider it a reset
		return ( $default_count / $total_count ) >= 0.8;
	}

	/**
	 * Restore settings from backup.
	 *
	 * @since 0.4.1
	 *
	 * @param array $backup_settings Settings from backup.
	 * @param array $current_settings Current settings.
	 *
	 * @return bool True if restoration was successful.
	 */
	protected function restore_from_backup( $backup_settings, $current_settings ) {
		$defaults = FPML_Settings::instance()->get_defaults();
		
		// Merge backup with current defaults to get new options
		$restored_settings = wp_parse_args( $current_settings, $backup_settings );
		
		// Ensure all new default options are present
		$restored_settings = wp_parse_args( $restored_settings, $defaults );

		// Sanitize the restored settings
		$settings_instance = FPML_Settings::instance();
		if ( method_exists( $settings_instance, 'sanitize' ) ) {
			$restored_settings = $settings_instance->sanitize( $restored_settings );
		}

		// Save restored settings
		$success = update_option( FPML_Settings::OPTION_KEY, $restored_settings );
		
		if ( $success ) {
			error_log( 'FPML: Settings restored from backup successfully' );
			
			// Update migration version
			update_option( self::MIGRATION_VERSION_KEY, self::CURRENT_MIGRATION_VERSION, false );
		}

		return $success;
	}

	/**
	 * Migrate new options from backup to current settings.
	 *
	 * @since 0.4.1
	 *
	 * @param array $current_settings Current settings.
	 * @param array $backup_settings Settings from backup.
	 *
	 * @return bool True if migration was successful.
	 */
	protected function migrate_new_options( $current_settings, $backup_settings ) {
		$defaults = FPML_Settings::instance()->get_defaults();
		$migrated = false;

		// Find new options that exist in defaults but not in current settings
		foreach ( $defaults as $key => $default_value ) {
			if ( ! isset( $current_settings[ $key ] ) && isset( $backup_settings[ $key ] ) ) {
				$current_settings[ $key ] = $backup_settings[ $key ];
				$migrated = true;
			}
		}

		if ( $migrated ) {
			// Sanitize the migrated settings
			$settings_instance = FPML_Settings::instance();
			if ( method_exists( $settings_instance, 'sanitize' ) ) {
				$current_settings = $settings_instance->sanitize( $current_settings );
			}

			update_option( FPML_Settings::OPTION_KEY, $current_settings );
			update_option( self::MIGRATION_VERSION_KEY, self::CURRENT_MIGRATION_VERSION, false );
			
			error_log( 'FPML: New settings options migrated successfully' );
		}

		return $migrated;
	}

	/**
	 * Update migration version when settings are saved.
	 *
	 * @since 0.4.1
	 *
	 * @param array $old_value Old settings value.
	 * @param array $new_value New settings value.
	 *
	 * @return void
	 */
	public function update_migration_version( $old_value, $new_value ) {
		unset( $old_value, $new_value );
		
		update_option( self::MIGRATION_VERSION_KEY, self::CURRENT_MIGRATION_VERSION, false );
	}

	/**
	 * Get backup information.
	 *
	 * @since 0.4.1
	 *
	 * @return array|false Backup data or false if no backup.
	 */
	public function get_backup_info() {
		$backup = get_option( self::BACKUP_OPTION_KEY, array() );
		
		return empty( $backup ) ? false : $backup;
	}

	/**
	 * Clear backup data.
	 *
	 * @since 0.4.1
	 *
	 * @return bool True if backup was cleared.
	 */
	public function clear_backup() {
		return delete_option( self::BACKUP_OPTION_KEY );
	}

	/**
	 * Force backup of current settings.
	 *
	 * @since 0.4.1
	 *
	 * @return bool True if backup was successful.
	 */
	public function force_backup() {
		return $this->backup_settings();
	}
}
