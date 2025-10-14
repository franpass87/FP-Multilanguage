<?php
/**
 * Diagnostic script for FP Multilanguage plugin
 * Upload this to the plugin directory and access it via browser to see diagnostic info
 */

// Security check
if ( ! isset( $_GET['fpml_diag'] ) || $_GET['fpml_diag'] !== 'check' ) {
	die( 'Access denied. Add ?fpml_diag=check to URL to run diagnostics.' );
}

header( 'Content-Type: text/plain; charset=utf-8' );

echo "FP MULTILANGUAGE - DIAGNOSTIC REPORT\n";
echo str_repeat( '=', 60 ) . "\n\n";

// 1. PHP Version
echo "1. PHP VERSION\n";
echo "   Version: " . PHP_VERSION . "\n";
echo "   SAPI: " . php_sapi_name() . "\n\n";

// 2. Directory Structure
echo "2. PLUGIN DIRECTORY\n";
$plugin_dir = __DIR__;
echo "   Plugin Dir: $plugin_dir\n";
echo "   Exists: " . ( is_dir( $plugin_dir ) ? 'YES' : 'NO' ) . "\n";
echo "   Readable: " . ( is_readable( $plugin_dir ) ? 'YES' : 'NO' ) . "\n\n";

// 3. Main Files
echo "3. MAIN FILES\n";
$main_files = array(
	'fp-multilanguage.php',
	'vendor/autoload.php',
	'includes/class-plugin.php',
	'includes/core/class-plugin.php',
	'includes/core/class-container.php',
);

foreach ( $main_files as $file ) {
	$path = $plugin_dir . '/' . $file;
	$exists = file_exists( $path );
	$readable = $exists && is_readable( $path );
	echo "   $file:\n";
	echo "      Exists: " . ( $exists ? 'YES' : 'NO' ) . "\n";
	echo "      Readable: " . ( $readable ? 'YES' : 'NO' ) . "\n";
}
echo "\n";

// 4. Includes Directory Scan
echo "4. INCLUDES DIRECTORY SCAN\n";
$includes_dir = $plugin_dir . '/includes/';
if ( is_dir( $includes_dir ) ) {
	$core_files = array();
	$other_files = array();
	
	if ( class_exists( 'RecursiveIteratorIterator' ) && class_exists( 'RecursiveDirectoryIterator' ) ) {
		$flags = FilesystemIterator::SKIP_DOTS;
		
		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $includes_dir, $flags ),
				RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ( $iterator as $file ) {
				if ( 'php' === strtolower( $file->getExtension() ) ) {
					$filepath = $file->getPathname();
					$normalized = str_replace( array( '\\', '/' ), DIRECTORY_SEPARATOR, $filepath );
					
					if ( strpos( $normalized, DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR ) !== false ) {
						$core_files[] = basename( $filepath );
					} else {
						$other_files[] = basename( $filepath );
					}
				}
			}
			
			sort( $core_files );
			sort( $other_files );
			
			echo "   Core files (" . count( $core_files ) . "):\n";
			foreach ( $core_files as $file ) {
				echo "      - $file\n";
			}
			echo "\n   Other files (" . count( $other_files ) . "):\n";
			$display_count = min( 10, count( $other_files ) );
			for ( $i = 0; $i < $display_count; $i++ ) {
				echo "      - " . $other_files[ $i ] . "\n";
			}
			if ( count( $other_files ) > 10 ) {
				echo "      ... and " . ( count( $other_files ) - 10 ) . " more files\n";
			}
		} catch ( Exception $e ) {
			echo "   ERROR: " . $e->getMessage() . "\n";
		}
	} else {
		echo "   ERROR: RecursiveIteratorIterator not available\n";
	}
} else {
	echo "   ERROR: Includes directory not found\n";
}
echo "\n";

// 5. Try Loading Classes
echo "5. CLASS LOADING TEST\n";
$vendor_autoload = $plugin_dir . '/vendor/autoload.php';
if ( file_exists( $vendor_autoload ) ) {
	echo "   Loading vendor/autoload.php ... ";
	require_once $vendor_autoload;
	echo "OK\n";
} else {
	echo "   vendor/autoload.php NOT FOUND\n";
}

echo "   Loading includes/core/class-container.php ... ";
$container_file = $plugin_dir . '/includes/core/class-container.php';
if ( file_exists( $container_file ) ) {
	require_once $container_file;
	echo ( class_exists( 'FPML_Container' ) ? 'OK' : 'FAILED - class not found' ) . "\n";
} else {
	echo "FILE NOT FOUND\n";
}

echo "   Loading includes/core/class-plugin.php ... ";
$core_plugin_file = $plugin_dir . '/includes/core/class-plugin.php';
if ( file_exists( $core_plugin_file ) ) {
	require_once $core_plugin_file;
	echo ( class_exists( 'FPML_Plugin_Core' ) ? 'OK' : 'FAILED - class not found' ) . "\n";
} else {
	echo "FILE NOT FOUND\n";
}

echo "   Loading includes/class-plugin.php ... ";
$plugin_file = $plugin_dir . '/includes/class-plugin.php';
if ( file_exists( $plugin_file ) ) {
	try {
		require_once $plugin_file;
		echo ( class_exists( 'FPML_Plugin' ) ? 'OK' : 'FAILED - class not found' ) . "\n";
	} catch ( Exception $e ) {
		echo "ERROR: " . $e->getMessage() . "\n";
	}
} else {
	echo "FILE NOT FOUND\n";
}
echo "\n";

// 6. Memory & Limits
echo "6. SYSTEM INFO\n";
echo "   Memory Limit: " . ini_get( 'memory_limit' ) . "\n";
echo "   Max Execution Time: " . ini_get( 'max_execution_time' ) . "s\n";
echo "   Directory Separator: " . DIRECTORY_SEPARATOR . "\n\n";

echo str_repeat( '=', 60 ) . "\n";
echo "DIAGNOSTIC COMPLETE\n";
echo "\nIf you see errors above, please share this output for support.\n";

