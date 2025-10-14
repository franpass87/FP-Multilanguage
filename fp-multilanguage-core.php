<?php
/**
 * Plugin Name: FP Multilanguage CORE
 * Description: Test con TUTTI i file core
 * Version: 0.4.1-core
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
 * Carica TUTTI i file core.
 */
function fpml_core_load() {
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
 * Inizializza.
 */
function fpml_core_init() {
	fpml_core_load();
	
	add_action( 'admin_notices', function() {
		echo '<div class="notice notice-success"><p>';
		echo '✅ FP Multilanguage CORE: Tutti i 5 file core caricati con successo!';
		echo '</p></div>';
	} );
}

add_action( 'plugins_loaded', 'fpml_core_init' );

/**
 * Attivazione.
 */
register_activation_hook( __FILE__, function() {
	update_option( 'fpml_core_activated', '1', false );
} );

/**
 * Disattivazione.
 */
register_deactivation_hook( __FILE__, function() {
	delete_option( 'fpml_core_activated' );
} );

