<?php
/**
 * Plugin Name: FP Multilanguage WRAPPER
 * Description: Test con core + wrapper
 * Version: 0.4.1-wrapper
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
 * Carica file core + wrapper.
 */
function fpml_wrapper_load() {
	// Prima i file core
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
	
	// Poi il wrapper (estende FPML_Plugin_Core)
	$wrapper = FPML_PLUGIN_DIR . 'includes/class-plugin.php';
	if ( file_exists( $wrapper ) ) {
		require_once $wrapper;
	}
}

/**
 * Inizializza.
 */
function fpml_wrapper_init() {
	fpml_wrapper_load();
	
	$message = '✅ FP Multilanguage WRAPPER: ';
	
	if ( class_exists( 'FPML_Plugin_Core' ) ) {
		$message .= 'Core OK. ';
	}
	
	if ( class_exists( 'FPML_Plugin' ) ) {
		$message .= 'Wrapper OK (FPML_Plugin caricata)!';
	} else {
		$message .= '❌ Wrapper FAILED (FPML_Plugin non trovata)!';
	}
	
	add_action( 'admin_notices', function() use ( $message ) {
		echo '<div class="notice notice-success"><p>' . esc_html( $message ) . '</p></div>';
	} );
}

add_action( 'plugins_loaded', 'fpml_wrapper_init' );

/**
 * Attivazione.
 */
register_activation_hook( __FILE__, function() {
	update_option( 'fpml_wrapper_activated', '1', false );
} );

/**
 * Disattivazione.
 */
register_deactivation_hook( __FILE__, function() {
	delete_option( 'fpml_wrapper_activated' );
} );

