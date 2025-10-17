<?php
/**
 * Ultra Simple Save - Soluzione ultra-semplice per salvare le impostazioni
 * 
 * Questo file bypassa completamente tutti i sistemi complessi e salva direttamente.
 * 
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @since 0.4.1
 */

// Solo se siamo in WordPress admin
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Solo se siamo sulla pagina delle impostazioni FPML
if ( ! is_admin() || ! isset( $_GET['page'] ) || $_GET['page'] !== 'fp-multilanguage' ) {
	return;
}

/**
 * Gestisce il salvataggio ultra-semplice delle impostazioni
 */
function fpml_ultra_simple_save() {
	// Solo se è stata inviata una form
	if ( ! isset( $_POST['fpml_ultra_save'] ) ) {
		return;
	}
	
	// Verifica nonce
	if ( ! wp_verify_nonce( $_POST['fpml_ultra_nonce'], 'fpml_ultra_save' ) ) {
		wp_die( 'Errore di sicurezza.' );
	}
	
	// Prepara le impostazioni da salvare
	$settings = array();
	
	// Provider
	if ( isset( $_POST['provider'] ) ) {
		$settings['provider'] = sanitize_text_field( $_POST['provider'] );
	}
	
	// API Keys
	if ( isset( $_POST['openai_api_key'] ) ) {
		$settings['openai_api_key'] = sanitize_text_field( $_POST['openai_api_key'] );
	}
	if ( isset( $_POST['google_api_key'] ) ) {
		$settings['google_api_key'] = sanitize_text_field( $_POST['google_api_key'] );
	}
	
	// Routing mode
	if ( isset( $_POST['routing_mode'] ) ) {
		$routing_mode = sanitize_text_field( $_POST['routing_mode'] );
		$settings['routing_mode'] = in_array( $routing_mode, array( 'segment', 'query' ) ) ? $routing_mode : 'segment';
	}
	
	// Numeric fields
	if ( isset( $_POST['batch_size'] ) ) {
		$settings['batch_size'] = max( 1, absint( $_POST['batch_size'] ) );
	}
	if ( isset( $_POST['max_chars'] ) ) {
		$settings['max_chars'] = max( 500, absint( $_POST['max_chars'] ) );
	}
	if ( isset( $_POST['max_chars_per_batch'] ) ) {
		$settings['max_chars_per_batch'] = max( 0, absint( $_POST['max_chars_per_batch'] ) );
	}
	
	// Cron frequency
	if ( isset( $_POST['cron_frequency'] ) ) {
		$freq = sanitize_text_field( $_POST['cron_frequency'] );
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
		$settings[ $field ] = isset( $_POST[ $field ] ) ? true : false;
	}
	
	// Menu switcher options
	if ( isset( $_POST['menu_switcher_style'] ) ) {
		$style = sanitize_text_field( $_POST['menu_switcher_style'] );
		$settings['menu_switcher_style'] = in_array( $style, array( 'inline', 'dropdown' ) ) ? $style : 'inline';
	}
	if ( isset( $_POST['menu_switcher_position'] ) ) {
		$position = sanitize_text_field( $_POST['menu_switcher_position'] );
		$settings['menu_switcher_position'] = in_array( $position, array( 'start', 'end' ) ) ? $position : 'end';
	}
	
	// Rates
	if ( isset( $_POST['rate_openai'] ) ) {
		$settings['rate_openai'] = floatval( $_POST['rate_openai'] );
	}
	if ( isset( $_POST['rate_google'] ) ) {
		$settings['rate_google'] = floatval( $_POST['rate_google'] );
	}
	
	// Carica le impostazioni esistenti
	$existing_settings = get_option( 'fpml_settings', array() );
	
	// Merge con le nuove impostazioni
	$final_settings = array_merge( $existing_settings, $settings );
	
	// Salva direttamente nel database
	$result = update_option( 'fpml_settings', $final_settings );
	
	if ( $result ) {
		// Imposta flag di successo
		set_transient( 'fpml_ultra_save_success', true, 30 );
		
		// Log
		error_log( 'FPML: Settings saved successfully via ultra simple method' );
	} else {
		// Imposta flag di errore
		set_transient( 'fpml_ultra_save_error', true, 30 );
		
		// Log
		error_log( 'FPML: Failed to save settings via ultra simple method' );
	}
	
	// Redirect
	$redirect_url = admin_url( 'admin.php?page=fp-multilanguage&ultra-save=success' );
	if ( isset( $_GET['tab'] ) ) {
		$redirect_url .= '&tab=' . sanitize_key( $_GET['tab'] );
	}
	wp_safe_redirect( $redirect_url );
	exit;
}

/**
 * Mostra i messaggi di salvataggio
 */
function fpml_ultra_simple_messages() {
	// Solo su pagine FPML
	if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'fp-multilanguage' ) {
		return;
	}
	
	// Messaggio di successo da transient
	if ( get_transient( 'fpml_ultra_save_success' ) ) {
		delete_transient( 'fpml_ultra_save_success' );
		echo '<div class="notice notice-success is-dismissible">';
		echo '<p><strong>✅ Impostazioni salvate con successo! (Ultra Simple Method)</strong></p>';
		echo '</div>';
	}
	
	// Messaggio di errore da transient
	if ( get_transient( 'fpml_ultra_save_error' ) ) {
		delete_transient( 'fpml_ultra_save_error' );
		echo '<div class="notice notice-error is-dismissible">';
		echo '<p><strong>❌ Errore nel salvataggio delle impostazioni. Riprova.</strong></p>';
		echo '</div>';
	}
	
	// Messaggio di successo da URL
	if ( isset( $_GET['ultra-save'] ) && $_GET['ultra-save'] === 'success' ) {
		echo '<div class="notice notice-success is-dismissible">';
		echo '<p><strong>✅ Impostazioni salvate con successo! (Ultra Simple Method)</strong></p>';
		echo '</div>';
	}
}

/**
 * Aggiunge JavaScript per intercettare i form
 */
function fpml_ultra_simple_script() {
	// Solo su pagine FPML
	if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'fp-multilanguage' ) {
		return;
	}
	
	?>
	<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Aggiungi form nascosto
		$('body').append('<form id="fpml-ultra-form" method="post" style="display:none;">' +
			'<input type="hidden" name="fpml_ultra_save" value="1">' +
			'<input type="hidden" name="fpml_ultra_nonce" value="<?php echo wp_create_nonce( 'fpml_ultra_save' ); ?>">' +
			'</form>');

		// Intercetta tutti i submit button
		$('input[type="submit"]').on('click', function(e) {
			e.preventDefault();
			
			console.log('FPML: Intercepting form submission via ultra simple method');
			
			// Raccogli dati
			var formData = {};
			
			// Input text, password, select, textarea
			$('input[type="text"], input[type="password"], select, textarea').each(function() {
				var name = $(this).attr('name');
				if (name && name.indexOf('fpml_settings') === 0) {
					var key = name.replace('fpml_settings[', '').replace(']', '');
					formData[key] = $(this).val();
				}
			});
			
			// Checkbox
			$('input[type="checkbox"]').each(function() {
				var name = $(this).attr('name');
				if (name && name.indexOf('fpml_settings') === 0) {
					var key = name.replace('fpml_settings[', '').replace(']', '');
					if ($(this).is(':checked')) {
						formData[key] = '1';
					}
				}
			});
			
			// Aggiungi dati al form nascosto
			var hiddenForm = $('#fpml-ultra-form');
			$.each(formData, function(key, value) {
				hiddenForm.append('<input type="hidden" name="' + key + '" value="' + value + '">');
			});
			
			console.log('FPML: Collected data:', formData);
			
			// Invia
			hiddenForm.submit();
		});
	});
	</script>
	<?php
}

// Hook per il salvataggio
add_action( 'init', 'fpml_ultra_simple_save', 1 );

// Hook per i messaggi
add_action( 'admin_notices', 'fpml_ultra_simple_messages' );

// Hook per il JavaScript
add_action( 'admin_enqueue_scripts', 'fpml_ultra_simple_script' );
