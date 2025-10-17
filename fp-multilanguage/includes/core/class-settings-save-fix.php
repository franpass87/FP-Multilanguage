<?php
/**
 * Settings Save Fix - Risolve definitivamente i problemi di salvataggio
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
 * Fix definitivo per i problemi di salvataggio delle impostazioni.
 *
 * @since 0.4.1
 */
class FPML_Settings_Save_Fix {
	/**
	 * Singleton instance.
	 *
	 * @var FPML_Settings_Save_Fix|null
	 */
	protected static $instance = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.1
	 *
	 * @return FPML_Settings_Save_Fix
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
		// Hook into admin_init early to ensure settings are properly handled
		add_action( 'admin_init', array( $this, 'ensure_proper_settings_handling' ), 1 );
		
		// Hook into the settings save process
		add_action( 'admin_init', array( $this, 'handle_settings_form_submission' ), 10 );
		
		// Add success message handling
		add_action( 'admin_notices', array( $this, 'show_save_success_message' ) );
	}

	/**
	 * Ensure proper settings handling.
	 *
	 * @since 0.4.1
	 *
	 * @return void
	 */
	public function ensure_proper_settings_handling() {
		// Only run on FPML settings pages
		if ( ! $this->is_fpml_settings_page() ) {
			return;
		}

		// Ensure FPML_Settings is properly initialized
		if ( class_exists( 'FPML_Settings' ) ) {
			$settings = FPML_Settings::instance();
			
			// Force registration if needed
			if ( ! $this->are_settings_registered() ) {
				$settings->register_settings();
			}
		}
	}

	/**
	 * Handle settings form submission.
	 *
	 * @since 0.4.1
	 *
	 * @return void
	 */
	public function handle_settings_form_submission() {
		// Only process if we're on the FPML settings page and form was submitted
		if ( ! $this->is_fpml_settings_page() ) {
			return;
		}

		// Check if settings form was submitted
		if ( ! $this->is_settings_form_submitted() ) {
			return;
		}

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'fpml_settings_group-options' ) ) {
			wp_die( __( 'Errore di sicurezza. Riprova.', 'fp-multilanguage' ) );
		}

		// Process settings manually to ensure they're saved
		$this->process_settings_manually();
	}

	/**
	 * Process settings manually to ensure proper saving.
	 *
	 * @since 0.4.1
	 *
	 * @return bool True if successful.
	 */
	protected function process_settings_manually() {
		if ( ! isset( $_POST[ FPML_Settings::OPTION_KEY ] ) ) {
			return false;
		}

		$raw_input = $_POST[ FPML_Settings::OPTION_KEY ];
		
		if ( ! class_exists( 'FPML_Settings' ) ) {
			return false;
		}

		$settings = FPML_Settings::instance();
		
		// Sanitize the input
		$sanitized_settings = $settings->sanitize( $raw_input );
		
		// Temporarily disable migration hooks to avoid conflicts
		$this->temporarily_disable_migration();
		
		// Save to database
		$result = update_option( FPML_Settings::OPTION_KEY, $sanitized_settings );
		
		// Re-enable migration hooks
		$this->re_enable_migration();
		
		if ( $result ) {
			// Set success flag
			set_transient( 'fpml_settings_saved', true, 30 );
			
			// Log success
			error_log( 'FPML: Settings saved successfully via manual processing' );
			
			// Redirect to avoid resubmission
			$this->redirect_after_save();
		} else {
			error_log( 'FPML: Failed to save settings via manual processing' );
		}
		
		return $result;
	}

	/**
	 * Temporarily disable migration hooks.
	 *
	 * @since 0.4.1
	 *
	 * @return void
	 */
	protected function temporarily_disable_migration() {
		// Remove migration hooks temporarily
		if ( class_exists( 'FPML_Settings_Migration' ) ) {
			remove_action( 'update_option_fpml_settings', array( 
				FPML_Settings_Migration::instance(), 
				'update_migration_version' 
			), 999 );
		}
	}

	/**
	 * Re-enable migration hooks.
	 *
	 * @since 0.4.1
	 *
	 * @return void
	 */
	protected function re_enable_migration() {
		// Re-add migration hooks
		if ( class_exists( 'FPML_Settings_Migration' ) ) {
			add_action( 'update_option_fpml_settings', array( 
				FPML_Settings_Migration::instance(), 
				'update_migration_version' 
			), 999, 2 );
		}
	}

	/**
	 * Redirect after successful save.
	 *
	 * @since 0.4.1
	 *
	 * @return void
	 */
	protected function redirect_after_save() {
		$redirect_url = admin_url( 'admin.php?page=fp-multilanguage&settings-updated=true' );
		
		// Preserve current tab
		if ( isset( $_GET['tab'] ) ) {
			$redirect_url .= '&tab=' . sanitize_key( $_GET['tab'] );
		}
		
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Show save success message.
	 *
	 * @since 0.4.1
	 *
	 * @return void
	 */
	public function show_save_success_message() {
		// Only show on FPML settings page
		if ( ! $this->is_fpml_settings_page() ) {
			return;
		}

		// Check if settings were just saved
		if ( get_transient( 'fpml_settings_saved' ) ) {
			delete_transient( 'fpml_settings_saved' );
			
			echo '<div class="notice notice-success is-dismissible">';
			echo '<p><strong>' . esc_html__( 'Impostazioni salvate con successo!', 'fp-multilanguage' ) . '</strong></p>';
			echo '</div>';
		}
	}

	/**
	 * Check if we're on the FPML settings page.
	 *
	 * @since 0.4.1
	 *
	 * @return bool True if on FPML settings page.
	 */
	protected function is_fpml_settings_page() {
		return isset( $_GET['page'] ) && $_GET['page'] === 'fp-multilanguage';
	}

	/**
	 * Check if settings form was submitted.
	 *
	 * @since 0.4.1
	 *
	 * @return bool True if form was submitted.
	 */
	protected function is_settings_form_submitted() {
		return isset( $_POST['option_page'] ) && $_POST['option_page'] === 'fpml_settings_group';
	}

	/**
	 * Check if settings are registered.
	 *
	 * @since 0.4.1
	 *
	 * @return bool True if settings are registered.
	 */
	protected function are_settings_registered() {
		global $wp_registered_settings;
		return isset( $wp_registered_settings[ FPML_Settings::OPTION_KEY ] );
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
		
		// Temporarily disable migration
		$this->temporarily_disable_migration();
		
		// Save to database
		$result = update_option( FPML_Settings::OPTION_KEY, $sanitized );
		
		// Re-enable migration
		$this->re_enable_migration();
		
		if ( $result ) {
			error_log( 'FPML: Settings saved successfully via force_save_settings' );
		} else {
			error_log( 'FPML: Failed to save settings via force_save_settings' );
		}
		
		return $result;
	}

	/**
	 * Get diagnostic information.
	 *
	 * @since 0.4.1
	 *
	 * @return array Diagnostic information.
	 */
	public function get_diagnostics() {
		$diagnostics = array(
			'settings_class_available' => class_exists( 'FPML_Settings' ),
			'settings_registered' => $this->are_settings_registered(),
			'migration_class_available' => class_exists( 'FPML_Settings_Migration' ),
			'fix_class_available' => class_exists( 'FPML_Settings_Fix' ),
			'current_settings_count' => 0,
			'provider_configured' => false,
			'api_key_configured' => false,
		);

		if ( $diagnostics['settings_class_available'] ) {
			$current_settings = get_option( FPML_Settings::OPTION_KEY, array() );
			$diagnostics['current_settings_count'] = count( $current_settings );
			$diagnostics['provider_configured'] = ! empty( $current_settings['provider'] );
			$diagnostics['api_key_configured'] = ! empty( $current_settings['openai_api_key'] ) || ! empty( $current_settings['google_api_key'] );
		}

		return $diagnostics;
	}
}
