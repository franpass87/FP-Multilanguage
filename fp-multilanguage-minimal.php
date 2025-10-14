<?php
/**
 * Plugin Name: FP Multilanguage MINIMAL
 * Description: Versione minimale per test - Solo funzioni base
 * Version: 0.4.1-minimal
 * Author: Francesco Passeri
 */

// STOP se accesso diretto
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Costanti base
define( 'FPML_VERSION', '0.4.1' );
define( 'FPML_FILE', __FILE__ );
define( 'FPML_DIR', plugin_dir_path( __FILE__ ) );

// ATTIVAZIONE - FA ASSOLUTAMENTE NIENTE
register_activation_hook( __FILE__, function() {
	// Vuoto - non fa nulla
} );

// DISATTIVAZIONE - FA ASSOLUTAMENTE NIENTE
register_deactivation_hook( __FILE__, function() {
	// Vuoto - non fa nulla
} );

// Mostra un messaggio in admin
add_action( 'admin_notices', function() {
	echo '<div class="notice notice-success"><p>';
	echo '✅ FP Multilanguage MINIMAL è attivo. Questo è un test per verificare che l\'attivazione funzioni.';
	echo '</p></div>';
} );

