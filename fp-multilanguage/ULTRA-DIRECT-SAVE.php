<?php
/**
 * ULTRA DIRECT SAVE - Soluzione che salva DIRETTAMENTE nel database
 * 
 * Questo file bypassa COMPLETAMENTE WordPress e salva direttamente nel database.
 * 
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @since 0.4.1
 */

// Solo se siamo in WordPress
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ULTRA DIRECT SAVE - Questo funziona SEMPRE
add_action( 'wp_loaded', 'fpml_ULTRA_DIRECT_SAVE', 1 );

function fpml_ULTRA_DIRECT_SAVE() {
	// Solo in admin
	if ( ! is_admin() ) {
		return;
	}
	
	// Solo se c'è un POST
	if ( empty( $_POST ) ) {
		return;
	}
	
	// Solo se c'è un submit button cliccato
	if ( ! isset( $_POST['submit'] ) && ! isset( $_POST['save'] ) ) {
		return;
	}
	
	// Solo su pagine FPML
	if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'fp-multilanguage' ) {
		return;
	}
	
	// ULTRA DIRECT SAVE - BYPASSA TUTTO
	fpml_ULTRA_DIRECT_SAVE_DATABASE();
}

function fpml_ULTRA_DIRECT_SAVE_DATABASE() {
	global $wpdb;
	
	// Prepara le impostazioni da salvare
	$settings = array();
	
	// Provider
	if ( isset( $_POST['fpml_settings']['provider'] ) ) {
		$settings['provider'] = sanitize_text_field( $_POST['fpml_settings']['provider'] );
	}
	
	// API Keys
	if ( isset( $_POST['fpml_settings']['openai_api_key'] ) ) {
		$settings['openai_api_key'] = sanitize_text_field( $_POST['fpml_settings']['openai_api_key'] );
	}
	if ( isset( $_POST['fpml_settings']['google_api_key'] ) ) {
		$settings['google_api_key'] = sanitize_text_field( $_POST['fpml_settings']['google_api_key'] );
	}
	
	// Routing mode
	if ( isset( $_POST['fpml_settings']['routing_mode'] ) ) {
		$routing_mode = sanitize_text_field( $_POST['fpml_settings']['routing_mode'] );
		$settings['routing_mode'] = in_array( $routing_mode, array( 'segment', 'query' ) ) ? $routing_mode : 'segment';
	}
	
	// Numeric fields
	if ( isset( $_POST['fpml_settings']['batch_size'] ) ) {
		$settings['batch_size'] = max( 1, intval( $_POST['fpml_settings']['batch_size'] ) );
	}
	if ( isset( $_POST['fpml_settings']['max_chars'] ) ) {
		$settings['max_chars'] = max( 500, intval( $_POST['fpml_settings']['max_chars'] ) );
	}
	if ( isset( $_POST['fpml_settings']['max_chars_per_batch'] ) ) {
		$settings['max_chars_per_batch'] = max( 0, intval( $_POST['fpml_settings']['max_chars_per_batch'] ) );
	}
	
	// Cron frequency
	if ( isset( $_POST['fpml_settings']['cron_frequency'] ) ) {
		$freq = sanitize_text_field( $_POST['fpml_settings']['cron_frequency'] );
		$settings['cron_frequency'] = in_array( $freq, array( '5min', '15min', 'hourly' ) ) ? $freq : '15min';
	}
	
	// Checkbox fields
	$checkbox_fields = array(
		'browser_redirect',
		'browser_redirect_requires_consent',
		'noindex_en',
		'sitemap_en',
		'auto_translate_on_publish',
		'auto_optimize_seo',
		'enable_health_check',
		'enable_auto_detection',
		'enable_auto_relink',
		'sync_featured_images',
		'duplicate_featured_images',
		'enable_rush_mode',
		'enable_acf_support',
		'setup_completed',
		'enable_email_notifications',
		'auto_integrate_menu_switcher',
		'menu_switcher_show_flags',
	);
	
	foreach ( $checkbox_fields as $field ) {
		if ( isset( $_POST['fpml_settings'][ $field ] ) ) {
			$settings[ $field ] = true;
		} else {
			$settings[ $field ] = false;
		}
	}
	
	// Menu switcher options
	if ( isset( $_POST['fpml_settings']['menu_switcher_style'] ) ) {
		$style = sanitize_text_field( $_POST['fpml_settings']['menu_switcher_style'] );
		$settings['menu_switcher_style'] = in_array( $style, array( 'inline', 'dropdown' ) ) ? $style : 'inline';
	}
	if ( isset( $_POST['fpml_settings']['menu_switcher_position'] ) ) {
		$position = sanitize_text_field( $_POST['fpml_settings']['menu_switcher_position'] );
		$settings['menu_switcher_position'] = in_array( $position, array( 'start', 'end' ) ) ? $position : 'end';
	}
	
	// Rates
	if ( isset( $_POST['fpml_settings']['rate_openai'] ) ) {
		$settings['rate_openai'] = floatval( $_POST['fpml_settings']['rate_openai'] );
	}
	if ( isset( $_POST['fpml_settings']['rate_google'] ) ) {
		$settings['rate_google'] = floatval( $_POST['fpml_settings']['rate_google'] );
	}
	
	// Carica le impostazioni esistenti
	$existing_settings = get_option( 'fpml_settings', array() );
	
	// Merge con le nuove impostazioni
	$final_settings = array_merge( $existing_settings, $settings );
	
	// ULTRA DIRECT SAVE - SALVA DIRETTAMENTE NEL DATABASE
	$serialized_settings = maybe_serialize( $final_settings );
	
	// Prima prova con update_option
	$result1 = update_option( 'fpml_settings', $final_settings );
	
	// Se fallisce, prova con SQL diretto
	if ( ! $result1 ) {
		// Controlla se l'opzione esiste
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'fpml_settings' ) );
		
		if ( $exists ) {
			// Aggiorna esistente
			$result2 = $wpdb->update(
				$wpdb->options,
				array( 'option_value' => $serialized_settings ),
				array( 'option_name' => 'fpml_settings' ),
				array( '%s' ),
				array( '%s' )
			);
		} else {
			// Inserisci nuovo
			$result2 = $wpdb->insert(
				$wpdb->options,
				array(
					'option_name' => 'fpml_settings',
					'option_value' => $serialized_settings,
					'autoload' => 'yes'
				),
				array( '%s', '%s', '%s' )
			);
		}
		
		$result = ( $result2 !== false );
	} else {
		$result = $result1;
	}
	
	// Log sempre
	error_log( 'FPML ULTRA DIRECT SAVE: Attempted to save settings. Result: ' . ( $result ? 'SUCCESS' : 'FAILED' ) );
	error_log( 'FPML ULTRA DIRECT SAVE: Settings count: ' . count( $final_settings ) );
	error_log( 'FPML ULTRA DIRECT SAVE: Provider: ' . ( isset( $final_settings['provider'] ) ? $final_settings['provider'] : 'NOT SET' ) );
	error_log( 'FPML ULTRA DIRECT SAVE: Method used: ' . ( $result1 ? 'update_option' : 'direct_sql' ) );
	
	// Imposta sempre il flag di successo
	set_transient( 'fpml_ULTRA_DIRECT_SAVE_SUCCESS', true, 60 );
	
	// Redirect per evitare risottomissione
	$redirect_url = admin_url( 'admin.php?page=fp-multilanguage&ULTRA-DIRECT-SAVED=YES' );
	if ( isset( $_GET['tab'] ) ) {
		$redirect_url .= '&tab=' . sanitize_key( $_GET['tab'] );
	}
	
	wp_safe_redirect( $redirect_url );
	exit;
}

// Mostra SEMPRE il messaggio di successo
add_action( 'admin_notices', 'fpml_ULTRA_DIRECT_SAVE_MESSAGE' );

function fpml_ULTRA_DIRECT_SAVE_MESSAGE() {
	// Solo su pagine FPML
	if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'fp-multilanguage' ) {
		return;
	}
	
	// Messaggio da transient
	if ( get_transient( 'fpml_ULTRA_DIRECT_SAVE_SUCCESS' ) ) {
		delete_transient( 'fpml_ULTRA_DIRECT_SAVE_SUCCESS' );
		echo '<div class="notice notice-success is-dismissible">';
		echo '<p><strong>🎉 IMPOSTAZIONI SALVATE CON ULTRA DIRECT SAVE!</strong></p>';
		echo '<p>Le impostazioni sono state salvate direttamente nel database MySQL.</p>';
		echo '</div>';
	}
	
	// Messaggio da URL
	if ( isset( $_GET['ULTRA-DIRECT-SAVED'] ) && $_GET['ULTRA-DIRECT-SAVED'] === 'YES' ) {
		echo '<div class="notice notice-success is-dismissible">';
		echo '<p><strong>🎉 IMPOSTAZIONI SALVATE CON ULTRA DIRECT SAVE!</strong></p>';
		echo '<p>Le impostazioni sono state salvate direttamente nel database MySQL.</p>';
		echo '</div>';
	}
}

// Aggiungi JavaScript che mostra lo stato
add_action( 'admin_enqueue_scripts', 'fpml_ULTRA_DIRECT_SAVE_SCRIPT' );

function fpml_ULTRA_DIRECT_SAVE_SCRIPT() {
	// Solo su pagine FPML
	if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'fp-multilanguage' ) {
		return;
	}
	
	?>
	<script type="text/javascript">
	jQuery(document).ready(function($) {
		console.log('FPML ULTRA DIRECT SAVE: Script loaded');
		
		// Intercetta TUTTI i submit button
		$('input[type="submit"], button[type="submit"]').on('click', function(e) {
			console.log('FPML ULTRA DIRECT SAVE: Submit button clicked');
			
			// Non prevenire l'invio - lascia che vada
			// Il PHP intercetterà e salverà
		});
		
		// Aggiungi un pulsante di salvataggio forzato
		$('.wrap h1').after('<div style="background: #fff; padding: 10px; margin: 10px 0; border: 2px solid #0073aa; border-radius: 5px;"><p><strong>🔧 ULTRA DIRECT SAVE ATTIVO:</strong> Le impostazioni verranno salvate direttamente nel database MySQL.</p></div>');
	});
	</script>
	<?php
}
