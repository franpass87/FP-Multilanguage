<?php
/**
 * Simple Settings - Sistema di salvataggio semplice e diretto
 * 
 * Questo file gestisce il salvataggio delle impostazioni senza interferenze.
 * 
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @since 0.4.1
 */


namespace FP\Multilanguage\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestione semplice delle impostazioni senza interferenze.
 *
 * @since 0.4.1
 */
class SimpleSettings {
	/**
	 * Singleton instance.
	 *
	 * @var \FPML_Simple_Settings|null
	 */
	protected static $instance = null;

	/**
	 * Option key.
	 */
	const OPTION_KEY = '\FPML_settings';

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.1
	 *
	 * @return \FPML_Simple_Settings
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
		// Hook semplice per intercettare il salvataggio
		add_action( 'init', array( $this, 'maybe_handle_save' ), 1 );
	}

	/**
	 * Gestisce il salvataggio delle impostazioni se necessario.
	 *
	 * @since 0.4.1
	 *
	 * @return void
	 */
	public function maybe_handle_save() {
		// Solo in admin
		if ( ! is_admin() ) {
			return;
		}

		// Solo se c'è un POST
		if ( empty( $_POST ) ) {
			return;
		}

		// Solo se c'è un submit button
		if ( ! isset( $_POST['submit'] ) && ! isset( $_POST['save'] ) ) {
			return;
		}

		// Solo su pagine FPML
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'fp-multilanguage' ) {
			return;
		}

		// Solo se c'è il nonce corretto
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], '\FPML_settings_group-options' ) ) {
			return;
		}

		// Gestisci il salvataggio
		$this->handle_save();
	}

	/**
	 * Gestisce il salvataggio delle impostazioni.
	 *
	 * @since 0.4.1
	 *
	 * @return void
	 */
	protected function handle_save() {
		if ( ! isset( $_POST[ self::OPTION_KEY ] ) ) {
			return;
		}

		$raw_input = $_POST[ self::OPTION_KEY ];
		
		// Sanitizza i dati
		$sanitized_data = $this->sanitize_data( $raw_input );

		// Salva direttamente nel database
		$result = update_option( self::OPTION_KEY, $sanitized_data );

		// Log per debugging
		\FP\Multilanguage\Logger::debug( 'Simple Settings saved', array(
			'success' => $result,
			'data_count' => count( $sanitized_data ),
		) );

		// Imposta messaggio di successo
		set_transient( '\FPML_settings_saved', true, 30 );

		// Redirect per evitare risottomissione
		$redirect_url = admin_url( 'admin.php?page=fp-multilanguage&settings-saved=1' );
		if ( isset( $_GET['tab'] ) ) {
			$redirect_url .= '&tab=' . sanitize_key( $_GET['tab'] );
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Sanitizza i dati delle impostazioni.
	 *
	 * @since 0.4.1
	 *
	 * @param array $input Dati di input.
	 *
	 * @return array Dati sanitizzati.
	 */
	protected function sanitize_data( $input ) {
		$data = is_array( $input ) ? $input : array();
		$sanitized = array();

		// Provider
		if ( isset( $data['provider'] ) ) {
			$sanitized['provider'] = sanitize_text_field( $data['provider'] );
		}

		// API Keys
		if ( isset( $data['openai_api_key'] ) ) {
			$sanitized['openai_api_key'] = sanitize_text_field( $data['openai_api_key'] );
		}
		if ( isset( $data['google_api_key'] ) ) {
			$sanitized['google_api_key'] = sanitize_text_field( $data['google_api_key'] );
		}

		// Routing mode
		if ( isset( $data['routing_mode'] ) ) {
			$routing_mode = sanitize_text_field( $data['routing_mode'] );
			$sanitized['routing_mode'] = in_array( $routing_mode, array( 'segment', 'query' ) ) ? $routing_mode : 'segment';
		}

		// Numeric fields
		if ( isset( $data['batch_size'] ) ) {
			$sanitized['batch_size'] = max( 1, intval( $data['batch_size'] ) );
		}
		if ( isset( $data['max_chars'] ) ) {
			$sanitized['max_chars'] = max( 500, intval( $data['max_chars'] ) );
		}
		if ( isset( $data['max_chars_per_batch'] ) ) {
			$sanitized['max_chars_per_batch'] = max( 0, intval( $data['max_chars_per_batch'] ) );
		}

		// Cron frequency
		if ( isset( $data['cron_frequency'] ) ) {
			$freq = sanitize_text_field( $data['cron_frequency'] );
			$sanitized['cron_frequency'] = in_array( $freq, array( '5min', '15min', 'hourly' ) ) ? $freq : '15min';
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
			$sanitized[ $field ] = isset( $data[ $field ] ) && $data[ $field ];
		}

		// Menu switcher options
		if ( isset( $data['menu_switcher_style'] ) ) {
			$style = sanitize_text_field( $data['menu_switcher_style'] );
			$sanitized['menu_switcher_style'] = in_array( $style, array( 'inline', 'dropdown' ) ) ? $style : 'inline';
		}
		if ( isset( $data['menu_switcher_position'] ) ) {
			$position = sanitize_text_field( $data['menu_switcher_position'] );
			$sanitized['menu_switcher_position'] = in_array( $position, array( 'start', 'end' ) ) ? $position : 'end';
		}

		// Rates
		if ( isset( $data['rate_openai'] ) ) {
			$sanitized['rate_openai'] = floatval( $data['rate_openai'] );
		}
		if ( isset( $data['rate_google'] ) ) {
			$sanitized['rate_google'] = floatval( $data['rate_google'] );
		}

		return $sanitized;
	}

	/**
	 * Mostra il messaggio di successo.
	 *
	 * @since 0.4.1
	 *
	 * @return void
	 */
	public function show_success_message() {
		// Solo su pagine FPML
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'fp-multilanguage' ) {
			return;
		}

		// Messaggio da transient
		if ( get_transient( '\FPML_settings_saved' ) ) {
			delete_transient( '\FPML_settings_saved' );
			echo '<div class="notice notice-success is-dismissible">';
			echo '<p><strong>✅ Impostazioni salvate con successo!</strong></p>';
			echo '</div>';
		}

		// Messaggio da URL
		if ( isset( $_GET['settings-saved'] ) && $_GET['settings-saved'] === '1' ) {
			echo '<div class="notice notice-success is-dismissible">';
			echo '<p><strong>✅ Impostazioni salvate con successo!</strong></p>';
			echo '</div>';
		}
	}
}

// DISABILITATO: Usa WordPress Settings API standard (registrata in \FPML_Settings)
// La classe rimane disponibile per retrocompatibilità ma non viene inizializzata
// per evitare conflitti con il sistema di salvataggio standard

// Inizializza il sistema
// \FPML_Simple_Settings::instance();

// Mostra messaggi di successo
// add_action( 'admin_notices', array( \FPML_Simple_Settings::instance(), 'show_success_message' ) );

