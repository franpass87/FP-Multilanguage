<?php
/**
 * Plugin Name: FP Multilanguage WRAPPER SAFE
 * Description: Test wrapper SENZA costruttore parent
 * Version: 0.4.1-wrapper-safe
 * Author: Francesco Passeri
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Costanti
define( 'FPML_PLUGIN_VERSION', '0.4.1' );
define( 'FPML_PLUGIN_FILE', __FILE__ );
define( 'FPML_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FPML_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Carica file core.
 */
function fpml_wrapper_safe_load() {
	$core_files = array(
		FPML_PLUGIN_DIR . 'includes/core/class-container.php',
		FPML_PLUGIN_DIR . 'includes/core/class-plugin.php',
		FPML_PLUGIN_DIR . 'includes/core/class-secure-settings.php',
		FPML_PLUGIN_DIR . 'includes/core/class-translation-cache.php',
		FPML_PLUGIN_DIR . 'includes/core/class-translation-versioning.php',
	);
	
	foreach ( $core_files as $file ) {
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}

/**
 * Wrapper SEMPLIFICATO - senza costruttore.
 */
class FPML_Plugin_Simple {
	protected static $instance = null;
	
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	// Costruttore VUOTO - non chiama parent
	protected function __construct() {
		// Vuoto
	}
}

/**
 * Inizializza.
 */
function fpml_wrapper_safe_init() {
	fpml_wrapper_safe_load();
	
	$message = '✅ WRAPPER SAFE: ';
	
	if ( class_exists( 'FPML_Plugin_Core' ) ) {
		$message .= 'Core OK. ';
	}
	
	if ( class_exists( 'FPML_Plugin_Simple' ) ) {
		$message .= 'Wrapper semplice OK!';
		
		// Prova a istanziare
		try {
			FPML_Plugin_Simple::instance();
			$message .= ' Istanza creata OK!';
		} catch ( Exception $e ) {
			$message .= ' ❌ Errore istanza: ' . $e->getMessage();
		}
	}
	
	add_action( 'admin_notices', function() use ( $message ) {
		echo '<div class="notice notice-success"><p>' . esc_html( $message ) . '</p></div>';
	} );
}

add_action( 'plugins_loaded', 'fpml_wrapper_safe_init' );

register_activation_hook( __FILE__, function() {
	update_option( 'fpml_wrapper_safe_activated', '1', false );
} );

register_deactivation_hook( __FILE__, function() {
	delete_option( 'fpml_wrapper_safe_activated' );
} );

