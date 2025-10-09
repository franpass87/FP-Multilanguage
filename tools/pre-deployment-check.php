<?php
/**
 * Pre-Deployment Health Check Script
 *
 * Verifica che il plugin sia pronto per il deployment verificando:
 * - Presenza e caricamento di tutte le classi
 * - Tabelle database create
 * - API keys configurate e crittografate
 * - Funzionalità critiche funzionanti
 *
 * Usage:
 *   php tools/pre-deployment-check.php
 *   wp eval-file tools/pre-deployment-check.php
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @since 0.4.1
 */

// Load WordPress if running standalone
if ( ! defined( 'ABSPATH' ) ) {
	require_once dirname( __DIR__ ) . '/../../../wp-load.php';
}

/**
 * Main health check function.
 */
function fpml_pre_deployment_check() {
	$colors = array(
		'reset'   => "\033[0m",
		'red'     => "\033[31m",
		'green'   => "\033[32m",
		'yellow'  => "\033[33m",
		'blue'    => "\033[34m",
		'cyan'    => "\033[36m",
		'bold'    => "\033[1m",
	);

	echo $colors['cyan'] . $colors['bold'] . "\n";
	echo "╔════════════════════════════════════════════════╗\n";
	echo "║  FP Multilanguage - Pre-Deployment Check      ║\n";
	echo "║  Version 0.4.1                                 ║\n";
	echo "╚════════════════════════════════════════════════╝\n";
	echo $colors['reset'] . "\n";

	$checks = array(
		'classes'    => false,
		'tables'     => false,
		'encryption' => false,
		'settings'   => false,
		'providers'  => false,
		'cache'      => false,
		'versioning' => false,
		'rest_api'   => false,
	);

	$errors   = array();
	$warnings = array();

	// 1. Check Classes
	echo $colors['blue'] . "🔍 Checking Classes...\n" . $colors['reset'];
	$required_classes = array(
		'FPML_Plugin',
		'FPML_Settings',
		'FPML_Queue',
		'FPML_Processor',
		'FPML_Logger',
		'FPML_Translation_Cache',
		'FPML_Secure_Settings',
		'FPML_Translation_Versioning',
		'FPML_Container',
		'FPML_REST_Admin',
	);

	$missing_classes = array();
	foreach ( $required_classes as $class ) {
		if ( ! class_exists( $class ) ) {
			$missing_classes[] = $class;
		}
	}

	if ( empty( $missing_classes ) ) {
		echo $colors['green'] . "  ✓ All required classes loaded (" . count( $required_classes ) . ")\n" . $colors['reset'];
		$checks['classes'] = true;
	} else {
		echo $colors['red'] . "  ✗ Missing classes:\n" . $colors['reset'];
		foreach ( $missing_classes as $class ) {
			echo "    - $class\n";
			$errors[] = "Missing class: $class";
		}
	}

	// 2. Check Database Tables
	echo $colors['blue'] . "\n🔍 Checking Database Tables...\n" . $colors['reset'];
	global $wpdb;
	
	$required_tables = array(
		$wpdb->prefix . 'fpml_queue',
		$wpdb->prefix . 'fpml_logs',
		$wpdb->prefix . 'fpml_translation_versions',
	);

	$missing_tables = array();
	foreach ( $required_tables as $table ) {
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $exists ) {
			$missing_tables[] = $table;
		}
	}

	if ( empty( $missing_tables ) ) {
		echo $colors['green'] . "  ✓ All required tables exist (" . count( $required_tables ) . ")\n" . $colors['reset'];
		$checks['tables'] = true;
	} else {
		echo $colors['red'] . "  ✗ Missing tables:\n" . $colors['reset'];
		foreach ( $missing_tables as $table ) {
			echo "    - $table\n";
			$errors[] = "Missing table: $table";
		}
	}

	// 3. Check Encryption
	echo $colors['blue'] . "\n🔍 Checking Encryption...\n" . $colors['reset'];
	
	if ( ! FPML_Secure_Settings::is_encryption_available() ) {
		echo $colors['yellow'] . "  ⚠ OpenSSL not available\n" . $colors['reset'];
		$warnings[] = 'OpenSSL extension not available - API keys will not be encrypted';
	} else {
		echo $colors['green'] . "  ✓ OpenSSL available\n" . $colors['reset'];
		
		// Check if keys are encrypted
		$settings = get_option( 'fpml_settings', array() );
		$api_keys = array( 'openai_api_key', 'deepl_api_key', 'google_api_key', 'libretranslate_api_key' );
		
		$encrypted_count = 0;
		$plain_count     = 0;
		
		foreach ( $api_keys as $key ) {
			if ( ! empty( $settings[ $key ] ) ) {
				if ( 0 === strpos( $settings[ $key ], 'ENC:' ) ) {
					$encrypted_count++;
				} else {
					$plain_count++;
				}
			}
		}

		if ( $encrypted_count > 0 ) {
			echo $colors['green'] . "  ✓ $encrypted_count API key(s) encrypted\n" . $colors['reset'];
			$checks['encryption'] = true;
		}
		
		if ( $plain_count > 0 ) {
			echo $colors['yellow'] . "  ⚠ $plain_count API key(s) NOT encrypted\n" . $colors['reset'];
			$warnings[] = "$plain_count API keys need migration - run: php tools/migrate-api-keys.php";
		}
	}

	// 4. Check Settings
	echo $colors['blue'] . "\n🔍 Checking Settings...\n" . $colors['reset'];
	
	$settings = get_option( 'fpml_settings', array() );
	
	if ( empty( $settings ) ) {
		echo $colors['yellow'] . "  ⚠ No settings configured\n" . $colors['reset'];
		$warnings[] = 'Plugin settings not configured';
	} else {
		echo $colors['green'] . "  ✓ Settings configured\n" . $colors['reset'];
		
		// Check provider
		if ( empty( $settings['provider'] ) ) {
			echo $colors['yellow'] . "  ⚠ No translation provider configured\n" . $colors['reset'];
			$warnings[] = 'No translation provider selected';
		} else {
			echo $colors['green'] . "  ✓ Provider: {$settings['provider']}\n" . $colors['reset'];
		}
		
		$checks['settings'] = true;
	}

	// 5. Check Providers
	echo $colors['blue'] . "\n🔍 Checking Translation Providers...\n" . $colors['reset'];
	
	$provider_classes = array(
		'openai'         => 'FPML_Provider_OpenAI',
		'deepl'          => 'FPML_Provider_DeepL',
		'google'         => 'FPML_Provider_Google',
		'libretranslate' => 'FPML_Provider_LibreTranslate',
	);

	$available_providers = 0;
	foreach ( $provider_classes as $slug => $class ) {
		if ( class_exists( $class ) ) {
			$available_providers++;
		}
	}

	echo $colors['green'] . "  ✓ $available_providers provider(s) available\n" . $colors['reset'];
	$checks['providers'] = $available_providers > 0;

	// 6. Check Cache
	echo $colors['blue'] . "\n🔍 Checking Translation Cache...\n" . $colors['reset'];
	
	if ( class_exists( 'FPML_Translation_Cache' ) ) {
		$cache = FPML_Translation_Cache::instance();
		$stats = $cache->get_stats();
		
		echo $colors['green'] . "  ✓ Cache system active\n" . $colors['reset'];
		echo "    Cache size: " . $cache->get_cache_count() . " items\n";
		echo "    Hit rate: {$stats['hit_rate']}%\n";
		
		$checks['cache'] = true;
	} else {
		echo $colors['red'] . "  ✗ Cache system not available\n" . $colors['reset'];
		$errors[] = 'Translation cache not available';
	}

	// 7. Check Versioning
	echo $colors['blue'] . "\n🔍 Checking Translation Versioning...\n" . $colors['reset'];
	
	if ( class_exists( 'FPML_Translation_Versioning' ) ) {
		$versioning = FPML_Translation_Versioning::instance();
		$stats      = $versioning->get_stats();
		
		echo $colors['green'] . "  ✓ Versioning system active\n" . $colors['reset'];
		echo "    Total versions: {$stats['total_versions']}\n";
		
		$checks['versioning'] = true;
	} else {
		echo $colors['red'] . "  ✗ Versioning system not available\n" . $colors['reset'];
		$errors[] = 'Translation versioning not available';
	}

	// 8. Check REST API
	echo $colors['blue'] . "\n🔍 Checking REST API Endpoints...\n" . $colors['reset'];
	
	$endpoints = rest_get_server()->get_routes();
	$fpml_endpoints = array();
	
	foreach ( $endpoints as $route => $handlers ) {
		if ( 0 === strpos( $route, '/fpml/v1' ) ) {
			$fpml_endpoints[] = $route;
		}
	}

	if ( ! empty( $fpml_endpoints ) ) {
		echo $colors['green'] . "  ✓ REST API active (" . count( $fpml_endpoints ) . " endpoints)\n" . $colors['reset'];
		
		// Check for new preview endpoint
		if ( in_array( '/fpml/v1/preview-translation', $fpml_endpoints, true ) ) {
			echo $colors['green'] . "  ✓ Preview endpoint available\n" . $colors['reset'];
		} else {
			echo $colors['yellow'] . "  ⚠ Preview endpoint not found\n" . $colors['reset'];
			$warnings[] = 'Preview translation endpoint not registered';
		}
		
		$checks['rest_api'] = true;
	} else {
		echo $colors['red'] . "  ✗ No REST endpoints found\n" . $colors['reset'];
		$errors[] = 'REST API endpoints not registered';
	}

	// Summary
	echo $colors['cyan'] . $colors['bold'] . "\n═══════════════════════════════════════════════════\n" . $colors['reset'];
	echo $colors['bold'] . "📊 Summary\n" . $colors['reset'];
	echo $colors['cyan'] . "═══════════════════════════════════════════════════\n" . $colors['reset'];

	$total_checks = count( $checks );
	$passed       = count( array_filter( $checks ) );
	$percentage   = round( ( $passed / $total_checks ) * 100 );

	echo "\nChecks: $passed/$total_checks passed ($percentage%)\n";
	
	if ( ! empty( $errors ) ) {
		echo $colors['red'] . "\n❌ Errors (" . count( $errors ) . "):\n" . $colors['reset'];
		foreach ( $errors as $error ) {
			echo $colors['red'] . "  • $error\n" . $colors['reset'];
		}
	}

	if ( ! empty( $warnings ) ) {
		echo $colors['yellow'] . "\n⚠️  Warnings (" . count( $warnings ) . "):\n" . $colors['reset'];
		foreach ( $warnings as $warning ) {
			echo $colors['yellow'] . "  • $warning\n" . $colors['reset'];
		}
	}

	// Final verdict
	echo "\n";
	if ( empty( $errors ) && empty( $warnings ) ) {
		echo $colors['green'] . $colors['bold'] . "✅ READY FOR DEPLOYMENT\n" . $colors['reset'];
		echo $colors['green'] . "   All systems operational!\n\n" . $colors['reset'];
		return 0;
	} elseif ( empty( $errors ) ) {
		echo $colors['yellow'] . $colors['bold'] . "⚠️  DEPLOYMENT WITH WARNINGS\n" . $colors['reset'];
		echo $colors['yellow'] . "   Review warnings before deploying.\n\n" . $colors['reset'];
		return 1;
	} else {
		echo $colors['red'] . $colors['bold'] . "❌ NOT READY FOR DEPLOYMENT\n" . $colors['reset'];
		echo $colors['red'] . "   Fix errors before deploying!\n\n" . $colors['reset'];
		return 2;
	}
}

// Run if called directly
if ( php_sapi_name() === 'cli' ) {
	$exit_code = fpml_pre_deployment_check();
	exit( $exit_code );
}

// Run if called via WP-CLI
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	$exit_code = fpml_pre_deployment_check();
	if ( 0 === $exit_code ) {
		WP_CLI::success( 'Pre-deployment check passed!' );
	} elseif ( 1 === $exit_code ) {
		WP_CLI::warning( 'Pre-deployment check passed with warnings.' );
	} else {
		WP_CLI::error( 'Pre-deployment check failed!' );
	}
}
