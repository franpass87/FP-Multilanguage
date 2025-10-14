<?php
/**
 * Plugin Name: FP Multilanguage - TEST MINIMAL
 * Description: Versione minima per test - NO COMPOSER
 * Version: 0.4.1-test
 */

// Accedi direttamente a questo file via browser per testare
if ( isset( $_GET['test'] ) && $_GET['test'] === 'minimal' ) {
	header( 'Content-Type: text/plain; charset=utf-8' );
	
	echo "=== TEST MINIMAL - SENZA COMPOSER ===\n\n";
	
	define( 'FPML_PLUGIN_DIR', __DIR__ . '/' );
	
	echo "1. Test caricamento class-container.php...\n";
	$file = FPML_PLUGIN_DIR . 'includes/core/class-container.php';
	if ( file_exists( $file ) ) {
		require_once $file;
		echo "   ✓ Caricato\n\n";
	} else {
		die( "   ✗ File non trovato\n" );
	}
	
	echo "2. Test caricamento class-plugin.php...\n";
	$file = FPML_PLUGIN_DIR . 'includes/core/class-plugin.php';
	if ( file_exists( $file ) ) {
		require_once $file;
		echo "   ✓ Caricato\n\n";
	} else {
		die( "   ✗ File non trovato\n" );
	}
	
	echo "3. Test classe FPML_Plugin_Core...\n";
	if ( class_exists( 'FPML_Plugin_Core' ) ) {
		echo "   ✓ Classe esiste\n\n";
	} else {
		die( "   ✗ Classe non trovata\n" );
	}
	
	echo "4. Test caricamento class-plugin.php wrapper...\n";
	$file = FPML_PLUGIN_DIR . 'includes/class-plugin.php';
	if ( file_exists( $file ) ) {
		require_once $file;
		echo "   ✓ Caricato\n\n";
	} else {
		die( "   ✗ File non trovato\n" );
	}
	
	echo "5. Test classe FPML_Plugin...\n";
	if ( class_exists( 'FPML_Plugin' ) ) {
		echo "   ✓ Classe esiste\n\n";
	} else {
		die( "   ✗ Classe non trovata\n" );
	}
	
	echo "=== TEST MINIMAL COMPLETATO ✓ ===\n\n";
	echo "Le classi base funzionano!\n";
	echo "Il problema potrebbe essere:\n";
	echo "- vendor/autoload.php mancante o corrotto\n";
	echo "- Problema in altre classi caricate\n";
	echo "- Problema durante attivazione\n";
	
	die();
}

// Se non è un test, comportati come plugin normale (disabilitato)
die( 'Plugin in modalità TEST. Per testare vai su: ' . plugin_dir_url( __FILE__ ) . 'test-minimal.php?test=minimal' );

