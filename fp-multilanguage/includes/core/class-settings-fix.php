<?php
/**
 * Settings Fix - Risolve i problemi di salvataggio delle impostazioni
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
 * Fix per i problemi di salvataggio delle impostazioni.
 *
 * @since 0.4.1
 */
class FPML_Settings_Fix {
	/**
	 * Singleton instance.
	 *
	 * @var FPML_Settings_Fix|null
	 */
	protected static $instance = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.1
	 *
	 * @return FPML_Settings_Fix
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
		// Hook into admin_init to ensure settings are registered properly
		add_action( 'admin_init', array( $this, 'ensure_settings_registered' ), 5 );
		
		// Hook into settings save to ensure proper processing
		add_action( 'admin_init', array( $this, 'handle_settings_save' ), 15 );
		
		// Add debugging information
		add_action( 'admin_notices', array( $this, 'show_settings_debug_info' ) );
	}

	/**
	 * Ensure settings are registered properly.
	 *
	 * @since 0.4.1
	 *
	 * @return void
	 */
	public function ensure_settings_registered() {
		// Make sure FPML_Settings is loaded and registered
		if ( class_exists( 'FPML_Settings' ) ) {
			$settings = FPML_Settings::instance();
			
			// Force registration if not already done
			if ( ! has_action( 'admin_init', array( $settings, 'register_settings' ) ) ) {
				$settings->register_settings();
			}
		}
	}

	/**
	 * Handle settings save with proper error handling.
	 *
	 * @since 0.4.1
	 *
	 * @return void
	 */
	public function handle_settings_save() {
		// Only process if we're on the FPML settings page
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'fp-multilanguage' ) {
			return;
		}

		// Check if settings were submitted
		if ( isset( $_POST['option_page'] ) && $_POST['option_page'] === 'fpml_settings_group' ) {
			// Verify nonce
			if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'fpml_settings_group-options' ) ) {
				wp_die( __( 'Errore di sicurezza. Riprova.', 'fp-multilanguage' ) );
			}

			// Process settings manually if needed
			if ( isset( $_POST[ FPML_Settings::OPTION_KEY ] ) ) {
				$this->process_settings_manually( $_POST[ FPML_Settings::OPTION_KEY ] );
			}
		}
	}

	/**
	 * Process settings manually if automatic processing fails.
	 *
	 * @since 0.4.1
	 *
	 * @param array $input Raw input data.
	 *
	 * @return bool True if successful.
	 */
	protected function process_settings_manually( $input ) {
		if ( ! class_exists( 'FPML_Settings' ) ) {
			return false;
		}

		$settings = FPML_Settings::instance();
		
		// Sanitize input
		$sanitized = $settings->sanitize( $input );
		
		// Save to database
		$result = update_option( FPML_Settings::OPTION_KEY, $sanitized );
		
		if ( $result ) {
			// Add success message
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-success is-dismissible"><p>' . 
					 esc_html__( 'Impostazioni salvate con successo.', 'fp-multilanguage' ) . 
					 '</p></div>';
			});
			
			// Redirect to avoid resubmission
			$redirect_url = admin_url( 'admin.php?page=fp-multilanguage&settings-updated=true' );
			if ( isset( $_GET['tab'] ) ) {
				$redirect_url .= '&tab=' . sanitize_key( $_GET['tab'] );
			}
			wp_safe_redirect( $redirect_url );
			exit;
		}
		
		return $result;
	}

	/**
	 * Show debug information for settings.
	 *
	 * @since 0.4.1
	 *
	 * @return void
	 */
	public function show_settings_debug_info() {
		// Only show on FPML settings page
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'fp-multilanguage' ) {
			return;
		}

		// Only show if WP_DEBUG is enabled
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		// Check if settings are registered
		global $wp_registered_settings;
		$settings_registered = isset( $wp_registered_settings[ FPML_Settings::OPTION_KEY ] );
		
		// Check current settings
		$current_settings = get_option( FPML_Settings::OPTION_KEY, array() );
		
		// Show debug info
		echo '<div class="notice notice-info"><p><strong>Debug Info:</strong></p>';
		echo '<ul>';
		echo '<li>Settings registrate: ' . ( $settings_registered ? '✅' : '❌' ) . '</li>';
		echo '<li>Impostazioni caricate: ' . count( $current_settings ) . ' elementi</li>';
		echo '<li>Provider: ' . ( isset( $current_settings['provider'] ) ? $current_settings['provider'] : 'Non impostato' ) . '</li>';
		echo '<li>Setup completato: ' . ( isset( $current_settings['setup_completed'] ) ? ( $current_settings['setup_completed'] ? 'Sì' : 'No' ) : 'Non impostato' ) . '</li>';
		echo '</ul></div>';
	}

	/**
	 * Force save settings with error handling.
	 *
	 * @since 0.4.1
	 *
	 * @param array $settings Settings to save.
	 *
	 * @return bool True if successful.
	 */
	public function force_save_settings( $settings ) {
		if ( ! class_exists( 'FPML_Settings' ) ) {
			return false;
		}

		$settings_instance = FPML_Settings::instance();
		
		// Sanitize settings
		$sanitized = $settings_instance->sanitize( $settings );
		
		// Save to database
		$result = update_option( FPML_Settings::OPTION_KEY, $sanitized );
		
		if ( $result ) {
			error_log( 'FPML: Settings saved successfully via force_save_settings' );
		} else {
			error_log( 'FPML: Failed to save settings via force_save_settings' );
		}
		
		return $result;
	}

	/**
	 * Get current settings with fallback.
	 *
	 * @since 0.4.1
	 *
	 * @return array Current settings.
	 */
	public function get_current_settings() {
		if ( ! class_exists( 'FPML_Settings' ) ) {
			return array();
		}

		return FPML_Settings::instance()->all();
	}

	/**
	 * Check if settings are properly configured.
	 *
	 * @since 0.4.1
	 *
	 * @return array Status information.
	 */
	public function check_settings_status() {
		$status = array(
			'class_loaded' => class_exists( 'FPML_Settings' ),
			'settings_registered' => false,
			'settings_loaded' => false,
			'provider_configured' => false,
			'api_key_configured' => false,
		);

		if ( $status['class_loaded'] ) {
			global $wp_registered_settings;
			$status['settings_registered'] = isset( $wp_registered_settings[ FPML_Settings::OPTION_KEY ] );
			
			$current_settings = get_option( FPML_Settings::OPTION_KEY, array() );
			$status['settings_loaded'] = ! empty( $current_settings );
			
			if ( $status['settings_loaded'] ) {
				$status['provider_configured'] = ! empty( $current_settings['provider'] );
				$status['api_key_configured'] = ! empty( $current_settings['openai_api_key'] ) || ! empty( $current_settings['google_api_key'] );
			}
		}

		return $status;
	}
}
