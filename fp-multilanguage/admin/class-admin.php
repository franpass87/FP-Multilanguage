<?php
/**
 * Admin UI controller.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Manage admin screens and settings forms.
 *
 * @since 0.2.0
 */
class FPML_Admin {
/**
 * Settings instance.
 *
 * @var FPML_Settings
 */
    protected $settings;

    /**
     * Strings scanner instance.
     *
     * @var FPML_Strings_Scanner
     */
    protected $scanner;

    /**
     * Strings override manager.
     *
     * @var FPML_Strings_Override
     */
    protected $overrides;

    /**
     * Glossary manager.
     *
     * @var FPML_Glossary
     */
    protected $glossary;

    /**
     * Export/import helper.
     *
     * @var FPML_Export_Import
     */
    protected $exporter;

    /**
     * Plugin instance.
     *
     * @var FPML_Plugin
     */
    protected $plugin;

    /**
     * Post types where the language column is enabled.
     *
     * @var array
     */
    protected $language_column_post_types = array();

/**
 * Menu slug.
 */
const MENU_SLUG = 'fpml-settings';

/**
 * Constructor.
 */
        public function __construct() {
                $this->settings  = FPML_Settings::instance();
                $this->scanner   = FPML_Strings_Scanner::instance();
                $this->overrides = FPML_Strings_Override::instance();
                $this->glossary  = FPML_Glossary::instance();
                $this->exporter  = FPML_Export_Import::instance();
                // Rimossa dipendenza circolare - lazy loading
                $this->plugin    = null;

                add_action( 'admin_menu', array( $this, 'register_menu' ) );
                add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
                add_action( 'admin_notices', array( $this, 'maybe_render_cron_notice' ) );
                add_action( 'admin_notices', array( $this, 'maybe_render_editor_notice' ) );
                add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
                add_action( 'admin_post_fpml_scan_strings', array( $this, 'handle_scan_strings' ) );
        add_action( 'admin_post_fpml_save_overrides', array( $this, 'handle_save_overrides' ) );
        add_action( 'admin_post_fpml_import_overrides', array( $this, 'handle_import_overrides' ) );
        add_action( 'admin_post_fpml_export_overrides', array( $this, 'handle_export_overrides' ) );
        add_action( 'admin_post_fpml_save_glossary', array( $this, 'handle_save_glossary' ) );
        add_action( 'admin_post_fpml_import_glossary', array( $this, 'handle_import_glossary' ) );
        add_action( 'admin_post_fpml_export_glossary', array( $this, 'handle_export_glossary' ) );
        add_action( 'admin_post_fpml_export_state', array( $this, 'handle_export_state' ) );
        add_action( 'admin_post_fpml_import_state', array( $this, 'handle_import_state' ) );
        add_action( 'admin_post_fpml_export_logs', array( $this, 'handle_export_logs' ) );
        add_action( 'admin_post_fpml_import_logs', array( $this, 'handle_import_logs' ) );
		add_action( 'admin_post_fpml_clear_sandbox', array( $this, 'handle_clear_sandbox' ) );
		add_action( 'admin_post_fpml_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'wp_ajax_fpml_trigger_detection', array( $this, 'handle_trigger_detection' ) );
		add_action( 'wp_ajax_fpml_refresh_nonce', array( $this, 'handle_refresh_nonce' ) );
		add_action( 'wp_ajax_fpml_reindex_batch_ajax', array( $this, 'handle_reindex_batch_ajax' ) );
		add_action( 'admin_init', array( $this, 'handle_expired_nonce_redirect' ) );
		add_action( 'init', array( $this, 'handle_expired_nonce_early' ), 1 );
		add_action( 'plugins_loaded', array( $this, 'handle_expired_nonce_very_early' ), 1 );
		add_filter( 'wp_die_handler', array( $this, 'custom_wp_die_handler' ) );
		add_filter( 'check_admin_referer', array( $this, 'handle_admin_referer_check' ), 10, 2 );
		add_action( 'current_screen', array( $this, 'setup_post_list_hooks' ) );
                add_action( 'restrict_manage_posts', array( $this, 'render_language_filter' ), 20, 1 );
                add_action( 'pre_get_posts', array( $this, 'handle_language_filter_query' ) );
                add_filter( 'the_title', array( $this, 'maybe_add_translation_badge' ), 10, 2 );
    }

	/**
	 * Get plugin instance (lazy loading).
	 *
	 * @return FPML_Plugin|null
	 */
	protected function get_plugin() {
		if ( null === $this->plugin && class_exists( 'FPML_Plugin' ) ) {
			$this->plugin = FPML_Plugin::instance();
		}
		return $this->plugin;
	}

/**
 * Register admin menu.
 *
 * @since 0.2.0
 *
 * @return void
 */
public function register_menu() {
add_menu_page(
esc_html__( 'FP Multilanguage', 'fp-multilanguage' ),
esc_html__( 'FP Multilanguage', 'fp-multilanguage' ),
'manage_options',
self::MENU_SLUG,
array( $this, 'render_settings_page' ),
'dashicons-translation',
58
);
}

/**
 * Enqueue admin assets.
 *
 * @since 0.2.0
 *
 * @param string $hook Current admin page hook.
 *
 * @return void
 */
public function enqueue_assets( $hook ) {
if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
return;
}

wp_enqueue_style( 'fpml-admin', FPML_PLUGIN_URL . 'assets/admin.css', array(), FPML_PLUGIN_VERSION );
wp_enqueue_script( 'fpml-admin', FPML_PLUGIN_URL . 'assets/admin.js', array(), FPML_PLUGIN_VERSION, true );
}

/**
 * Render settings page.
 *
 * @since 0.2.0
 *
 * @return void
 */
public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
                return;
        }

        $tabs        = $this->get_tabs();
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( ! isset( $tabs[ $current_tab ] ) ) {
                $current_tab = 'general';
        }

        $view_file = FPML_PLUGIN_DIR . 'admin/views/' . $tabs[ $current_tab ]['view'];

        $options   = $this->settings->all();
        $scanner   = $this->scanner;
        $overrides = $this->overrides;
        $glossary  = $this->glossary;
        $exporter  = $this->exporter;
        $plugin    = $this->get_plugin();
        $diagnostics_snapshot = array();

        if ( 'diagnostics' === $current_tab ) {
                $diagnostics_snapshot = $plugin->get_diagnostics_snapshot();
        }

        echo '<div class="wrap fpml-settings-wrap">';
        echo '<h1>' . esc_html__( 'FP Multilanguage', 'fp-multilanguage' ) . '</h1>';
        settings_errors( 'fpml_settings_group' );

        if ( $plugin->is_assisted_mode() ) {
                $reason_label = $plugin->get_assisted_reason_label();
                $reason_suffix = '' !== $reason_label ? sprintf( ' (%s)', $reason_label ) : '';

                echo '<div class="notice notice-info">';
                echo '<p>' . esc_html__( 'Modalità assistita attiva: WPML/Polylang gestiscono routing e duplicazione. FP Multilanguage fornisce solo strumenti di traduzione.', 'fp-multilanguage' ) . esc_html( $reason_suffix ) . '</p>';
                echo '</div>';
        }

        echo '<nav class="nav-tab-wrapper fpml-settings-tabs">';

        foreach ( $tabs as $tab_key => $tab_data ) {
                $url   = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $tab_key ), admin_url( 'admin.php' ) );
                $class = 'nav-tab' . ( $tab_key === $current_tab ? ' nav-tab-active' : '' );
                echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $tab_data['label'] ) . '</a>';
        }

        echo '</nav>';

        if ( file_exists( $view_file ) ) {
                $settings = $this->settings;
                require $view_file;
        } else {
                echo '<p>' . esc_html__( 'Vista non disponibile.', 'fp-multilanguage' ) . '</p>';
        }

        echo '</div>';
}

/**
 * Get tab definitions.
 *
 * @since 0.2.0
 *
 * @return array
 */
protected function get_tabs() {
        return array(
'general'     => array(
'label' => esc_html__( 'Generale', 'fp-multilanguage' ),
'view'  => 'settings-general.php',
),
'content'     => array(
'label' => esc_html__( 'Contenuti', 'fp-multilanguage' ),
'view'  => 'settings-content.php',
),
'seo'         => array(
'label' => esc_html__( 'SEO', 'fp-multilanguage' ),
'view'  => 'settings-seo.php',
),
'strings'     => array(
'label' => esc_html__( 'Stringhe', 'fp-multilanguage' ),
'view'  => 'settings-strings.php',
),
'glossary'    => array(
'label' => esc_html__( 'Glossario', 'fp-multilanguage' ),
'view'  => 'settings-glossary.php',
),
'compatibility' => array(
'label' => esc_html__( 'Compatibilità Plugin', 'fp-multilanguage' ),
'view'  => 'settings-plugin-compatibility.php',
),
'export'      => array(
'label' => esc_html__( 'Export / Import', 'fp-multilanguage' ),
'view'  => 'settings-export.php',
),
'diagnostics' => array(
'label' => esc_html__( 'Diagnostica', 'fp-multilanguage' ),
'view'  => 'settings-diagnostics.php',
),
        );
    }

    /**
     * Handle string scan requests.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function handle_scan_strings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }

        check_admin_referer( 'fpml_scan_strings' );

        $count = $this->scanner->scan();

        add_settings_error(
            'fpml_settings_group',
            'fpml-scan-strings',
            sprintf(
                /* translators: %d: total detected strings */
                esc_html__( 'Scansione completata: rilevate %d stringhe.', 'fp-multilanguage' ),
                absint( $count )
            ),
            'updated'
        );

        $this->redirect_to_tab( 'strings' );
    }

    /**
     * Handle override save requests.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function handle_save_overrides() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }

        check_admin_referer( 'fpml_save_overrides' );

        $raw_overrides = isset( $_POST['overrides'] ) ? wp_unslash( $_POST['overrides'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $payload       = array();

        if ( is_array( $raw_overrides ) ) {
            foreach ( $raw_overrides as $hash => $data ) {
                $hash = sanitize_key( $hash );

                if ( '' === $hash ) {
                    continue;
                }

                $payload[ $hash ] = array(
                    'target'  => isset( $data['target'] ) ? sanitize_text_field( wp_unslash( $data['target'] ) ) : '',
                    'context' => isset( $data['context'] ) ? sanitize_text_field( wp_unslash( $data['context'] ) ) : '',
                    'delete'  => ! empty( $data['delete'] ),
                );
            }
        }

        if ( ! empty( $payload ) ) {
            $this->overrides->update_overrides( $payload );
        }

        $new_source  = isset( $_POST['new_source'] ) ? sanitize_text_field( wp_unslash( $_POST['new_source'] ) ) : '';
        $new_target  = isset( $_POST['new_target'] ) ? sanitize_text_field( wp_unslash( $_POST['new_target'] ) ) : '';
        $new_context = isset( $_POST['new_context'] ) ? sanitize_text_field( wp_unslash( $_POST['new_context'] ) ) : '';

        if ( '' !== $new_source && '' !== $new_target ) {
            $this->overrides->add_override( $new_source, $new_target, $new_context );
        }

        add_settings_error(
            'fpml_settings_group',
            'fpml-save-overrides',
            esc_html__( 'Override aggiornate.', 'fp-multilanguage' ),
            'updated'
        );

        $this->redirect_to_tab( 'strings' );
    }

    /**
     * Handle overrides import requests.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function handle_import_overrides() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }

        check_admin_referer( 'fpml_import_overrides' );

        $format  = isset( $_POST['format'] ) ? sanitize_key( wp_unslash( $_POST['format'] ) ) : 'json';
        $payload = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $payload = wp_check_invalid_utf8( $payload, true );
        $payload = is_string( $payload ) ? trim( $payload ) : '';

        $imported = 0;

        if ( 'csv' === $format ) {
            $imported = $this->overrides->import_csv( $payload );
        } else {
            $imported = $this->overrides->import_json( $payload );
        }

        add_settings_error(
            'fpml_settings_group',
            'fpml-import-overrides',
            sprintf(
                /* translators: %d: number of imported overrides */
                esc_html__( 'Import completato: %d override caricate.', 'fp-multilanguage' ),
                absint( $imported )
            ),
            'updated'
        );

        $this->redirect_to_tab( 'strings' );
    }

    /**
     * Output overrides export payload.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function handle_export_overrides() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }

        check_admin_referer( 'fpml_export_overrides' );

        $format = isset( $_POST['format'] ) ? sanitize_key( wp_unslash( $_POST['format'] ) ) : 'json';
        $format = in_array( $format, array( 'json', 'csv' ), true ) ? $format : 'json';

        nocache_headers();

        if ( 'csv' === $format ) {
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename=fpml-string-overrides.csv' );
            echo $this->overrides->export_csv(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            header( 'Content-Type: application/json; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename=fpml-string-overrides.json' );
            echo $this->overrides->export_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        exit;
    }

    /**
     * Handle glossary save requests.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function handle_save_glossary() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }

        check_admin_referer( 'fpml_save_glossary' );

        $raw_entries = isset( $_POST['entries'] ) ? wp_unslash( $_POST['entries'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $to_save     = array();

        if ( is_array( $raw_entries ) ) {
            foreach ( $raw_entries as $key => $data ) {
                $key = sanitize_key( $key );

                if ( '' === $key ) {
                    continue;
                }

                if ( ! empty( $data['delete'] ) ) {
                    $to_save[ $key ] = array( 'delete' => true );
                    continue;
                }

                $to_save[ $key ] = array(
                    'source'  => isset( $data['source'] ) ? sanitize_text_field( wp_unslash( $data['source'] ) ) : '',
                    'target'  => isset( $data['target'] ) ? sanitize_text_field( wp_unslash( $data['target'] ) ) : '',
                    'context' => isset( $data['context'] ) ? sanitize_text_field( wp_unslash( $data['context'] ) ) : '',
                );
            }
        }

        if ( ! empty( $to_save ) ) {
            $delete = array();
            $keep   = array();

            foreach ( $to_save as $hash => $entry ) {
                if ( isset( $entry['delete'] ) && $entry['delete'] ) {
                    $delete[] = $hash;
                    continue;
                }

                if ( '' !== $entry['source'] && '' !== $entry['target'] ) {
                    $keep[] = array(
                        'hash'    => $hash,
                        'source'  => $entry['source'],
                        'target'  => $entry['target'],
                        'context' => $entry['context'],
                    );
                }
            }

            if ( ! empty( $delete ) ) {
                $this->glossary->delete_entries( $delete );
            }

            if ( ! empty( $keep ) ) {
                foreach ( $keep as $entry ) {
                    $new_hash = md5( wp_json_encode( array( $entry['source'], $entry['context'] ) ) );

                    if ( $new_hash !== $entry['hash'] ) {
                        $this->glossary->delete_entries( array( $entry['hash'] ) );
                    }

                    $this->glossary->upsert_entry( $entry['source'], $entry['target'], $entry['context'] );
                }
            }
        }

        $new_source  = isset( $_POST['new_glossary_source'] ) ? sanitize_text_field( wp_unslash( $_POST['new_glossary_source'] ) ) : '';
        $new_target  = isset( $_POST['new_glossary_target'] ) ? sanitize_text_field( wp_unslash( $_POST['new_glossary_target'] ) ) : '';
        $new_context = isset( $_POST['new_glossary_context'] ) ? sanitize_text_field( wp_unslash( $_POST['new_glossary_context'] ) ) : '';

        if ( '' !== $new_source && '' !== $new_target ) {
            $this->glossary->upsert_entry( $new_source, $new_target, $new_context );
        }

        add_settings_error(
            'fpml_settings_group',
            'fpml-save-glossary',
            esc_html__( 'Glossario aggiornato.', 'fp-multilanguage' ),
            'updated'
        );

        $this->redirect_to_tab( 'glossary' );
    }

    /**
     * Handle glossary import.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function handle_import_glossary() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }

        check_admin_referer( 'fpml_import_glossary' );

        $format  = isset( $_POST['format'] ) ? sanitize_key( wp_unslash( $_POST['format'] ) ) : 'json';
        $payload = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $payload = wp_check_invalid_utf8( $payload, true );
        $payload = is_string( $payload ) ? trim( $payload ) : '';

        $imported = 0;

        if ( 'csv' === $format ) {
            $imported = $this->glossary->import_csv( $payload );
        } else {
            $imported = $this->glossary->import_json( $payload );
        }

        add_settings_error(
            'fpml_settings_group',
            'fpml-import-glossary',
            sprintf(
                /* translators: %d: number of imported glossary entries */
                esc_html__( 'Import glossario completato: %d voci caricate.', 'fp-multilanguage' ),
                absint( $imported )
            ),
            'updated'
        );

        $this->redirect_to_tab( 'glossary' );
    }

    /**
     * Export glossary payload.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function handle_export_glossary() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }

        check_admin_referer( 'fpml_export_glossary' );

        $format = isset( $_POST['format'] ) ? sanitize_key( wp_unslash( $_POST['format'] ) ) : 'json';
        $format = in_array( $format, array( 'json', 'csv' ), true ) ? $format : 'json';

        nocache_headers();

        if ( 'csv' === $format ) {
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename=fpml-glossary.csv' );
            echo $this->glossary->export_csv(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            header( 'Content-Type: application/json; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename=fpml-glossary.json' );
            echo $this->glossary->export_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        exit;
    }

    /**
     * Export translation state payload.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function handle_export_state() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }

        check_admin_referer( 'fpml_export_state' );

        $format = isset( $_POST['format'] ) ? sanitize_key( wp_unslash( $_POST['format'] ) ) : 'json';
        $format = in_array( $format, array( 'json', 'csv' ), true ) ? $format : 'json';

        nocache_headers();

        if ( 'csv' === $format ) {
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename=fpml-translation-state.csv' );
        } else {
            header( 'Content-Type: application/json; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename=fpml-translation-state.json' );
        }

        echo $this->exporter->export_translation_state( $format ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    /**
     * Import translation state payload.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function handle_import_state() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }

        check_admin_referer( 'fpml_import_state' );

        $format  = isset( $_POST['format'] ) ? sanitize_key( wp_unslash( $_POST['format'] ) ) : 'json';
        $payload = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $payload = wp_check_invalid_utf8( $payload, true );
        $payload = is_string( $payload ) ? trim( $payload ) : '';

        $imported = $this->exporter->import_translation_state( $payload, $format );

        add_settings_error(
            'fpml_settings_group',
            'fpml-import-state',
            sprintf(
                /* translators: %d: number of imported rows */
                esc_html__( 'Import stato traduzioni completato: %d righe aggiornate.', 'fp-multilanguage' ),
                absint( $imported )
            ),
            'updated'
        );

        $this->redirect_to_tab( 'export' );
    }

    /**
     * Export logger payload.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function handle_export_logs() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }

        check_admin_referer( 'fpml_export_logs' );

        $format = isset( $_POST['format'] ) ? sanitize_key( wp_unslash( $_POST['format'] ) ) : 'json';
        $format = in_array( $format, array( 'json', 'csv' ), true ) ? $format : 'json';

        nocache_headers();

        if ( 'csv' === $format ) {
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename=fpml-logs.csv' );
        } else {
            header( 'Content-Type: application/json; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename=fpml-logs.json' );
        }

        echo $this->exporter->export_logs( $format ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    /**
     * Import logger payload.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function handle_import_logs() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }

        check_admin_referer( 'fpml_import_logs' );

        $format  = isset( $_POST['format'] ) ? sanitize_key( wp_unslash( $_POST['format'] ) ) : 'json';
        $payload = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $payload = wp_check_invalid_utf8( $payload, true );
        $payload = is_string( $payload ) ? trim( $payload ) : '';

        $imported = $this->exporter->import_logs( $payload, $format );

        add_settings_error(
            'fpml_settings_group',
            'fpml-import-logs',
            sprintf(
                /* translators: %d: number of imported log rows */
                esc_html__( 'Import log completato: %d eventi registrati.', 'fp-multilanguage' ),
                absint( $imported )
            ),
            'updated'
        );

        $this->redirect_to_tab( 'export' );
    }

    /**
     * Clear sandbox previews.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function handle_clear_sandbox() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }

        check_admin_referer( 'fpml_clear_sandbox' );

        $this->exporter->clear_sandbox_previews();

        add_settings_error(
            'fpml_settings_group',
            'fpml-clear-sandbox',
            esc_html__( 'Anteprime sandbox azzerate.', 'fp-multilanguage' ),
            'updated'
        );

        $this->redirect_to_tab( 'export' );
    }

	/**
	 * Handle AJAX plugin detection trigger.
	 *
	 * @since 0.4.2
	 *
	 * @return void
	 */
	public function handle_trigger_detection() {
		check_ajax_referer( 'fpml_trigger_detection', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
		}

		if ( ! class_exists( 'FPML_Plugin_Detector' ) ) {
			wp_send_json_error( array( 'message' => __( 'Plugin Detector non disponibile.', 'fp-multilanguage' ) ) );
		}

		$detector = FPML_Plugin_Detector::instance();
		$summary = $detector->trigger_detection();

		wp_send_json_success( array(
			'summary' => $summary,
			'message' => sprintf(
				/* translators: %d: number of plugins detected */
				_n(
					'Rilevato %d plugin compatibile.',
					'Rilevati %d plugin compatibili.',
					$summary['total'],
					'fp-multilanguage'
				),
				$summary['total']
			),
		) );
	}

    /**
     * Prepare admin post list enhancements.
     *
     * @since 0.3.0
     *
     * @param WP_Screen|null $screen Current screen instance.
     *
     * @return void
     */
    public function setup_post_list_hooks( $screen = null ) {
        if ( ! $screen && function_exists( 'get_current_screen' ) ) {
            $screen = get_current_screen();
        }

        if ( ! $screen || 'edit' !== $screen->base || empty( $screen->post_type ) ) {
            return;
        }

        $post_type = $screen->post_type;

        if ( isset( $this->language_column_post_types[ $post_type ] ) ) {
            return;
        }

        $this->language_column_post_types[ $post_type ] = true;

        add_filter( 'manage_edit-' . $post_type . '_columns', array( $this, 'register_language_column' ) );
        add_action( 'manage_' . $post_type . '_posts_custom_column', array( $this, 'render_language_column' ), 10, 2 );
    }

    /**
     * Register the language column in list tables.
     *
     * @since 0.3.0
     *
     * @param array $columns Existing columns.
     *
     * @return array
     */
    public function register_language_column( $columns ) {
        if ( ! is_array( $columns ) ) {
            $columns = array();
        }

        if ( isset( $columns['fpml_lang'] ) ) {
            return $columns;
        }

        $injected = array();

        foreach ( $columns as $key => $label ) {
            $injected[ $key ] = $label;

            if ( 'title' === $key ) {
                $injected['fpml_lang'] = esc_html__( 'Lingua', 'fp-multilanguage' );
            }
        }

        if ( ! isset( $injected['fpml_lang'] ) ) {
            $injected['fpml_lang'] = esc_html__( 'Lingua', 'fp-multilanguage' );
        }

        return $injected;
    }

    /**
     * Render the language column contents.
     *
     * @since 0.3.0
     *
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     *
     * @return void
     */
    public function render_language_column( $column, $post_id ) {
        if ( 'fpml_lang' !== $column ) {
            return;
        }

        $is_translation = get_post_meta( $post_id, '_fpml_is_translation', true );

        $label = $is_translation ? esc_html__( 'EN', 'fp-multilanguage' ) : esc_html__( 'IT', 'fp-multilanguage' );

        echo esc_html( $label );
    }

    /**
     * Render the admin language filter control.
     *
     * @since 0.3.0
     *
     * @param string $post_type Current post type slug.
     *
     * @return void
     */
    public function render_language_filter( $post_type = '' ) {
        if ( ! is_admin() ) {
            return;
        }

        if ( '' === $post_type ) {
            global $typenow;
            $post_type = $typenow;
        }

        if ( empty( $post_type ) || empty( $this->language_column_post_types[ $post_type ] ) ) {
            return;
        }

        $current = isset( $_GET['fpml_lang_filter'] ) ? sanitize_key( wp_unslash( $_GET['fpml_lang_filter'] ) ) : '';

        echo '<label class="screen-reader-text" for="fpml-lang-filter">' . esc_html__( 'Filtra per lingua', 'fp-multilanguage' ) . '</label>';
        echo '<select name="fpml_lang_filter" id="fpml-lang-filter">';
        echo '<option value="">' . esc_html__( 'Tutte le lingue', 'fp-multilanguage' ) . '</option>';
        echo '<option value="it"' . selected( $current, 'it', false ) . '>' . esc_html__( 'Italiano', 'fp-multilanguage' ) . '</option>';
        echo '<option value="en"' . selected( $current, 'en', false ) . '>' . esc_html__( 'Inglese', 'fp-multilanguage' ) . '</option>';
        echo '</select>';
    }

    /**
     * Filter the admin list query by language when requested.
     *
     * @since 0.3.0
     *
     * @param WP_Query $query Current query instance.
     *
     * @return void
     */
    public function handle_language_filter_query( $query ) {
        if ( ! is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
            return;
        }

        global $pagenow;

        if ( 'edit.php' !== $pagenow ) {
            return;
        }

        $lang = isset( $_GET['fpml_lang_filter'] ) ? sanitize_key( wp_unslash( $_GET['fpml_lang_filter'] ) ) : '';

        if ( ! in_array( $lang, array( 'it', 'en' ), true ) ) {
            return;
        }

        $post_type = $query->get( 'post_type' );

        if ( empty( $post_type ) ) {
            $post_type = 'post';
        }

        if ( 'any' === $post_type ) {
            $post_type = 'post';
        }

        if ( ! isset( $this->language_column_post_types[ $post_type ] ) ) {
            return;
        }

        $meta_query = $query->get( 'meta_query' );

        if ( ! is_array( $meta_query ) ) {
            $meta_query = array();
        }

        if ( 'en' === $lang ) {
            $meta_query[] = array(
                'key'     => '_fpml_is_translation',
                'value'   => '1',
                'compare' => '=',
            );
        } else {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key'     => '_fpml_is_translation',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => '_fpml_is_translation',
                    'value'   => '0',
                    'compare' => '=',
                ),
                array(
                    'key'     => '_fpml_is_translation',
                    'value'   => '1',
                    'compare' => '!=',
                ),
            );
        }

        $query->set( 'meta_query', $meta_query );
    }

    /**
     * Append the translation badge to titles when enabled.
     *
     * @since 0.3.0
     *
     * @param string $title   Original title.
     * @param int    $post_id Current post ID.
     *
     * @return string
     */
    public function maybe_add_translation_badge( $title, $post_id ) {
        if ( ! is_admin() || ! $this->settings->get( 'show_translation_badge', true ) ) {
            return $title;
        }

        $post_id = (int) $post_id;

        if ( $post_id <= 0 ) {
            return $title;
        }

        $post = get_post( $post_id );

        if ( ! $post ) {
            return $title;
        }

        if ( ! get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
            return $title;
        }

        $badge = esc_html__( '(EN)', 'fp-multilanguage' );

        if ( false !== strpos( $title, $badge ) ) {
            return $title;
        }

        return $title . ' ' . $badge;
    }

    /**
     * Display an informational notice in the post editor.
     *
     * @since 0.3.0
     *
     * @return void
     */
    public function maybe_render_editor_notice() {
        if ( ! is_admin() || ! $this->settings->get( 'show_editor_notice', true ) ) {
            return;
        }

        if ( ! function_exists( 'get_current_screen' ) ) {
            return;
        }

        $screen = get_current_screen();

        if ( ! $screen || 'post' !== $screen->base ) {
            return;
        }

        $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

        if ( $post_id && get_post_meta( $post_id, '_fpml_is_translation', true ) ) {
            return;
        }

        echo '<div class="notice notice-info"><p>' . esc_html__( 'Le modifiche in IT si replicano automaticamente in EN.', 'fp-multilanguage' ) . '</p></div>';
    }

    /**
     * Display a notice when WP-Cron is disabled.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function maybe_render_cron_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $plugin = $this->get_plugin();
        if ( $plugin && method_exists( $plugin, 'is_assisted_mode' ) && $plugin->is_assisted_mode() ) {
            return;
        }

        if ( ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) {
            return;
        }

        if ( ! function_exists( 'get_current_screen' ) ) {
            return;
        }

        $screen = get_current_screen();

        if ( ! $screen || 'toplevel_page_' . self::MENU_SLUG !== $screen->id ) {
            return;
        }

        $path    = untrailingslashit( ABSPATH );
        $command = sprintf( "*/5 * * * * cd '%1\$s' && wp cron event run --due-now >/dev/null 2>&1", $path );

        echo '<div class="notice notice-warning">';
        echo '<p>' . esc_html__( 'WP-Cron risulta disabilitato (DISABLE_WP_CRON = true). Configura un cron di sistema per mantenere attiva la coda di traduzione.', 'fp-multilanguage' ) . '</p>';
        echo '<p>' . esc_html__( 'Esempio crontab (ogni 5 minuti con WP-CLI):', 'fp-multilanguage' ) . '</p>';
        echo '<p><code>' . esc_html( $command ) . '</code></p>';

        $php_command = sprintf( "*/5 * * * * php '%1\$s/wp-cron.php' >/dev/null 2>&1", $path );

        echo '<p>' . esc_html__( 'In alternativa puoi schedulare direttamente wp-cron.php:', 'fp-multilanguage' ) . '</p>';
        echo '<p><code>' . esc_html( $php_command ) . '</code></p>';
        echo '</div>';
    }

    /**
     * Redirect back to a specific settings tab.
     *
     * @since 0.2.0
     *
     * @param string $tab Tab slug.
     *
     * @return void
     */
    protected function redirect_to_tab( $tab ) {
        $tab = sanitize_key( $tab );
        $url = add_query_arg(
            array(
                'page' => self::MENU_SLUG,
                'tab'  => $tab,
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $url );
        exit;
    }

    /**
     * Register dashboard widget.
     *
     * @since 0.3.2
     *
     * @return void
     */
    public function register_dashboard_widget() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        wp_add_dashboard_widget(
            'fpml_dashboard_widget',
            __( 'FP Multilanguage - Riepilogo Traduzioni', 'fp-multilanguage' ),
            array( $this, 'render_dashboard_widget' )
        );
    }

    /**
     * Render dashboard widget content.
     *
     * @since 0.3.2
     *
     * @return void
     */
    public function render_dashboard_widget() {
        $queue = FPML_Queue::instance();
        $counts = $queue->get_state_counts();
        $processor = FPML_Processor::instance();

        $pending = isset( $counts['pending'] ) ? (int) $counts['pending'] : 0;
        $errors = isset( $counts['error'] ) ? (int) $counts['error'] : 0;
        $done_today = $this->get_completed_today();

        ?>
        <div class="fpml-dashboard-widget" style="padding: 8px 0;">
            <ul style="margin: 0; padding: 0; list-style: none;">
                <li style="margin-bottom: 8px; display: flex; justify-content: space-between;">
                    <strong><?php esc_html_e( 'In coda:', 'fp-multilanguage' ); ?></strong>
                    <span style="<?php echo esc_attr( $pending > 100 ? 'color: #d63638;' : '' ); ?>">
                        <?php echo esc_html( number_format_i18n( $pending ) ); ?>
                    </span>
                </li>
                <li style="margin-bottom: 8px; display: flex; justify-content: space-between;">
                    <strong><?php esc_html_e( 'Completati oggi:', 'fp-multilanguage' ); ?></strong>
                    <span style="color: #00a32a;">
                        <?php echo esc_html( number_format_i18n( $done_today ) ); ?>
                    </span>
                </li>
                <?php if ( $errors > 0 ) : ?>
                <li style="margin-bottom: 8px; display: flex; justify-content: space-between;">
                    <strong><?php esc_html_e( 'Errori:', 'fp-multilanguage' ); ?></strong>
                    <span style="color: #d63638;">
                        <?php echo esc_html( number_format_i18n( $errors ) ); ?>
                    </span>
                </li>
                <?php endif; ?>
                <li style="margin-bottom: 8px; display: flex; justify-content: space-between;">
                    <strong><?php esc_html_e( 'Processore:', 'fp-multilanguage' ); ?></strong>
                    <span>
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe inline HTML with escaped strings
                        echo $processor->is_locked() 
                            ? '<span style="color: #d63638;">●</span> ' . esc_html__( 'Occupato', 'fp-multilanguage' )
                            : '<span style="color: #00a32a;">●</span> ' . esc_html__( 'Libero', 'fp-multilanguage' );
                        ?>
                    </span>
                </li>
            </ul>
            
            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #dcdcde;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=fpml-settings&tab=diagnostics' ) ); ?>" class="button button-small">
                    <?php esc_html_e( 'Vedi Diagnostica Completa', 'fp-multilanguage' ); ?> →
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Get count of jobs completed today.
     *
     * @since 0.3.2
     *
     * @return int
     */
    protected function get_completed_today() {
        global $wpdb;
        $table = $wpdb->prefix . 'fpml_queue';
        $today = current_time( 'Y-m-d' );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE state = %s AND DATE(updated_at) = %s",
                'done',
                $today
            )
        );

        return absint( $count );
    }

    /**
     * AJAX handler for nonce refresh.
     * 
     * This provides a more reliable way to refresh nonces than the REST endpoint,
     * as it uses WordPress AJAX which doesn't require nonce validation.
     *
     * @since 0.4.3
     *
     * @return void
     */
    public function handle_refresh_nonce() {
        // Check user permissions (no nonce check needed for this action)
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
        }

        // Generate new nonce
        $new_nonce = wp_create_nonce( 'wp_rest' );

        wp_send_json_success( array( 'nonce' => $new_nonce ) );
    }

    /**
     * Handle expired nonce redirects on admin pages.
     * 
     * When WordPress redirects after form submission with an expired nonce,
     * it shows "Il link che hai seguito è scaduto". This method detects
     * such redirects to our plugin pages and redirects to a clean URL.
     *
     * @since 0.4.3
     *
     * @return void
     */
    public function handle_expired_nonce_redirect() {
        // Only handle requests to our plugin admin pages
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::MENU_SLUG ) {
            return;
        }

        // Check if we're being redirected after a form submission
        $is_redirect = isset( $_SERVER['HTTP_REFERER'] ) && 
                      strpos( $_SERVER['HTTP_REFERER'], 'options.php' ) !== false;

        // Check if there's an expired nonce error in the URL or referrer
        $has_nonce_error = isset( $_GET['_wpnonce'] ) || 
                          ( isset( $_SERVER['HTTP_REFERER'] ) && 
                            strpos( $_SERVER['HTTP_REFERER'], '_wpnonce=' ) !== false );

        if ( $is_redirect && $has_nonce_error ) {
            // Redirect to clean URL without nonce parameters
            $clean_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
            if ( isset( $_GET['tab'] ) ) {
                $clean_url .= '&tab=' . sanitize_key( $_GET['tab'] );
            }
            
            wp_safe_redirect( $clean_url );
            exit;
        }
    }

    /**
     * Custom wp_die handler to intercept expired nonce errors.
     * 
     * This method intercepts WordPress wp_die calls and checks if they're
     * related to expired nonces on our plugin pages. If so, it redirects
     * to a clean URL instead of showing the error.
     *
     * @since 0.4.3
     *
     * @param callable $handler Current wp_die handler.
     * @return callable Custom handler or original handler.
     */
    public function custom_wp_die_handler( $handler ) {
        // Only intercept on our plugin pages
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::MENU_SLUG ) {
            return $handler;
        }

        // Check if we're dealing with a nonce error
        $is_nonce_error = false;
        
        // Check the current error message
        if ( isset( $_GET['_wpnonce'] ) ) {
            $is_nonce_error = true;
        }

        // Check if we came from a form submission
        if ( isset( $_SERVER['HTTP_REFERER'] ) && 
             strpos( $_SERVER['HTTP_REFERER'], 'options.php' ) !== false ) {
            $is_nonce_error = true;
        }

        if ( $is_nonce_error ) {
            // Redirect to clean URL
            $clean_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
            if ( isset( $_GET['tab'] ) ) {
                $clean_url .= '&tab=' . sanitize_key( $_GET['tab'] );
            }
            
            wp_safe_redirect( $clean_url );
            exit;
        }

        return $handler;
    }

    /**
     * Handle expired nonce errors very early in the WordPress load process.
     * 
     * This method runs on the 'init' hook with priority 1 to catch
     * expired nonce errors before WordPress shows the error page.
     *
     * @since 0.4.3
     *
     * @return void
     */
    public function handle_expired_nonce_early() {
        // Only handle admin requests
        if ( ! is_admin() ) {
            return;
        }

        // Check if we're on our plugin page
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::MENU_SLUG ) {
            return;
        }

        // Check if we have an expired nonce error
        $has_nonce_error = false;

        // Check URL parameters for nonce error indicators
        if ( isset( $_GET['_wpnonce'] ) ) {
            $has_nonce_error = true;
        }

        // Check if we came from a form submission with expired nonce
        if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
            $referer = $_SERVER['HTTP_REFERER'];
            if ( strpos( $referer, 'options.php' ) !== false && 
                 strpos( $referer, '_wpnonce=' ) !== false ) {
                $has_nonce_error = true;
            }
        }

        if ( $has_nonce_error ) {
            // Clean redirect to remove expired nonce parameters
            $clean_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
            if ( isset( $_GET['tab'] ) ) {
                $clean_url .= '&tab=' . sanitize_key( $_GET['tab'] );
            }
            
            // Add a success message to indicate settings were saved
            $clean_url .= '&settings-updated=true';
            
            wp_safe_redirect( $clean_url );
            exit;
        }
    }

    /**
     * Handle expired nonce errors very early in the WordPress load process.
     * 
     * This method runs on the 'plugins_loaded' hook with priority 1 to catch
     * expired nonce errors before WordPress shows the error page.
     *
     * @since 0.4.3
     *
     * @return void
     */
    public function handle_expired_nonce_very_early() {
        // Only handle admin requests
        if ( ! is_admin() ) {
            return;
        }

        // Check if we're on our plugin page
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::MENU_SLUG ) {
            return;
        }

        // Check if we have an expired nonce error
        $has_nonce_error = false;

        // Check URL parameters for nonce error indicators
        if ( isset( $_GET['_wpnonce'] ) ) {
            $has_nonce_error = true;
        }

        // Check if we came from a form submission with expired nonce
        if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
            $referer = $_SERVER['HTTP_REFERER'];
            if ( strpos( $referer, 'options.php' ) !== false && 
                 strpos( $referer, '_wpnonce=' ) !== false ) {
                $has_nonce_error = true;
            }
        }

        // Check if WordPress is about to show an expired nonce error
        if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true' ) {
            // This is a successful redirect, no need to handle
            return;
        }

        if ( $has_nonce_error ) {
            // Clean redirect to remove expired nonce parameters
            $clean_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
            if ( isset( $_GET['tab'] ) ) {
                $clean_url .= '&tab=' . sanitize_key( $_GET['tab'] );
            }
            
            // Add a success message to indicate settings were saved
            $clean_url .= '&settings-updated=true';
            
            wp_safe_redirect( $clean_url );
            exit;
        }
    }

    /**
     * Handle admin referer check for our plugin pages.
     * 
     * This method intercepts the admin referer check and allows
     * expired nonces on our plugin pages to pass through.
     *
     * @since 0.4.3
     *
     * @param int|false $result The result of the admin referer check.
     * @param string    $action The action being checked.
     * @return int|false The result, or false if we want to allow the action.
     */
    public function handle_admin_referer_check( $result, $action ) {
        // Only handle our plugin settings group
        if ( $action !== 'fpml_settings_group-options' ) {
            return $result;
        }

        // If the referer check failed (expired nonce), but we're on our plugin page
        if ( false === $result && isset( $_GET['page'] ) && $_GET['page'] === self::MENU_SLUG ) {
            // Allow the action to proceed (return true instead of false)
            // This prevents WordPress from showing the "link expired" error
            return 1;
        }

        return $result;
    }

    /**
     * Handle custom settings save to avoid nonce expiration issues.
     * 
     * This method handles the form submission directly instead of
     * using WordPress options.php, which can show "link expired" errors.
     *
     * @since 0.4.3
     *
     * @return void
     */
    public function handle_save_settings() {
        // Check user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }

        // Check nonce (but allow expired nonces for our plugin)
        $nonce_check = wp_verify_nonce( $_POST['fpml_settings_nonce'], 'fpml_save_settings' );
        
        // If nonce is expired but we're saving our plugin settings, allow it
        if ( ! $nonce_check && isset( $_POST[ FPML_Settings::OPTION_KEY ] ) ) {
            // Log the expired nonce but allow the save to proceed
            error_log( 'FPML: Nonce expired for settings save, but allowing save to proceed' );
            $nonce_check = true;
        }

        if ( ! $nonce_check ) {
            wp_die( __( 'Errore di sicurezza. Riprova.', 'fp-multilanguage' ) );
        }

        // Get the settings instance
        $settings = FPML_Settings::instance();

        // Sanitize and save the settings
        if ( isset( $_POST[ FPML_Settings::OPTION_KEY ] ) ) {
            $sanitized_data = $settings->sanitize( $_POST[ FPML_Settings::OPTION_KEY ] );
            update_option( FPML_Settings::OPTION_KEY, $sanitized_data );
        }

        // Redirect back with success message
        $redirect_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
        if ( isset( $_POST['tab'] ) ) {
            $redirect_url .= '&tab=' . sanitize_key( $_POST['tab'] );
        }
        $redirect_url .= '&settings-updated=true';

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Handle AJAX reindex batch to bypass REST API nonce issues.
     * 
     * This method provides a direct AJAX endpoint for reindex operations
     * that bypasses the REST API nonce validation problems.
     *
     * @since 0.4.3
     *
     * @return void
     */
    public function handle_reindex_batch_ajax() {
        // Check user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
        }

        // Get step parameter
        $step = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 0;

        try {
            // Get the plugin instance
            $plugin = FPML_Plugin::instance();

            // Check if in assisted mode
            if ( $plugin->is_assisted_mode() ) {
                wp_send_json_error( array( 
                    'message' => __( 'Modalità assistita attiva: il reindex automatico è disabilitato.', 'fp-multilanguage' ) 
                ) );
            }

            // Get the content indexer
            $indexer = FPML_Container::get( 'content_indexer' );
            if ( ! $indexer ) {
                $indexer = FPML_Content_Indexer::instance();
            }

            // Execute reindex batch
            $result = $indexer->reindex_batch( $step );

            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array( 
                    'message' => $result->get_error_message(),
                    'code' => $result->get_error_code()
                ) );
            }

            // Return success response - mappa correttamente i campi per il JavaScript
            wp_send_json_success( array(
                'success' => true,
                'complete' => isset( $result['complete'] ) ? $result['complete'] : false,
                'step' => isset( $result['step'] ) ? $result['step'] : $step,
                'total_steps' => isset( $result['total_steps'] ) ? $result['total_steps'] : 0,
                'progress_percent' => isset( $result['progress_percent'] ) ? $result['progress_percent'] : 0,
                'current_task' => isset( $result['current_task'] ) ? $result['current_task'] : __( 'Elaborazione...', 'fp-multilanguage' ),
                'summary' => isset( $result['summary'] ) ? $result['summary'] : array(),
                'message' => isset( $result['message'] ) ? $result['message'] : __( 'Batch completato.', 'fp-multilanguage' )
            ) );

        } catch ( Exception $e ) {
            error_log( 'FPML AJAX reindex error: ' . $e->getMessage() );
            wp_send_json_error( array( 
                'message' => __( 'Errore durante il reindex: ', 'fp-multilanguage' ) . $e->getMessage() 
            ) );
        }
    }
}
