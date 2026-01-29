<?php
/**
 * Test Runner - Esegue tutti i test del plugin
 * 
 * Questo script carica WordPress e esegue tutti i test automatici.
 * 
 * Usage: php tests/run-tests.php [test-name]
 * 
 * @package FP_Multilanguage
 */

// Find wp-load.php - tests/ è in wp-content/plugins/FP-Multilanguage/tests/
// Quindi wp-load.php è in ../../../ (wp-content/plugins/FP-Multilanguage -> wp-content/plugins -> wp-content -> root)
$wp_load_paths = array(
    __DIR__ . '/../../../wp-load.php',  // From tests/ to root
    dirname( dirname( dirname( __DIR__ ) ) ) . '/wp-load.php',
);

$wp_loaded = false;
foreach ( $wp_load_paths as $path ) {
    if ( file_exists( $path ) ) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if ( ! $wp_loaded ) {
    // Try parent directories
    $current = realpath( __DIR__ );
    if ( $current ) {
        $parts = explode( DIRECTORY_SEPARATOR, $current );
        // Build paths going up from tests/ directory
        for ( $i = count( $parts ) - 1; $i >= 3; $i-- ) {
            $test_parts = array_slice( $parts, 0, $i );
            $test_path = implode( DIRECTORY_SEPARATOR, $test_parts ) . DIRECTORY_SEPARATOR . 'wp-load.php';
            if ( file_exists( $test_path ) ) {
                require_once $test_path;
                $wp_loaded = true;
                break;
            }
        }
    }
}

if ( ! defined( 'ABSPATH' ) || ! $wp_loaded ) {
    // Last attempt: use the directory where script is executed from
    $script_dir = getcwd();
    $wp_load = $script_dir . DIRECTORY_SEPARATOR . 'wp-load.php';
    if ( file_exists( $wp_load ) ) {
        require_once $wp_load;
        $wp_loaded = true;
    }
}

if ( ! defined( 'ABSPATH' ) || ! $wp_loaded ) {
    echo "ERRORE: Impossibile trovare wp-load.php.\n";
    echo "Directory corrente: " . getcwd() . "\n";
    echo "Directory script: " . __DIR__ . "\n";
    echo "Percorsi provati:\n";
    foreach ( $wp_load_paths as $p ) {
        echo "  - $p (" . ( file_exists( $p ) ? 'ESISTE' : 'NON ESISTE' ) . ")\n";
    }
    die( "Assicurati di eseguire lo script dalla root di WordPress.\n" );
}

// Get test to run
$test_name = isset( $argv[1] ) ? $argv[1] : 'all';

$tests = array(
    'structure' => 'test-plugin-structure.php',
    'rest-api' => 'test-rest-api-endpoints.php',
    'ajax' => 'test-ajax-handlers.php',
    'routing' => 'test-frontend-routing.php',
);

if ( $test_name === 'all' ) {
    foreach ( $tests as $name => $file ) {
        echo "\n" . str_repeat( '=', 70 ) . "\n";
        echo "Esecuzione test: {$name}\n";
        echo str_repeat( '=', 70 ) . "\n\n";
        require_once __DIR__ . '/' . $file;
        echo "\n";
    }
} elseif ( isset( $tests[ $test_name ] ) ) {
    echo "\n" . str_repeat( '=', 70 ) . "\n";
    echo "Esecuzione test: {$test_name}\n";
    echo str_repeat( '=', 70 ) . "\n\n";
    require_once __DIR__ . '/' . $tests[ $test_name ];
} else {
    echo "Test non trovato: {$test_name}\n";
    echo "Test disponibili: " . implode( ', ', array_keys( $tests ) ) . ", all\n";
    exit( 1 );
}

