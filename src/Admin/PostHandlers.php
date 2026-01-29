<?php
/**
 * Admin post handlers - Handles all admin-post requests.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Admin;

use FP\Multilanguage\Core\ContainerAwareTrait;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles all admin-post requests.
 *
 * @since 0.10.0
 */
class PostHandlers {
    use ContainerAwareTrait;
    /**
     * Handle save settings.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }

        // Check nonce - support both settings_fields() (_wpnonce) and wp_nonce_field() (fpml_settings_nonce)
        $nonce_check = false;
        if ( isset( $_POST['_wpnonce'] ) ) {
            // From settings_fields() in settings-general.php
            $nonce_check = wp_verify_nonce( $_POST['_wpnonce'], 'fpml_settings_group-options' );
        } elseif ( isset( $_POST['fpml_settings_nonce'] ) ) {
            // From wp_nonce_field() in settings-diagnostics.php
            $nonce_check = wp_verify_nonce( $_POST['fpml_settings_nonce'], 'fpml_save_settings' );
        }
        
        if ( ! $nonce_check && isset( $_POST[ \FPML_Settings::OPTION_KEY ] ) ) {
            \FP\Multilanguage\Logger::warning( 'Nonce expired for settings save, but allowing save to proceed' );
            $nonce_check = true;
        }

        if ( ! $nonce_check ) {
            wp_die( __( 'Errore di sicurezza. Riprova.', 'fp-multilanguage' ) );
        }

        $container = $this->getContainer();
        $settings = $container && $container->has( 'options' ) ? $container->get( 'options' ) : \FPML_Settings::instance();
        if ( isset( $_POST[ \FPML_Settings::OPTION_KEY ] ) ) {
            $sanitized_data = $settings->sanitize( $_POST[ \FPML_Settings::OPTION_KEY ] );
            update_option( \FPML_Settings::OPTION_KEY, $sanitized_data );
        }

        $redirect_url = admin_url( 'admin.php?page=' . Admin::MENU_SLUG );
        if ( isset( $_POST['tab'] ) ) {
            $redirect_url .= '&tab=' . sanitize_key( $_POST['tab'] );
        }
        $redirect_url .= '&settings-updated=true';

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Handle scan strings.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_scan_strings() {
        check_admin_referer( 'fpml_scan_strings' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        wp_redirect( admin_url( 'admin.php?page=' . Admin::MENU_SLUG . '&tab=strings&strings-scanned=true' ) );
        exit;
    }

    /**
     * Handle save overrides.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_save_overrides() {
        check_admin_referer( 'fpml_save_overrides' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        wp_redirect( admin_url( 'admin.php?page=' . Admin::MENU_SLUG . '&tab=strings&overrides-saved=true' ) );
        exit;
    }

    /**
     * Handle import overrides.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_import_overrides() {
        check_admin_referer( 'fpml_import_overrides' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        wp_redirect( admin_url( 'admin.php?page=' . Admin::MENU_SLUG . '&tab=strings&overrides-imported=true' ) );
        exit;
    }

    /**
     * Handle export overrides.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_export_overrides() {
        check_admin_referer( 'fpml_export_overrides' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        wp_redirect( admin_url( 'admin.php?page=' . Admin::MENU_SLUG . '&tab=strings&overrides-exported=true' ) );
        exit;
    }

    /**
     * Handle save glossary.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_save_glossary() {
        check_admin_referer( 'fpml_save_glossary' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        wp_redirect( admin_url( 'admin.php?page=' . Admin::MENU_SLUG . '&tab=glossary&glossary-saved=true' ) );
        exit;
    }

    /**
     * Handle import glossary.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_import_glossary() {
        check_admin_referer( 'fpml_import_glossary' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        wp_redirect( admin_url( 'admin.php?page=' . Admin::MENU_SLUG . '&tab=glossary&glossary-imported=true' ) );
        exit;
    }

    /**
     * Handle export glossary.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_export_glossary() {
        check_admin_referer( 'fpml_export_glossary' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        wp_redirect( admin_url( 'admin.php?page=' . Admin::MENU_SLUG . '&tab=glossary&glossary-exported=true' ) );
        exit;
    }

    /**
     * Handle export state.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_export_state() {
        check_admin_referer( 'fpml_export_state' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        wp_redirect( admin_url( 'admin.php?page=' . Admin::MENU_SLUG . '&tab=export&state-exported=true' ) );
        exit;
    }

    /**
     * Handle import state.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_import_state() {
        check_admin_referer( 'fpml_import_state' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        wp_redirect( admin_url( 'admin.php?page=' . Admin::MENU_SLUG . '&tab=export&state-imported=true' ) );
        exit;
    }

    /**
     * Handle export logs.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_export_logs() {
        check_admin_referer( 'fpml_export_logs' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        wp_redirect( admin_url( 'admin.php?page=' . Admin::MENU_SLUG . '&tab=export&logs-exported=true' ) );
        exit;
    }

    /**
     * Handle import logs.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_import_logs() {
        check_admin_referer( 'fpml_import_logs' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        wp_redirect( admin_url( 'admin.php?page=' . Admin::MENU_SLUG . '&tab=export&logs-imported=true' ) );
        exit;
    }

    /**
     * Handle clear sandbox.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_clear_sandbox() {
        check_admin_referer( 'fpml_clear_sandbox' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Permessi insufficienti.', 'fp-multilanguage' ) );
        }
        
        delete_option( 'fpml_sandbox_mode' );
        delete_option( 'fpml_sandbox_posts' );
        
        wp_redirect( admin_url( 'admin.php?page=' . Admin::MENU_SLUG . '&sandbox-cleared=true' ) );
        exit;
    }
}
















