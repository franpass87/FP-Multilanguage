<?php
/**
 * Plugin Name: FP Multilanguage BASE
 * Description: Versione base funzionante - Solo core essenziale
 * Version: 0.4.1-base
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
 * Carica solo i file essenziali.
 */
function fpml_base_load_core() {
	$files = array(
		FPML_PLUGIN_DIR . 'includes/core/class-container.php',
		FPML_PLUGIN_DIR . 'includes/core/class-plugin.php',
	);
	
	foreach ( $files as $file ) {
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}

/**
 * Inizializza il plugin.
 */
function fpml_base_init() {
	fpml_base_load_core();
	
	if ( class_exists( 'FPML_Plugin_Core' ) ) {
		// OK - classe caricata
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-success"><p>';
			echo '✅ FP Multilanguage BASE: Core caricato con successo!';
			echo '</p></div>';
		} );
	}
}

add_action( 'plugins_loaded', 'fpml_base_init' );

/**
 * Attivazione - sicura.
 */
register_activation_hook( __FILE__, function() {
	update_option( 'fpml_base_activated', '1', false );
} );

/**
 * Disattivazione.
 */
register_deactivation_hook( __FILE__, function() {
	delete_option( 'fpml_base_activated' );
} );

