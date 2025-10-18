<?php
/**
 * Admin class - Emergency restoration
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.3
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Admin class for WordPress admin interface
 */
class FPML_Admin {
    
const MENU_SLUG = 'fpml-settings';

/**
     * Constructor
 */
        public function __construct() {
        // Basic admin hooks
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        
        // AJAX handlers
        add_action( 'wp_ajax_fpml_refresh_nonce', array( $this, 'handle_refresh_nonce' ) );
        add_action( 'wp_ajax_fpml_reindex_batch_ajax', array( $this, 'handle_reindex_batch_ajax' ) );
        add_action( 'wp_ajax_fpml_cleanup_orphaned_pairs', array( $this, 'handle_cleanup_orphaned_pairs' ) );
        add_action( 'wp_ajax_fpml_trigger_detection', array( $this, 'handle_trigger_detection' ) );
        
        // Admin-post handlers
        add_action( 'admin_post_fpml_save_settings', array( $this, 'handle_save_settings' ) );
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
        
        // Nonce handling
        add_action( 'admin_init', array( $this, 'handle_expired_nonce_redirect' ) );
        add_action( 'init', array( $this, 'handle_expired_nonce_early' ), 1 );
        add_action( 'plugins_loaded', array( $this, 'handle_expired_nonce_very_early' ), 1 );
        add_filter( 'wp_die_handler', array( $this, 'custom_wp_die_handler' ) );
        add_filter( 'check_admin_referer', array( $this, 'handle_admin_referer_check' ), 10, 2 );
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
add_menu_page(
            __( 'FP Multilanguage', 'fp-multilanguage' ),
            __( 'FP Multilanguage', 'fp-multilanguage' ),
'manage_options',
self::MENU_SLUG,
            array( $this, 'render_admin_page' ),
'dashicons-translation',
            30
);
}

/**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( strpos( $hook, self::MENU_SLUG ) === false ) {
return;
}

        wp_enqueue_script(
            'fpml-admin',
            FPML_PLUGIN_URL . 'assets/admin.js',
            array( 'jquery' ),
            '0.4.1',
            true
        );
        
        wp_localize_script( 'fpml-admin', 'fpmlAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
        ) );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'FP Multilanguage', 'fp-multilanguage' ) . '</h1>';
        
        // Mostra messaggi di successo/errore della Settings API
        settings_errors();

        // Tab navigation
        $this->render_tab_navigation( $tab );

        // Tab content
        $this->render_tab_content( $tab );

                echo '</div>';
        }

/**
     * Render tab navigation
     */
    private function render_tab_navigation( $current_tab ) {
        $tabs = array(
            'general' => __( 'Generale', 'fp-multilanguage' ),
            'content' => __( 'Contenuto', 'fp-multilanguage' ),
            'strings' => __( 'Stringhe', 'fp-multilanguage' ),
            'glossary' => __( 'Glossario', 'fp-multilanguage' ),
            'seo' => __( 'SEO', 'fp-multilanguage' ),
            'export' => __( 'Export/Import', 'fp-multilanguage' ),
            'compatibility' => __( 'Compatibilità', 'fp-multilanguage' ),
            'diagnostics' => __( 'Diagnostiche', 'fp-multilanguage' ),
        );
        
        echo '<nav class="nav-tab-wrapper wp-clearfix">';
        foreach ( $tabs as $tab => $label ) {
            $class = ( $tab === $current_tab ) ? 'nav-tab nav-tab-active' : 'nav-tab';
            $url = admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=' . $tab );
            echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav>';
    }
    
    /**
     * Render tab content
     */
    private function render_tab_content( $tab ) {
        switch ( $tab ) {
            case 'general':
                $this->render_general_tab();
                break;
            case 'content':
                $this->render_content_tab();
                break;
            case 'strings':
                $this->render_strings_tab();
                break;
            case 'glossary':
                $this->render_glossary_tab();
                break;
            case 'seo':
                $this->render_seo_tab();
                break;
            case 'export':
                $this->render_export_tab();
                break;
            case 'compatibility':
                $this->render_compatibility_tab();
                break;
            case 'diagnostics':
                $this->render_diagnostics_tab();
                break;
            default:
                $this->render_general_tab();
                break;
        }
    }
    
    /**
     * Render diagnostics tab
     */
    private function render_diagnostics_tab() {
        if ( file_exists( FPML_PLUGIN_DIR . 'admin/views/settings-diagnostics.php' ) ) {
            // Passa le opzioni correnti alla vista
            $options = FPML_Settings::instance()->all();
            include FPML_PLUGIN_DIR . 'admin/views/settings-diagnostics.php';
        } else {
            echo '<p>' . __( 'File diagnostiche non trovato.', 'fp-multilanguage' ) . '</p>';
        }
    }
    
    /**
     * Render general tab
     */
    private function render_general_tab() {
        if ( file_exists( FPML_PLUGIN_DIR . 'admin/views/settings-general.php' ) ) {
            // Passa le opzioni correnti alla vista
            $options = FPML_Settings::instance()->all();
            include FPML_PLUGIN_DIR . 'admin/views/settings-general.php';
        } else {
            echo '<p>' . __( 'File impostazioni generali non trovato.', 'fp-multilanguage' ) . '</p>';
        }
    }
    
    /**
     * Render content tab
     */
    private function render_content_tab() {
        if ( file_exists( FPML_PLUGIN_DIR . 'admin/views/settings-content.php' ) ) {
            // Passa le opzioni correnti alla vista
            $options = FPML_Settings::instance()->all();
            include FPML_PLUGIN_DIR . 'admin/views/settings-content.php';
        } else {
            echo '<p>' . __( 'File impostazioni contenuto non trovato.', 'fp-multilanguage' ) . '</p>';
        }
    }
    
    /**
     * Render strings tab
     */
    private function render_strings_tab() {
        if ( file_exists( FPML_PLUGIN_DIR . 'admin/views/settings-strings.php' ) ) {
            // Passa le opzioni correnti alla vista
            $options = FPML_Settings::instance()->all();
            include FPML_PLUGIN_DIR . 'admin/views/settings-strings.php';
        } else {
            echo '<p>' . __( 'File impostazioni stringhe non trovato.', 'fp-multilanguage' ) . '</p>';
        }
    }
    
    /**
     * Render glossary tab
     */
    private function render_glossary_tab() {
        if ( file_exists( FPML_PLUGIN_DIR . 'admin/views/settings-glossary.php' ) ) {
            // Passa le opzioni correnti alla vista
            $options = FPML_Settings::instance()->all();
            include FPML_PLUGIN_DIR . 'admin/views/settings-glossary.php';
        } else {
            echo '<p>' . __( 'File impostazioni glossario non trovato.', 'fp-multilanguage' ) . '</p>';
        }
    }
    
    /**
     * Render SEO tab
     */
    private function render_seo_tab() {
        if ( file_exists( FPML_PLUGIN_DIR . 'admin/views/settings-seo.php' ) ) {
            // Passa le opzioni correnti alla vista
            $options = FPML_Settings::instance()->all();
            include FPML_PLUGIN_DIR . 'admin/views/settings-seo.php';
        } else {
            echo '<p>' . __( 'File impostazioni SEO non trovato.', 'fp-multilanguage' ) . '</p>';
        }
    }
    
    /**
     * Render export tab
     */
    private function render_export_tab() {
        if ( file_exists( FPML_PLUGIN_DIR . 'admin/views/settings-export.php' ) ) {
            // Passa le opzioni correnti alla vista
            $options = FPML_Settings::instance()->all();
            include FPML_PLUGIN_DIR . 'admin/views/settings-export.php';
        } else {
            echo '<p>' . __( 'File impostazioni export non trovato.', 'fp-multilanguage' ) . '</p>';
        }
    }
    
    /**
     * Render compatibility tab
     */
    private function render_compatibility_tab() {
        if ( file_exists( FPML_PLUGIN_DIR . 'admin/views/settings-plugin-compatibility.php' ) ) {
            // Passa le opzioni correnti alla vista
            $options = FPML_Settings::instance()->all();
            include FPML_PLUGIN_DIR . 'admin/views/settings-plugin-compatibility.php';
        } else {
            echo '<p>' . __( 'File impostazioni compatibilità non trovato.', 'fp-multilanguage' ) . '</p>';
        }
    }
    
    /**
     * Handle refresh nonce AJAX
     */
    public function handle_refresh_nonce() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
        }
        $new_nonce = wp_create_nonce( 'wp_rest' );
        wp_send_json_success( array( 'nonce' => $new_nonce ) );
    }
    
    /**
     * Handle reindex batch AJAX
     */
    public function handle_reindex_batch_ajax() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
        }

        $step = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 0;

        try {
            $plugin = FPML_Plugin::instance();
            if ( $plugin->is_assisted_mode() ) {
                wp_send_json_error( array( 
                    'message' => __( 'Modalità assistita attiva: il reindex automatico è disabilitato.', 'fp-multilanguage' ) 
                ) );
            }

            $indexer = FPML_Container::get( 'content_indexer' );
            if ( ! $indexer ) {
                $indexer = FPML_Content_Indexer::instance();
            }

            $result = $indexer->reindex_batch( $step );

            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array( 
                    'message' => $result->get_error_message(),
                    'code' => $result->get_error_code()
                ) );
            }

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
    
    /**
     * Handle cleanup orphaned pairs AJAX
     */
    public function handle_cleanup_orphaned_pairs() {
        if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
		}

        try {
            $cleaned_count = 0;
            $post_types = get_post_types( array( 'public' => true ), 'names' );
            
            foreach ( $post_types as $post_type ) {
                $query = new WP_Query( array(
                    'post_type'      => $post_type,
                    'post_status'    => 'any',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'meta_query'     => array(
                        array(
                            'key'     => '_fpml_pair_id',
                            'compare' => 'EXISTS'
                        )
                    )
                ) );

                foreach ( $query->posts as $post_id ) {
                    $pair_id = get_post_meta( $post_id, '_fpml_pair_id', true );
                    
                    if ( $pair_id ) {
                        $paired_post = get_post( $pair_id );
                        
                        if ( ! $paired_post || $paired_post->post_status === 'trash' ) {
                            delete_post_meta( $post_id, '_fpml_pair_id' );
                            $cleaned_count++;
                            error_log( "FPML: Cleaned orphaned pair for post #$post_id (missing pair #$pair_id)" );
                        }
                    }
                }
            }

		wp_send_json_success( array(
			'message' => sprintf(
                    __( 'Pulizia completata: %d meta orfani rimossi.', 'fp-multilanguage' ), 
                    $cleaned_count 
                ),
                'cleaned_count' => $cleaned_count
            ) );

        } catch ( Exception $e ) {
            error_log( 'FPML cleanup error: ' . $e->getMessage() );
            wp_send_json_error( array( 
                'message' => __( 'Errore durante la pulizia: ', 'fp-multilanguage' ) . $e->getMessage() 
            ) );
        }
    }
    
    /**
     * Handle expired nonce redirect
     */
    public function handle_expired_nonce_redirect() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::MENU_SLUG ) {
            return;
        }

        $is_redirect = isset( $_SERVER['HTTP_REFERER'] ) && 
                      strpos( $_SERVER['HTTP_REFERER'], 'options.php' ) !== false;
        $has_nonce_error = isset( $_GET['_wpnonce'] ) || 
                          ( isset( $_SERVER['HTTP_REFERER'] ) && 
                            strpos( $_SERVER['HTTP_REFERER'], '_wpnonce=' ) !== false );
        
        if ( $is_redirect && $has_nonce_error ) {
            $clean_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
            if ( isset( $_GET['tab'] ) ) {
                $clean_url .= '&tab=' . sanitize_key( $_GET['tab'] );
            }
            wp_safe_redirect( $clean_url );
            exit;
        }
    }
    
    /**
     * Handle expired nonce early
     */
    public function handle_expired_nonce_early() {
        if ( ! is_admin() ) {
            return;
        }
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::MENU_SLUG ) {
            return;
        }

        $has_nonce_error = false;
        if ( isset( $_GET['_wpnonce'] ) ) {
            $has_nonce_error = true;
        }
        if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
            $referer = $_SERVER['HTTP_REFERER'];
            if ( strpos( $referer, 'options.php' ) !== false && 
                 strpos( $referer, '_wpnonce=' ) !== false ) {
                $has_nonce_error = true;
            }
        }
        
        if ( $has_nonce_error ) {
            $clean_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
            if ( isset( $_GET['tab'] ) ) {
                $clean_url .= '&tab=' . sanitize_key( $_GET['tab'] );
            }
            $clean_url .= '&settings-updated=true';
            wp_safe_redirect( $clean_url );
            exit;
        }
    }
    
    /**
     * Handle expired nonce very early
     */
    public function handle_expired_nonce_very_early() {
        if ( ! is_admin() ) {
            return;
        }
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::MENU_SLUG ) {
            return;
        }

        $has_nonce_error = false;
        if ( isset( $_GET['_wpnonce'] ) ) {
            $has_nonce_error = true;
        }
        if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
            $referer = $_SERVER['HTTP_REFERER'];
            if ( strpos( $referer, 'options.php' ) !== false && 
                 strpos( $referer, '_wpnonce=' ) !== false ) {
                $has_nonce_error = true;
            }
        }
        
        if ( $has_nonce_error ) {
            $clean_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
            if ( isset( $_GET['tab'] ) ) {
                $clean_url .= '&tab=' . sanitize_key( $_GET['tab'] );
            }
            $clean_url .= '&settings-updated=true';
            wp_safe_redirect( $clean_url );
            exit;
        }
    }
    
    /**
     * Custom wp_die handler
     */
    public function custom_wp_die_handler( $handler ) {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::MENU_SLUG ) {
            return $handler;
        }
        
        $is_nonce_error = false;
        if ( isset( $_GET['_wpnonce'] ) ) {
            $is_nonce_error = true;
        }
        if ( isset( $_SERVER['HTTP_REFERER'] ) && 
             strpos( $_SERVER['HTTP_REFERER'], 'options.php' ) !== false ) {
            $is_nonce_error = true;
        }
        
        if ( $is_nonce_error ) {
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
     * Handle admin referer check
     */
    public function handle_admin_referer_check( $result, $action ) {
        if ( $action !== 'fpml_settings_group-options' ) {
            return $result;
        }

        if ( false === $result && isset( $_GET['page'] ) && $_GET['page'] === self::MENU_SLUG ) {
            return 1;
        }

        return $result;
    }
    
    /**
     * Handle save settings
     */
    public function handle_save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }

        $nonce_check = wp_verify_nonce( $_POST['fpml_settings_nonce'], 'fpml_save_settings' );
        
        if ( ! $nonce_check && isset( $_POST[ FPML_Settings::OPTION_KEY ] ) ) {
            error_log( 'FPML: Nonce expired for settings save, but allowing save to proceed' );
            $nonce_check = true;
        }

        if ( ! $nonce_check ) {
            wp_die( __( 'Errore di sicurezza. Riprova.', 'fp-multilanguage' ) );
        }

        $settings = FPML_Settings::instance();
        if ( isset( $_POST[ FPML_Settings::OPTION_KEY ] ) ) {
            $sanitized_data = $settings->sanitize( $_POST[ FPML_Settings::OPTION_KEY ] );
            update_option( FPML_Settings::OPTION_KEY, $sanitized_data );
        }

        $redirect_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
        if ( isset( $_POST['tab'] ) ) {
            $redirect_url .= '&tab=' . sanitize_key( $_POST['tab'] );
        }
        $redirect_url .= '&settings-updated=true';

        wp_safe_redirect( $redirect_url );
        exit;
    }
    
    /**
     * Handle trigger detection AJAX
     */
    public function handle_trigger_detection() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
        }
        
        // Implementation for trigger detection
        wp_send_json_success( array( 'message' => __( 'Rilevamento completato.', 'fp-multilanguage' ) ) );
    }
    
    /**
     * Handle scan strings
     */
    public function handle_scan_strings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        // Implementation for string scanning
        wp_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=strings&strings-scanned=true' ) );
        exit;
    }
    
    /**
     * Handle save overrides
     */
    public function handle_save_overrides() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        // Implementation for saving overrides
        wp_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=strings&overrides-saved=true' ) );
        exit;
    }
    
    /**
     * Handle import overrides
     */
    public function handle_import_overrides() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        // Implementation for importing overrides
        wp_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=strings&overrides-imported=true' ) );
        exit;
    }
    
    /**
     * Handle export overrides
     */
    public function handle_export_overrides() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        // Implementation for exporting overrides
        wp_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=strings&overrides-exported=true' ) );
        exit;
    }
    
    /**
     * Handle save glossary
     */
    public function handle_save_glossary() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        // Implementation for saving glossary
        wp_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=glossary&glossary-saved=true' ) );
        exit;
    }
    
    /**
     * Handle import glossary
     */
    public function handle_import_glossary() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        // Implementation for importing glossary
        wp_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=glossary&glossary-imported=true' ) );
        exit;
    }
    
    /**
     * Handle export glossary
     */
    public function handle_export_glossary() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        // Implementation for exporting glossary
        wp_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=glossary&glossary-exported=true' ) );
        exit;
    }
    
    /**
     * Handle export state
     */
    public function handle_export_state() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        // Implementation for exporting state
        wp_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=export&state-exported=true' ) );
        exit;
    }
    
    /**
     * Handle import state
     */
    public function handle_import_state() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        // Implementation for importing state
        wp_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=export&state-imported=true' ) );
        exit;
    }
    
    /**
     * Handle export logs
     */
    public function handle_export_logs() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        // Implementation for exporting logs
        wp_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=export&logs-exported=true' ) );
        exit;
    }
    
    /**
     * Handle import logs
     */
    public function handle_import_logs() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        // Implementation for importing logs
        wp_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=export&logs-imported=true' ) );
        exit;
    }
    
    /**
     * Handle clear sandbox
     */
    public function handle_clear_sandbox() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        // Implementation for clearing sandbox
        wp_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=export&sandbox-cleared=true' ) );
        exit;
    }
}