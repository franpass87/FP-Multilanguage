<?php
/**
 * Plugin Name: FP Multilanguage CONSTRUCTOR TEST
 * Description: Test del costruttore
 * Version: 0.4.1-constructor
 * Author: Francesco Passeri
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FPML_PLUGIN_VERSION', '0.4.1' );
define( 'FPML_PLUGIN_FILE', __FILE__ );
define( 'FPML_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FPML_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Carica solo Container
$container = FPML_PLUGIN_DIR . 'includes/core/class-container.php';
if ( file_exists( $container ) ) {
	require_once $container;
}

// Carica classe MINIMAL (costruttore vuoto)
$minimal = FPML_PLUGIN_DIR . 'includes/core/class-plugin-minimal.php';
if ( file_exists( $minimal ) ) {
	require_once $minimal;
}

// Wrapper che estende MINIMAL
class FPML_Plugin_Test extends FPML_Plugin_Core_Minimal {
	protected static $instance = null;
	
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}

function fpml_constructor_init() {
	$message = '🔍 TEST COSTRUTTORE: ';
	
	if ( class_exists( 'FPML_Plugin_Core_Minimal' ) ) {
		$message .= 'Core Minimal OK. ';
	}
	
	if ( class_exists( 'FPML_Plugin_Test' ) ) {
		$message .= 'Wrapper Test OK. ';
		
		try {
			$instance = FPML_Plugin_Test::instance();
			$message .= '✅ Istanza creata! Il problema è NEL COSTRUTTORE ORIGINALE!';
		} catch ( Exception $e ) {
			$message .= '❌ Errore: ' . $e->getMessage();
		}
	}
	
	add_action( 'admin_notices', function() use ( $message ) {
		echo '<div class="notice notice-info"><p>' . esc_html( $message ) . '</p></div>';
	} );
}

add_action( 'plugins_loaded', 'fpml_constructor_init' );

register_activation_hook( __FILE__, function() {
	update_option( 'fpml_constructor_test', '1', false );
} );

register_deactivation_hook( __FILE__, function() {
	delete_option( 'fpml_constructor_test' );
} );

