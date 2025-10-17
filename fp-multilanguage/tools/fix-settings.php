<?php
/**
 * Fix Settings Tool
 * 
 * Questo script diagnostica e corregge i problemi di salvataggio
 * delle impostazioni del plugin FP Multilanguage.
 * 
 * Utilizzo:
 * php tools/fix-settings.php diagnose    - Diagnostica i problemi
 * php tools/fix-settings.php fix         - Corregge i problemi trovati
 * php tools/fix-settings.php test-save   - Testa il salvataggio
 * 
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @since 0.4.1
 */

// Assicurati che questo script sia eseguito da WordPress
if ( ! defined( 'ABSPATH' ) ) {
	// Trova il percorso di WordPress
	$wp_path = dirname( __FILE__ );
	$levels = 0;
	$max_levels = 10;
	
	while ( $levels < $max_levels ) {
		if ( file_exists( $wp_path . '/wp-config.php' ) ) {
			require_once $wp_path . '/wp-config.php';
			break;
		}
		$wp_path = dirname( $wp_path );
		$levels++;
	}
	
	if ( ! defined( 'ABSPATH' ) ) {
		die( 'Errore: Non riesco a trovare wp-config.php. Esegui questo script dalla cartella del plugin.' . "\n" );
	}
}

// Carica il plugin
if ( ! class_exists( 'FPML_Settings' ) ) {
	$plugin_file = dirname( __FILE__ ) . '/../fp-multilanguage.php';
	if ( file_exists( $plugin_file ) ) {
		require_once $plugin_file;
	} else {
		die( 'Errore: Non riesco a trovare il file del plugin.' . "\n" );
	}
}

/**
 * Mostra l'utilizzo dello script.
 */
function show_usage() {
	echo "Fix Settings Tool - FP Multilanguage\n";
	echo "===================================\n\n";
	echo "Utilizzo: php " . basename( __FILE__ ) . " <comando>\n\n";
	echo "Comandi disponibili:\n";
	echo "  diagnose   - Diagnostica i problemi delle impostazioni\n";
	echo "  fix        - Corregge i problemi trovati\n";
	echo "  test-save  - Testa il salvataggio delle impostazioni\n";
	echo "  status     - Mostra lo stato attuale delle impostazioni\n";
	echo "  help       - Mostra questo messaggio di aiuto\n\n";
}

/**
 * Diagnostica i problemi delle impostazioni.
 */
function diagnose_settings() {
	echo "🔍 Diagnostica Impostazioni FP Multilanguage\n";
	echo "==========================================\n\n";
	
	$issues = array();
	$warnings = array();
	
	// Verifica 1: Classe FPML_Settings
	echo "Verifica 1: Classe FPML_Settings\n";
	echo "--------------------------------\n";
	
	if ( class_exists( 'FPML_Settings' ) ) {
		echo "✅ Classe FPML_Settings trovata\n";
		
		$settings = FPML_Settings::instance();
		if ( $settings ) {
			echo "✅ Istanza della classe creata correttamente\n";
		} else {
			$issues[] = "Impossibile creare istanza di FPML_Settings";
			echo "❌ Impossibile creare istanza della classe\n";
		}
	} else {
		$issues[] = "Classe FPML_Settings non trovata";
		echo "❌ Classe FPML_Settings non trovata\n";
	}
	
	echo "\n";
	
	// Verifica 2: Registrazione delle impostazioni
	echo "Verifica 2: Registrazione delle impostazioni\n";
	echo "-------------------------------------------\n";
	
	global $wp_registered_settings;
	
	if ( isset( $wp_registered_settings[ FPML_Settings::OPTION_KEY ] ) ) {
		echo "✅ Impostazioni registrate in WordPress\n";
		
		$registered = $wp_registered_settings[ FPML_Settings::OPTION_KEY ];
		echo "   - Gruppo: " . $registered['group'] . "\n";
		echo "   - Sanitizzazione: " . ( isset( $registered['sanitize_callback'] ) ? 'Presente' : 'Assente' ) . "\n";
	} else {
		$issues[] = "Impostazioni non registrate in WordPress";
		echo "❌ Impostazioni NON registrate in WordPress\n";
	}
	
	echo "\n";
	
	// Verifica 3: Impostazioni nel database
	echo "Verifica 3: Impostazioni nel database\n";
	echo "------------------------------------\n";
	
	$current_settings = get_option( FPML_Settings::OPTION_KEY, array() );
	
	if ( ! empty( $current_settings ) ) {
		echo "✅ Impostazioni trovate nel database: " . count( $current_settings ) . " elementi\n";
		echo "   - Provider: " . ( isset( $current_settings['provider'] ) ? $current_settings['provider'] : 'Non impostato' ) . "\n";
		echo "   - Setup completato: " . ( isset( $current_settings['setup_completed'] ) ? ( $current_settings['setup_completed'] ? 'Sì' : 'No' ) : 'Non impostato' ) . "\n";
	} else {
		$warnings[] = "Nessuna impostazione trovata nel database";
		echo "⚠️  Nessuna impostazione trovata nel database\n";
	}
	
	echo "\n";
	
	// Verifica 4: Sistema di migrazione
	echo "Verifica 4: Sistema di migrazione\n";
	echo "--------------------------------\n";
	
	if ( class_exists( 'FPML_Settings_Migration' ) ) {
		echo "✅ Classe FPML_Settings_Migration trovata\n";
		
		$migration = FPML_Settings_Migration::instance();
		$backup = $migration->get_backup_info();
		
		if ( $backup ) {
			echo "✅ Backup trovato: " . count( $backup['settings'] ) . " impostazioni\n";
			echo "   - Data backup: " . $backup['timestamp'] . "\n";
		} else {
			echo "ℹ️  Nessun backup trovato\n";
		}
	} else {
		$warnings[] = "Sistema di migrazione non trovato";
		echo "⚠️  Classe FPML_Settings_Migration non trovata\n";
	}
	
	echo "\n";
	
	// Verifica 5: Sistema di fix
	echo "Verifica 5: Sistema di fix\n";
	echo "-------------------------\n";
	
	if ( class_exists( 'FPML_Settings_Fix' ) ) {
		echo "✅ Classe FPML_Settings_Fix trovata\n";
		
		$fix = FPML_Settings_Fix::instance();
		$status = $fix->check_settings_status();
		
		echo "   - Classe caricata: " . ( $status['class_loaded'] ? 'Sì' : 'No' ) . "\n";
		echo "   - Impostazioni registrate: " . ( $status['settings_registered'] ? 'Sì' : 'No' ) . "\n";
		echo "   - Impostazioni caricate: " . ( $status['settings_loaded'] ? 'Sì' : 'No' ) . "\n";
		echo "   - Provider configurato: " . ( $status['provider_configured'] ? 'Sì' : 'No' ) . "\n";
		echo "   - API Key configurata: " . ( $status['api_key_configured'] ? 'Sì' : 'No' ) . "\n";
	} else {
		$warnings[] = "Sistema di fix non trovato";
		echo "⚠️  Classe FPML_Settings_Fix non trovata\n";
	}
	
	echo "\n";
	
	// Riepilogo
	echo "📋 Riepilogo Diagnostica\n";
	echo "======================\n";
	
	if ( empty( $issues ) ) {
		echo "✅ Nessun problema critico trovato\n";
	} else {
		echo "❌ Problemi critici trovati:\n";
		foreach ( $issues as $issue ) {
			echo "   - $issue\n";
		}
	}
	
	if ( ! empty( $warnings ) ) {
		echo "\n⚠️  Avvisi:\n";
		foreach ( $warnings as $warning ) {
			echo "   - $warning\n";
		}
	}
	
	echo "\n";
	
	return array( 'issues' => $issues, 'warnings' => $warnings );
}

/**
 * Corregge i problemi trovati.
 */
function fix_settings() {
	echo "🔧 Correzione Problemi Impostazioni\n";
	echo "===================================\n\n";
	
	$fixed = 0;
	
	// Fix 1: Forza registrazione delle impostazioni
	echo "Fix 1: Registrazione delle impostazioni\n";
	echo "--------------------------------------\n";
	
	if ( class_exists( 'FPML_Settings' ) ) {
		$settings = FPML_Settings::instance();
		
		// Forza registrazione
		$settings->register_settings();
		
		global $wp_registered_settings;
		if ( isset( $wp_registered_settings[ FPML_Settings::OPTION_KEY ] ) ) {
			echo "✅ Impostazioni registrate correttamente\n";
			$fixed++;
		} else {
			echo "❌ Impossibile registrare le impostazioni\n";
		}
	} else {
		echo "❌ Classe FPML_Settings non disponibile\n";
	}
	
	echo "\n";
	
	// Fix 2: Verifica e correggi impostazioni
	echo "Fix 2: Verifica impostazioni\n";
	echo "----------------------------\n";
	
	$current_settings = get_option( FPML_Settings::OPTION_KEY, array() );
	$defaults = FPML_Settings::instance()->get_defaults();
	
	// Merge con i default per assicurarsi che tutte le opzioni siano presenti
	$merged_settings = wp_parse_args( $current_settings, $defaults );
	
	if ( $merged_settings !== $current_settings ) {
		update_option( FPML_Settings::OPTION_KEY, $merged_settings );
		echo "✅ Impostazioni corrette e sincronizzate con i default\n";
		$fixed++;
	} else {
		echo "ℹ️  Impostazioni già corrette\n";
	}
	
	echo "\n";
	
	// Fix 3: Crea backup se necessario
	echo "Fix 3: Backup delle impostazioni\n";
	echo "--------------------------------\n";
	
	if ( class_exists( 'FPML_Settings_Migration' ) ) {
		$migration = FPML_Settings_Migration::instance();
		$backup = $migration->get_backup_info();
		
		if ( ! $backup ) {
			$backup_result = $migration->force_backup();
			if ( $backup_result ) {
				echo "✅ Backup creato con successo\n";
				$fixed++;
			} else {
				echo "❌ Errore nella creazione del backup\n";
			}
		} else {
			echo "ℹ️  Backup già esistente\n";
		}
	} else {
		echo "⚠️  Sistema di migrazione non disponibile\n";
	}
	
	echo "\n";
	
	// Fix 4: Test salvataggio
	echo "Fix 4: Test salvataggio\n";
	echo "----------------------\n";
	
	$test_settings = array(
		'provider' => 'openai',
		'test_timestamp' => current_time( 'mysql' ),
		'setup_completed' => true,
	);
	
	$save_result = update_option( FPML_Settings::OPTION_KEY, $test_settings );
	
	if ( $save_result ) {
		$saved_settings = get_option( FPML_Settings::OPTION_KEY );
		if ( isset( $saved_settings['test_timestamp'] ) && $saved_settings['test_timestamp'] === $test_settings['test_timestamp'] ) {
			echo "✅ Salvataggio test riuscito\n";
			$fixed++;
		} else {
			echo "❌ Salvataggio test fallito\n";
		}
	} else {
		echo "❌ Errore nel salvataggio test\n";
	}
	
	echo "\n";
	
	echo "🎉 Correzione completata!\n";
	echo "========================\n";
	echo "Problemi corretti: $fixed\n\n";
}

/**
 * Testa il salvataggio delle impostazioni.
 */
function test_settings_save() {
	echo "🧪 Test Salvataggio Impostazioni\n";
	echo "================================\n\n";
	
	$tests_passed = 0;
	$total_tests = 4;
	
	// Test 1: Salvataggio manuale
	echo "Test 1: Salvataggio manuale\n";
	echo "--------------------------\n";
	
	$test_data = array(
		'provider' => 'google',
		'test_field' => 'test_value_' . time(),
		'setup_completed' => true,
	);
	
	$save_result = update_option( FPML_Settings::OPTION_KEY, $test_data );
	
	if ( $save_result ) {
		echo "✅ Salvataggio riuscito\n";
		$tests_passed++;
	} else {
		echo "❌ Salvataggio fallito\n";
	}
	
	echo "\n";
	
	// Test 2: Verifica salvataggio
	echo "Test 2: Verifica salvataggio\n";
	echo "----------------------------\n";
	
	$saved_data = get_option( FPML_Settings::OPTION_KEY );
	
	if ( isset( $saved_data['test_field'] ) && $saved_data['test_field'] === $test_data['test_field'] ) {
		echo "✅ Dati salvati correttamente\n";
		$tests_passed++;
	} else {
		echo "❌ Dati non salvati correttamente\n";
	}
	
	echo "\n";
	
	// Test 3: Sanitizzazione
	echo "Test 3: Sanitizzazione\n";
	echo "---------------------\n";
	
	if ( class_exists( 'FPML_Settings' ) ) {
		$settings = FPML_Settings::instance();
		
		$raw_input = array(
			'provider' => 'openai',
			'openai_api_key' => 'sk-1234567890',
			'routing_mode' => 'invalid_mode',
			'batch_size' => 'invalid_number',
		);
		
		$sanitized = $settings->sanitize( $raw_input );
		
		if ( $sanitized['routing_mode'] !== 'invalid_mode' && $sanitized['batch_size'] > 0 ) {
			echo "✅ Sanitizzazione funzionante\n";
			$tests_passed++;
		} else {
			echo "❌ Sanitizzazione non funzionante\n";
		}
	} else {
		echo "❌ Classe FPML_Settings non disponibile\n";
	}
	
	echo "\n";
	
	// Test 4: Sistema di fix
	echo "Test 4: Sistema di fix\n";
	echo "---------------------\n";
	
	if ( class_exists( 'FPML_Settings_Fix' ) ) {
		$fix = FPML_Settings_Fix::instance();
		$status = $fix->check_settings_status();
		
		if ( $status['class_loaded'] && $status['settings_registered'] ) {
			echo "✅ Sistema di fix funzionante\n";
			$tests_passed++;
		} else {
			echo "❌ Sistema di fix non funzionante\n";
		}
	} else {
		echo "❌ Sistema di fix non disponibile\n";
	}
	
	echo "\n";
	
	echo "📊 Risultati Test\n";
	echo "================\n";
	echo "Test superati: $tests_passed/$total_tests\n";
	
	if ( $tests_passed === $total_tests ) {
		echo "✅ Tutti i test superati!\n";
	} else {
		echo "❌ Alcuni test falliti\n";
	}
	
	echo "\n";
}

/**
 * Mostra lo stato attuale delle impostazioni.
 */
function show_settings_status() {
	echo "📊 Stato Impostazioni FP Multilanguage\n";
	echo "=====================================\n\n";
	
	if ( class_exists( 'FPML_Settings' ) ) {
		$settings = FPML_Settings::instance();
		$current_settings = $settings->all();
		
		echo "Impostazioni attuali:\n";
		echo "--------------------\n";
		
		foreach ( $current_settings as $key => $value ) {
			if ( is_bool( $value ) ) {
				$value = $value ? 'true' : 'false';
			} elseif ( is_array( $value ) ) {
				$value = 'array(' . count( $value ) . ' elementi)';
			} elseif ( strpos( $key, 'api_key' ) !== false ) {
				$value = ! empty( $value ) ? '***' . substr( $value, -4 ) : 'vuoto';
			}
			
			echo "  $key: $value\n";
		}
	} else {
		echo "❌ Classe FPML_Settings non disponibile\n";
	}
	
	echo "\n";
}

// Gestione dei comandi
if ( $argc < 2 ) {
	show_usage();
	exit( 1 );
}

$command = strtolower( trim( $argv[1] ) );

switch ( $command ) {
	case 'diagnose':
		diagnose_settings();
		break;
		
	case 'fix':
		fix_settings();
		break;
		
	case 'test-save':
		test_settings_save();
		break;
		
	case 'status':
		show_settings_status();
		break;
		
	case 'help':
	default:
		show_usage();
		break;
}

echo "Script completato.\n";
