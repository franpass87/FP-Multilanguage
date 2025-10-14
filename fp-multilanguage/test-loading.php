<?php
/**
 * Script di test caricamento - Identifica il file che causa l'errore 500
 * 
 * Accedi a: https://viterboantica7576.live-website.com/wp-content/plugins/FP-Multilanguage/test-loading.php?test=1
 */

if ( ! isset( $_GET['test'] ) || $_GET['test'] !== '1' ) {
	die( 'Accesso negato. Aggiungi ?test=1 all\'URL' );
}

header( 'Content-Type: text/plain; charset=utf-8' );
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

echo "=== TEST CARICAMENTO FP MULTILANGUAGE ===\n\n";

// Definisci costanti WordPress simulate
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( dirname( dirname( __DIR__ ) ) ) . '/' );
}

// Definisci costanti plugin
define( 'FPML_PLUGIN_VERSION', '0.4.1' );
define( 'FPML_PLUGIN_FILE', __DIR__ . '/fp-multilanguage.php' );
define( 'FPML_PLUGIN_DIR', __DIR__ . '/' );
define( 'FPML_PLUGIN_URL', 'http://test/' );

echo "1. Costanti definite ✓\n";
echo "   FPML_PLUGIN_DIR: " . FPML_PLUGIN_DIR . "\n\n";

// Test 1: Verifica vendor/autoload.php
echo "2. Test vendor/autoload.php...\n";
$autoload = FPML_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	echo "   File esiste: ✓\n";
	echo "   Caricamento... ";
	try {
		require_once $autoload;
		echo "✓\n\n";
	} catch ( Exception $e ) {
		echo "✗ ERRORE: " . $e->getMessage() . "\n";
		die();
	}
} else {
	echo "   ✗ FILE NON TROVATO!\n";
	echo "   Percorso cercato: $autoload\n\n";
}

// Test 2: Carica file core uno alla volta
echo "3. Test caricamento file CORE...\n";
$core_classes = array(
	'includes/core/class-container.php',
	'includes/core/class-plugin.php',
	'includes/core/class-secure-settings.php',
	'includes/core/class-translation-cache.php',
	'includes/core/class-translation-versioning.php',
);

foreach ( $core_classes as $core_class ) {
	$file = FPML_PLUGIN_DIR . $core_class;
	echo "   Caricamento: " . basename( $core_class ) . "... ";
	
	if ( ! file_exists( $file ) ) {
		echo "✗ FILE NON TROVATO\n";
		continue;
	}
	
	try {
		require_once $file;
		echo "✓\n";
	} catch ( Exception $e ) {
		echo "✗ ERRORE: " . $e->getMessage() . "\n";
		die( "\n=== ERRORE FATALE - Il file sopra causa il problema ===\n" );
	} catch ( Error $e ) {
		echo "✗ ERRORE FATALE: " . $e->getMessage() . "\n";
		echo "   File: " . $e->getFile() . "\n";
		echo "   Linea: " . $e->getLine() . "\n";
		die( "\n=== ERRORE FATALE - Il file sopra causa il problema ===\n" );
	}
}

echo "\n4. Test verifica classi core caricate...\n";
$classes = array( 'FPML_Container', 'FPML_Plugin_Core' );
foreach ( $classes as $class ) {
	echo "   $class: " . ( class_exists( $class ) ? '✓' : '✗ NON TROVATA' ) . "\n";
}

echo "\n5. Test caricamento altri file...\n";
echo "   Scansione directory includes/...\n";

$includes_dir = FPML_PLUGIN_DIR . 'includes/';
$files = array();

if ( is_dir( $includes_dir ) ) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $includes_dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $file ) {
		if ( 'php' === strtolower( $file->getExtension() ) ) {
			$filepath = $file->getPathname();
			
			// Salta file core (già caricati)
			if ( strpos( $filepath, DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR ) !== false ) {
				continue;
			}
			
			$files[] = $filepath;
		}
	}
	
	sort( $files );
	
	echo "   Trovati " . count( $files ) . " file da caricare\n\n";
	
	$loaded = 0;
	foreach ( $files as $filepath ) {
		$basename = basename( $filepath );
		echo "   Caricamento: $basename... ";
		
		try {
			require_once $filepath;
			echo "✓\n";
			$loaded++;
		} catch ( Exception $e ) {
			echo "✗ ERRORE: " . $e->getMessage() . "\n";
			die( "\n=== ERRORE - Il file sopra causa il problema ===\n" );
		} catch ( Error $e ) {
			echo "✗ ERRORE FATALE: " . $e->getMessage() . "\n";
			echo "   File: " . $e->getFile() . "\n";
			echo "   Linea: " . $e->getLine() . "\n";
			die( "\n=== ERRORE FATALE - Il file sopra causa il problema ===\n" );
		}
	}
	
	echo "\n   Caricati con successo: $loaded file ✓\n";
} else {
	echo "   ✗ Directory includes/ non trovata\n";
}

echo "\n6. Test finale - Verifica classe FPML_Plugin...\n";
if ( class_exists( 'FPML_Plugin' ) ) {
	echo "   FPML_Plugin: ✓ ESISTE\n";
} else {
	echo "   FPML_Plugin: ✗ NON TROVATA\n";
}

echo "\n=== TEST COMPLETATO CON SUCCESSO ✓ ===\n";
echo "\nSe vedi questo messaggio, il problema NON è nel caricamento dei file.\n";
echo "Il problema potrebbe essere:\n";
echo "- Conflitto con altri plugin\n";
echo "- Problema durante l'attivazione (hook activation)\n";
echo "- Problema con il database\n";
echo "\nESEGUI ORA: diagnostic.php?fpml_diag=check per maggiori dettagli\n";

