<?php
/**
 * Migration script to encrypt existing API keys.
 *
 * Usage:
 *   php tools/migrate-api-keys.php
 *   wp eval-file tools/migrate-api-keys.php
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
 * Main migration function.
 */
function fpml_migrate_api_keys_main() {
	// Colors for CLI output
	$colors = array(
		'reset'   => "\033[0m",
		'red'     => "\033[31m",
		'green'   => "\033[32m",
		'yellow'  => "\033[33m",
		'blue'    => "\033[34m",
		'magenta' => "\033[35m",
		'cyan'    => "\033[36m",
	);

	echo $colors['cyan'] . "\n";
	echo "╔════════════════════════════════════════════════╗\n";
	echo "║  FP Multilanguage - API Keys Migration Tool   ║\n";
	echo "║  Version 0.4.1                                 ║\n";
	echo "╚════════════════════════════════════════════════╝\n";
	echo $colors['reset'] . "\n";

	// Check if secure settings class exists
	if ( ! class_exists( 'FPML_Secure_Settings' ) ) {
		echo $colors['red'] . "✗ Error: FPML_Secure_Settings class not found.\n" . $colors['reset'];
		echo "  Make sure the plugin is properly installed.\n\n";
		return false;
	}

	// Check if encryption is available
	if ( ! FPML_Secure_Settings::is_encryption_available() ) {
		echo $colors['yellow'] . "⚠ Warning: OpenSSL not available.\n" . $colors['reset'];
		echo "  API keys will not be encrypted.\n";
		echo "  Install OpenSSL extension to enable encryption.\n\n";
		return false;
	}

	echo $colors['green'] . "✓ OpenSSL is available\n" . $colors['reset'];

	// Get current settings
	$settings = get_option( 'fpml_settings', array() );

	if ( empty( $settings ) ) {
		echo $colors['yellow'] . "\n⚠ No settings found.\n" . $colors['reset'];
		echo "  Configure the plugin first.\n\n";
		return false;
	}

	// Check which keys need migration
	$keys_to_migrate = array(
		'openai_api_key',
		'google_api_key',
	);

	$plain_text_keys = array();
	$already_encrypted = array();
	$empty_keys = array();

	foreach ( $keys_to_migrate as $key ) {
		if ( ! isset( $settings[ $key ] ) || empty( $settings[ $key ] ) ) {
			$empty_keys[] = $key;
			continue;
		}

		if ( 0 === strpos( $settings[ $key ], 'ENC:' ) ) {
			$already_encrypted[] = $key;
		} else {
			$plain_text_keys[] = $key;
		}
	}

	// Show summary
	echo $colors['cyan'] . "\n📊 Migration Summary:\n" . $colors['reset'];
	echo "  • Total API keys: " . count( $keys_to_migrate ) . "\n";
	echo "  • Empty: " . $colors['yellow'] . count( $empty_keys ) . $colors['reset'] . "\n";
	echo "  • Already encrypted: " . $colors['green'] . count( $already_encrypted ) . $colors['reset'] . "\n";
	echo "  • To migrate: " . $colors['blue'] . count( $plain_text_keys ) . $colors['reset'] . "\n\n";

	if ( ! empty( $already_encrypted ) ) {
		echo $colors['green'] . "Already encrypted:\n" . $colors['reset'];
		foreach ( $already_encrypted as $key ) {
			echo "  ✓ $key\n";
		}
		echo "\n";
	}

	if ( empty( $plain_text_keys ) ) {
		echo $colors['green'] . "✓ All API keys are already encrypted or empty.\n" . $colors['reset'];
		echo "  No migration needed.\n\n";
		return true;
	}

	// Show keys to migrate
	echo $colors['yellow'] . "Keys to encrypt:\n" . $colors['reset'];
	foreach ( $plain_text_keys as $key ) {
		$length = mb_strlen( $settings[ $key ] );
		$masked = substr( $settings[ $key ], 0, 8 ) . str_repeat( '*', max( 0, $length - 8 ) );
		echo "  • $key: $masked\n";
	}

	// Ask for confirmation (skip in non-interactive mode)
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		$confirm = true;
	} elseif ( php_sapi_name() === 'cli' && function_exists( 'readline' ) ) {
		echo "\n" . $colors['yellow'] . "⚠ This will encrypt your API keys in the database.\n" . $colors['reset'];
		echo "  Make sure you have a backup before proceeding.\n\n";
		
		$response = readline( "Do you want to continue? [y/N]: " );
		$confirm  = in_array( strtolower( trim( $response ) ), array( 'y', 'yes' ), true );
	} else {
		// Auto-confirm if non-interactive
		$confirm = true;
	}

	if ( ! $confirm ) {
		echo "\n" . $colors['red'] . "✗ Migration cancelled.\n" . $colors['reset'] . "\n";
		return false;
	}

	// Create backup
	echo "\n" . $colors['blue'] . "Creating backup...\n" . $colors['reset'];
	$backup_file = WP_CONTENT_DIR . '/fpml-settings-backup-' . date( 'Y-m-d-His' ) . '.json';
	$backup_data = array(
		'timestamp' => current_time( 'mysql' ),
		'version'   => defined( 'FPML_PLUGIN_VERSION' ) ? FPML_PLUGIN_VERSION : 'unknown',
		'settings'  => $settings,
	);

	$backup_result = file_put_contents( $backup_file, wp_json_encode( $backup_data, JSON_PRETTY_PRINT ) );

	if ( $backup_result ) {
		echo $colors['green'] . "✓ Backup created: $backup_file\n" . $colors['reset'];
	} else {
		echo $colors['red'] . "✗ Failed to create backup.\n" . $colors['reset'];
		echo "  Aborting migration for safety.\n\n";
		return false;
	}

	// Perform migration
	echo "\n" . $colors['blue'] . "Migrating API keys...\n" . $colors['reset'];
	
	$secure_settings = FPML_Secure_Settings::instance();
	$migrated_count  = $secure_settings->migrate_existing_keys();

	if ( $migrated_count > 0 ) {
		echo $colors['green'] . "✓ Successfully migrated $migrated_count API key(s)\n" . $colors['reset'];
		
		// Verify encryption
		echo "\n" . $colors['blue'] . "Verifying encryption...\n" . $colors['reset'];
		$updated_settings = get_option( 'fpml_settings', array() );
		
		$all_encrypted = true;
		foreach ( $plain_text_keys as $key ) {
			if ( isset( $updated_settings[ $key ] ) && ! empty( $updated_settings[ $key ] ) ) {
				if ( 0 === strpos( $updated_settings[ $key ], 'ENC:' ) ) {
					echo $colors['green'] . "  ✓ $key is encrypted\n" . $colors['reset'];
				} else {
					echo $colors['red'] . "  ✗ $key is NOT encrypted\n" . $colors['reset'];
					$all_encrypted = false;
				}
			}
		}

		if ( $all_encrypted ) {
			echo "\n" . $colors['green'] . "✓ Migration completed successfully!\n" . $colors['reset'];
			echo "  All API keys are now encrypted.\n";
			echo "  Backup saved to: $backup_file\n\n";
			
			// Show next steps
			echo $colors['cyan'] . "📝 Next Steps:\n" . $colors['reset'];
			echo "  1. Test your translation providers in the plugin settings\n";
			echo "  2. Verify everything works correctly\n";
			echo "  3. Keep the backup file in a secure location\n";
			echo "  4. You can delete the backup after confirming everything works\n\n";
			
			return true;
		} else {
			echo "\n" . $colors['red'] . "✗ Migration completed with errors.\n" . $colors['reset'];
			echo "  Some keys were not encrypted properly.\n";
			echo "  Check the output above for details.\n\n";
			
			return false;
		}
	} else {
		echo $colors['yellow'] . "⚠ No keys were migrated.\n" . $colors['reset'];
		echo "  This could mean:\n";
		echo "  - Keys were already encrypted\n";
		echo "  - Settings are empty\n";
		echo "  - An error occurred\n\n";
		
		return false;
	}
}

// Run if called directly
if ( php_sapi_name() === 'cli' ) {
	$result = fpml_migrate_api_keys_main();
	exit( $result ? 0 : 1 );
}

// Run if called via WP-CLI
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	fpml_migrate_api_keys_main();
}
