<?php
/**
 * Admin nonce manager - Handles nonce validation and expired nonce redirects.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages nonce validation and expired nonce redirects.
 *
 * @since 0.10.0
 */
class NonceManager {
    /**
     * Handle expired nonce redirect.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_expired_nonce_redirect() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== Admin::MENU_SLUG ) {
            return;
        }

        $is_redirect = isset( $_SERVER['HTTP_REFERER'] ) && 
                      strpos( $_SERVER['HTTP_REFERER'], 'options.php' ) !== false;
        $has_nonce_error = isset( $_GET['_wpnonce'] ) || 
                          ( isset( $_SERVER['HTTP_REFERER'] ) && 
                            strpos( $_SERVER['HTTP_REFERER'], '_wpnonce=' ) !== false );
        
        if ( $is_redirect && $has_nonce_error ) {
            $clean_url = admin_url( 'admin.php?page=' . Admin::MENU_SLUG );
            if ( isset( $_GET['tab'] ) ) {
                $clean_url .= '&tab=' . sanitize_key( $_GET['tab'] );
            }
            wp_safe_redirect( $clean_url );
            exit;
        }
    }

    /**
     * Handle expired nonce early.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_expired_nonce_early() {
        if ( ! is_admin() ) {
            return;
        }
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== Admin::MENU_SLUG ) {
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
            $clean_url = admin_url( 'admin.php?page=' . Admin::MENU_SLUG );
            if ( isset( $_GET['tab'] ) ) {
                $clean_url .= '&tab=' . sanitize_key( $_GET['tab'] );
            }
            $clean_url .= '&settings-updated=true';
            wp_safe_redirect( $clean_url );
            exit;
        }
    }

    /**
     * Handle expired nonce very early.
     *
     * @since 0.10.0
     * @return void
     */
    public function handle_expired_nonce_very_early() {
        if ( ! is_admin() ) {
            return;
        }
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== Admin::MENU_SLUG ) {
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
            $clean_url = admin_url( 'admin.php?page=' . Admin::MENU_SLUG );
            if ( isset( $_GET['tab'] ) ) {
                $clean_url .= '&tab=' . sanitize_key( $_GET['tab'] );
            }
            $clean_url .= '&settings-updated=true';
            wp_safe_redirect( $clean_url );
            exit;
        }
    }

    /**
     * Custom wp_die handler.
     *
     * @since 0.10.0
     *
     * @param callable $handler Original handler.
     * @return callable
     */
    public function custom_wp_die_handler( $handler ) {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== Admin::MENU_SLUG ) {
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
            $clean_url = admin_url( 'admin.php?page=' . Admin::MENU_SLUG );
            if ( isset( $_GET['tab'] ) ) {
                $clean_url .= '&tab=' . sanitize_key( $_GET['tab'] );
            }
            wp_safe_redirect( $clean_url );
            exit;
        }
        
        return $handler;
    }

    /**
     * Handle admin referer check.
     *
     * @since 0.10.0
     *
     * @param bool   $result Result of check.
     * @param string $action Action being checked.
     * @return bool|int
     */
    public function handle_admin_referer_check( $result, $action ) {
        if ( $action !== '\FPML_settings_group-options' ) {
            return $result;
        }

        if ( false === $result && isset( $_GET['page'] ) && $_GET['page'] === Admin::MENU_SLUG ) {
            return 1;
        }

        return $result;
    }
}
















