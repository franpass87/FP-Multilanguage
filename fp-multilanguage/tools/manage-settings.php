<?php
/**
 * Settings Management Tool
 * 
 * Questo script permette di gestire manualmente il backup e ripristino
 * delle impostazioni del plugin FP Multilanguage.
 * 
 * Utilizzo:
 * php tools/manage-settings.php backup    - Crea backup delle impostazioni
 * php tools/manage-settings.php restore   - Ripristina dalle impostazioni di backup
 * php tools/manage-settings.php info      - Mostra informazioni sul backup
 * php tools/manage-settings.php clear     - Cancella il backup
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
if ( ! class_exists( 'FPML_Settings_Migration' ) ) {
	$plugin_file = dirname( __FILE__ ) . '/../fp-multilanguage.php';
	if ( file_exists( $plugin_file ) ) {
		require_once $plugin_file;
	} else {
		die( 'Errore: Non riesco a trovare il file del plugin.' . "\n" );
	}
}

// Verifica che la classe esista
if ( ! class_exists( 'FPML_Settings_Migration' ) ) {
	die( 'Errore: Classe FPML_Settings_Migration non trovata. Assicurati che il plugin sia caricato correttamente.' . "\n" );
}

/**
 * Mostra l'utilizzo dello script.
 */
function show_usage() {
	echo "Gestione Impostazioni FP Multilanguage\n";
	echo "=====================================\n\n";
	echo "Utilizzo: php " . basename( __FILE__ ) . " <comando>\n\n";
	echo "Comandi disponibili:\n";
	echo "  backup   - Crea un backup delle impostazioni attuali\n";
	echo "  restore  - Ripristina le impostazioni dal backup\n";
	echo "  info     - Mostra informazioni sul backup esistente\n";
	echo "  clear    - Cancella il backup esistente\n";
	echo "  help     - Mostra questo messaggio di aiuto\n\n";
}

/**
 * Formatta una data per la visualizzazione.
 */
function format_date( $date_string ) {
	if ( empty( $date_string ) ) {
		return 'Non disponibile';
	}
	
	try {
		$date = new DateTime( $date_string );
		return $date->format( 'd/m/Y H:i:s' );
	} catch ( Exception $e ) {
		return $date_string;
	}
}

/**
 * Mostra informazioni dettagliate sul backup.
 */
function show_backup_info() {
	$migration = FPML_Settings_Migration::instance();
	$backup = $migration->get_backup_info();
	
	if ( ! $backup ) {
		echo "❌ Nessun backup trovato.\n\n";
		return;
	}
	
	echo "📋 Informazioni Backup\n";
	echo "=====================\n\n";
	
	echo "Data creazione: " . format_date( $backup['timestamp'] ) . "\n";
	echo "Versione plugin: " . ( isset( $backup['version'] ) ? $backup['version'] : 'Sconosciuta' ) . "\n";
	
	if ( isset( $backup['settings'] ) && is_array( $backup['settings'] ) ) {
		$settings_count = count( $backup['settings'] );
		echo "Numero impostazioni: $settings_count\n";
		
		// Mostra alcune impostazioni chiave
		$key_settings = array(
			'provider' => 'Provider di traduzione',
			'routing_mode' => 'Modalità routing',
			'setup_completed' => 'Setup completato',
			'auto_translate_on_publish' => 'Traduzione automatica',
		);
		
		echo "\nImpostazioni principali:\n";
		foreach ( $key_settings as $key => $label ) {
			$value = isset( $backup['settings'][ $key ] ) ? $backup['settings'][ $key ] : 'Non impostato';
			echo "  • $label: $value\n";
		}
	}
	
	echo "\n";
}

/**
 * Crea un backup delle impostazioni.
 */
function create_backup() {
	$migration = FPML_Settings_Migration::instance();
	
	echo "🔄 Creazione backup impostazioni...\n";
	
	$success = $migration->force_backup();
	
	if ( $success ) {
		echo "✅ Backup creato con successo!\n\n";
		show_backup_info();
	} else {
		echo "❌ Errore durante la creazione del backup.\n\n";
	}
}

/**
 * Ripristina le impostazioni dal backup.
 */
function restore_settings() {
	$migration = FPML_Settings_Migration::instance();
	$backup = $migration->get_backup_info();
	
	if ( ! $backup ) {
		echo "❌ Nessun backup trovato. Impossibile ripristinare.\n\n";
		return;
	}
	
	echo "⚠️  ATTENZIONE: Questa operazione sovrascriverà le impostazioni attuali!\n";
	echo "Data backup: " . format_date( $backup['timestamp'] ) . "\n";
	echo "Sei sicuro di voler continuare? (sì/no): ";
	
	$handle = fopen( "php://stdin", "r" );
	$line = fgets( $handle );
	fclose( $handle );
	
	$response = trim( strtolower( $line ) );
	
	if ( ! in_array( $response, array( 'sì', 'si', 'yes', 'y' ) ) ) {
		echo "Operazione annullata.\n\n";
		return;
	}
	
	echo "\n🔄 Ripristino impostazioni...\n";
	
	// Forza il ripristino
	$success = $migration->maybe_restore_settings();
	
	if ( $success ) {
		echo "✅ Impostazioni ripristinate con successo!\n\n";
	} else {
		echo "❌ Errore durante il ripristino delle impostazioni.\n\n";
	}
}

/**
 * Cancella il backup esistente.
 */
function clear_backup() {
	$migration = FPML_Settings_Migration::instance();
	$backup = $migration->get_backup_info();
	
	if ( ! $backup ) {
		echo "❌ Nessun backup trovato.\n\n";
		return;
	}
	
	echo "⚠️  Sei sicuro di voler cancellare il backup?\n";
	echo "Data backup: " . format_date( $backup['timestamp'] ) . "\n";
	echo "Questa operazione è irreversibile! (sì/no): ";
	
	$handle = fopen( "php://stdin", "r" );
	$line = fgets( $handle );
	fclose( $handle );
	
	$response = trim( strtolower( $line ) );
	
	if ( ! in_array( $response, array( 'sì', 'si', 'yes', 'y' ) ) ) {
		echo "Operazione annullata.\n\n";
		return;
	}
	
	echo "\n🔄 Cancellazione backup...\n";
	
	$success = $migration->clear_backup();
	
	if ( $success ) {
		echo "✅ Backup cancellato con successo!\n\n";
	} else {
		echo "❌ Errore durante la cancellazione del backup.\n\n";
	}
}

// Gestione dei comandi
if ( $argc < 2 ) {
	show_usage();
	exit( 1 );
}

$command = strtolower( trim( $argv[1] ) );

switch ( $command ) {
	case 'backup':
		create_backup();
		break;
		
	case 'restore':
		restore_settings();
		break;
		
	case 'info':
		show_backup_info();
		break;
		
	case 'clear':
		clear_backup();
		break;
		
	case 'help':
	default:
		show_usage();
		break;
}

echo "Script completato.\n";
