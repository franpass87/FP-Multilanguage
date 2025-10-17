<?php
/**
 * Direct Settings Save - Soluzione diretta e semplice per il salvataggio
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Soluzione diretta e semplice per salvare le impostazioni.
 * Bypassa tutti i sistemi complessi e salva direttamente.
 *
 * @since 0.4.1
 */
class FPML_Direct_Settings_Save {
	/**
	 * Singleton instance.
	 *
	 * @var FPML_Direct_Settings_Save|null
	 */
	protected static $instance = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.1
	 *
	 * @return FPML_Direct_Settings_Save
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		// Hook diretto per gestire il salvataggio
		add_action( 'init', array( $this, 'handle_direct_save' ), 1 );
		
		// Hook per mostrare messaggi
		add_action( 'admin_notices', array( $this, 'show_save_messages' ) );
		
		// Hook per aggiungere JavaScript che forza il salvataggio
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_save_script' ) );
	}

	/**
	 * Handle direct save of settings.
	 *
	 * @since 0.4.1
	 *
	 * @return void
	 */
	public function handle_direct_save() {
		// Solo su pagine admin
		if ( ! is_admin() ) {
			return;
		}

		// Solo su pagine FPML
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'fp-multilanguage' ) {
			return;
		}

		// Controlla se è stata inviata una form
		if ( ! isset( $_POST['fpml_direct_save'] ) ) {
			return;
		}

		// Verifica nonce
		if ( ! wp_verify_nonce( $_POST['fpml_direct_nonce'], 'fpml_direct_save' ) ) {
			wp_die( 'Errore di sicurezza.' );
		}

		// Salva le impostazioni direttamente
		$this->save_settings_directly();
	}

	/**
	 * Save settings directly to database.
	 *
	 * @since 0.4.1
	 *
	 * @return bool True if successful.
	 */
	protected function save_settings_directly() {
		// Prepara i dati da salvare
		$settings_to_save = array();

		// Provider
		if ( isset( $_POST['provider'] ) ) {
			$settings_to_save['provider'] = sanitize_text_field( $_POST['provider'] );
		}

		// OpenAI API Key
		if ( isset( $_POST['openai_api_key'] ) ) {
			$settings_to_save['openai_api_key'] = sanitize_text_field( $_POST['openai_api_key'] );
		}

		// Google API Key
		if ( isset( $_POST['google_api_key'] ) ) {
			$settings_to_save['google_api_key'] = sanitize_text_field( $_POST['google_api_key'] );
		}

		// Routing mode
		if ( isset( $_POST['routing_mode'] ) ) {
			$routing_mode = sanitize_text_field( $_POST['routing_mode'] );
			$settings_to_save['routing_mode'] = in_array( $routing_mode, array( 'segment', 'query' ) ) ? $routing_mode : 'segment';
		}

		// Batch size
		if ( isset( $_POST['batch_size'] ) ) {
			$settings_to_save['batch_size'] = max( 1, absint( $_POST['batch_size'] ) );
		}

		// Max chars
		if ( isset( $_POST['max_chars'] ) ) {
			$settings_to_save['max_chars'] = max( 500, absint( $_POST['max_chars'] ) );
		}

		// Max chars per batch
		if ( isset( $_POST['max_chars_per_batch'] ) ) {
			$settings_to_save['max_chars_per_batch'] = max( 0, absint( $_POST['max_chars_per_batch'] ) );
		}

		// Cron frequency
		if ( isset( $_POST['cron_frequency'] ) ) {
			$freq = sanitize_text_field( $_POST['cron_frequency'] );
			$settings_to_save['cron_frequency'] = in_array( $freq, array( '5min', '15min', 'hourly' ) ) ? $freq : '15min';
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
			$settings_to_save[ $field ] = isset( $_POST[ $field ] ) ? true : false;
		}

		// Menu switcher style
		if ( isset( $_POST['menu_switcher_style'] ) ) {
			$style = sanitize_text_field( $_POST['menu_switcher_style'] );
			$settings_to_save['menu_switcher_style'] = in_array( $style, array( 'inline', 'dropdown' ) ) ? $style : 'inline';
		}

		// Menu switcher position
		if ( isset( $_POST['menu_switcher_position'] ) ) {
			$position = sanitize_text_field( $_POST['menu_switcher_position'] );
			$settings_to_save['menu_switcher_position'] = in_array( $position, array( 'start', 'end' ) ) ? $position : 'end';
		}

		// Tariffe provider
		if ( isset( $_POST['rate_openai'] ) ) {
			$settings_to_save['rate_openai'] = floatval( $_POST['rate_openai'] );
		}
		if ( isset( $_POST['rate_google'] ) ) {
			$settings_to_save['rate_google'] = floatval( $_POST['rate_google'] );
		}

		// Carica le impostazioni esistenti per non sovrascrivere tutto
		$existing_settings = get_option( 'fpml_settings', array() );
		$merged_settings = array_merge( $existing_settings, $settings_to_save );

		// Salva direttamente nel database
		$result = update_option( 'fpml_settings', $merged_settings );

		if ( $result ) {
			// Imposta flag di successo
			set_transient( 'fpml_settings_saved_direct', true, 30 );
			error_log( 'FPML: Settings saved successfully via direct method' );
		} else {
			set_transient( 'fpml_settings_error_direct', true, 30 );
			error_log( 'FPML: Failed to save settings via direct method' );
		}

		// Redirect per evitare risottomissione
		$redirect_url = admin_url( 'admin.php?page=fp-multilanguage&direct-save=success' );
		if ( isset( $_GET['tab'] ) ) {
			$redirect_url .= '&tab=' . sanitize_key( $_GET['tab'] );
		}
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Show save messages.
	 *
	 * @since 0.4.1
	 *
	 * @return void
	 */
	public function show_save_messages() {
		// Solo su pagine FPML
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'fp-multilanguage' ) {
			return;
		}

		// Messaggio di successo
		if ( get_transient( 'fpml_settings_saved_direct' ) ) {
			delete_transient( 'fpml_settings_saved_direct' );
			echo '<div class="notice notice-success is-dismissible">';
			echo '<p><strong>✅ Impostazioni salvate con successo!</strong></p>';
			echo '</div>';
		}

		// Messaggio di errore
		if ( get_transient( 'fpml_settings_error_direct' ) ) {
			delete_transient( 'fpml_settings_error_direct' );
			echo '<div class="notice notice-error is-dismissible">';
			echo '<p><strong>❌ Errore nel salvataggio delle impostazioni. Riprova.</strong></p>';
			echo '</div>';
		}

		// Messaggio da URL
		if ( isset( $_GET['direct-save'] ) && $_GET['direct-save'] === 'success' ) {
			echo '<div class="notice notice-success is-dismissible">';
			echo '<p><strong>✅ Impostazioni salvate con successo!</strong></p>';
			echo '</div>';
		}
	}

	/**
	 * Enqueue JavaScript for enhanced save functionality.
	 *
	 * @since 0.4.1
	 *
	 * @return void
	 */
	public function enqueue_save_script() {
		// Solo su pagine FPML
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'fp-multilanguage' ) {
			return;
		}

		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Aggiungi un form nascosto per il salvataggio diretto
			$('body').append('<form id="fpml-direct-save-form" method="post" style="display:none;">' +
				'<input type="hidden" name="fpml_direct_save" value="1">' +
				'<input type="hidden" name="fpml_direct_nonce" value="<?php echo wp_create_nonce( 'fpml_direct_save' ); ?>">' +
				'</form>');

			// Intercetta tutti i submit button
			$('input[type="submit"]').on('click', function(e) {
				e.preventDefault();
				
				// Raccogli tutti i dati del form
				var formData = {};
				
				// Input text, select, textarea
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
						formData[key] = $(this).is(':checked');
					}
				});
				
				// Aggiungi i dati al form nascosto
				var hiddenForm = $('#fpml-direct-save-form');
				$.each(formData, function(key, value) {
					hiddenForm.append('<input type="hidden" name="' + key + '" value="' + value + '">');
				});
				
				// Invia il form
				hiddenForm.submit();
			});
		});
		</script>
		<?php
	}

	/**
	 * Force save settings programmatically.
	 *
	 * @since 0.4.1
	 *
	 * @param array $settings Settings to save.
	 *
	 * @return bool True if successful.
	 */
	public function force_save( $settings ) {
		// Sanitizza le impostazioni
		$sanitized = array();
		
		foreach ( $settings as $key => $value ) {
			if ( is_string( $value ) ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			} elseif ( is_bool( $value ) ) {
				$sanitized[ $key ] = (bool) $value;
			} elseif ( is_numeric( $value ) ) {
				$sanitized[ $key ] = is_float( $value ) ? floatval( $value ) : intval( $value );
			} else {
				$sanitized[ $key ] = $value;
			}
		}

		// Salva direttamente
		$result = update_option( 'fpml_settings', $sanitized );
		
		if ( $result ) {
			error_log( 'FPML: Settings saved successfully via force_save' );
		} else {
			error_log( 'FPML: Failed to save settings via force_save' );
		}
		
		return $result;
	}

	/**
	 * Get current settings.
	 *
	 * @since 0.4.1
	 *
	 * @return array Current settings.
	 */
	public function get_settings() {
		return get_option( 'fpml_settings', array() );
	}

	/**
	 * Check if settings are properly saved.
	 *
	 * @since 0.4.1
	 *
	 * @return bool True if settings are saved.
	 */
	public function are_settings_saved() {
		$settings = $this->get_settings();
		return ! empty( $settings );
	}
}
