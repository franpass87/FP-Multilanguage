<?php
/**
 * Language resolver - Determines current language and manages cookies.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Language;

use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles language detection and cookie management.
 *
 * @since 0.10.0
 */
class LanguageResolver {
    /**
     * Cookie key for language preference.
     */
    const COOKIE_NAME = 'fpml_lang_pref';

    /**
     * Cookie lifetime (30 days).
     */
    const COOKIE_TTL = 2592000;

    /**
     * Source language slug fallback (used only if settings are not yet available).
     */
    const SOURCE = 'it';

    /**
     * Current language code.
     *
     * @var string
     */
    protected $current = '';

    /**
     * Cached settings instance.
     *
     * @var \FPML_Settings
     */
    protected $settings;

    /**
     * Constructor.
     *
     * @param \FPML_Settings $settings Settings instance.
     */
    public function __construct( $settings ) {
        $this->settings = $settings;
        $this->current  = $this->get_source_language();
    }

    /**
     * Get the configured source language, falling back to the SOURCE constant.
     *
     * @return string
     */
    protected function get_source_language(): string {
        if ( $this->settings && method_exists( $this->settings, 'get' ) ) {
            $src = $this->settings->get( 'source_language', '' );
            if ( ! empty( $src ) ) {
                return (string) $src;
            }
        }
        if ( function_exists( 'fpml_get_source_language' ) ) {
            $src = fpml_get_source_language();
            if ( ! empty( $src ) ) {
                return (string) $src;
            }
        }
        return self::SOURCE;
    }

    /**
     * Determine current language from query vars, request and cookies.
     *
     * @since 0.2.0
     *
     * @param WP_Query $query Current query.
     *
     * @return void
     */
    public function determine_language( $query ) {
        if ( ! $query->is_main_query() || is_admin() ) {
            return;
        }

        $lang = $this->get_source_language();

        // Get enabled languages
        $language_manager = function_exists( 'fpml_get_language_manager' ) ? fpml_get_language_manager() : null;
        if ( ! $language_manager ) {
            return;
        }
        $enabled_languages   = $language_manager->get_enabled_languages();
        $available_languages = $language_manager->get_all_languages();

        $requested = $query->get( 'fpml_lang' );

        if ( is_string( $requested ) ) {
            $requested = sanitize_key( $requested );
        } else {
            $requested = '';
        }
        if ( empty( $requested ) && isset( $_GET['lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $requested = strtolower( sanitize_text_field( wp_unslash( $_GET['lang'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        // Check if requested language is enabled
        if ( ! empty( $requested ) && in_array( $requested, $enabled_languages, true ) ) {
            $lang = $requested;
        } else {
            // Check path for any enabled language
            $path = $this->get_current_path();
            $lowered = strtolower( $path );
            
            foreach ( $enabled_languages as $target_lang ) {
                if ( ! isset( $available_languages[ $target_lang ] ) ) {
                    continue;
                }
                
                $lang_info = $available_languages[ $target_lang ];
                if ( ! is_array( $lang_info ) || empty( $lang_info['slug'] ) ) {
                    continue;
                }
                $lang_slug = trim( $lang_info['slug'], '/' );
                
                $is_target = ( 0 === strpos( $lowered, '/' . $lang_slug . '/' ) );
                
                if ( ! $is_target ) {
                    $is_target = ( '/' . $lang_slug === rtrim( $lowered, '/' ) );
                }
                
                if ( $is_target ) {
                    $lang = $target_lang;
                    break;
                }
            }
        }

        $previous_lang = $this->current;
        $this->current = $lang;

        // Notify other plugins about language change
        if ( $previous_lang !== $lang ) {
            do_action( 'fpml_language_determined', $lang, $previous_lang );
        }

        $query->set( 'fpml_lang', $lang );
    }

    /**
     * Persist language choice in cookie for subsequent visits.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function persist_language_cookie() {
        if ( is_admin() || headers_sent() ) {
            return;
        }

        if ( ! $this->has_cookie_consent() ) {
            return;
        }

        $cookie_value = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ) : ''; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE

        if ( $cookie_value === $this->current ) {
            return;
        }

        setcookie( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
            self::COOKIE_NAME,
            $this->current,
            time() + self::COOKIE_TTL,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );

        $_COOKIE[ self::COOKIE_NAME ] = $this->current; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
    }

    /**
     * Get current language code.
     *
     * @since 0.2.0
     *
     * @return string
     */
    public function get_current_language() {
        // Allow other plugins to filter the current language
        return apply_filters( 'fpml_current_language', $this->current );
    }

    /**
     * Set current language.
     *
     * @since 0.10.0
     *
     * @param string $lang Language code.
     * @return void
     */
    public function set_current_language( $lang ) {
        $this->current = $lang;
    }

    /**
     * Get current path from request.
     *
     * @since 0.10.0
     *
     * @return string
     */
    protected function get_current_path() {
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        if ( empty( $request_uri ) ) {
            return '';
        }

        $parsed = wp_parse_url( $request_uri );
        $path   = isset( $parsed['path'] ) ? $parsed['path'] : '';

        // Strip WP installation subdir so callers get a path relative to home_url
        $home_path = wp_parse_url( home_url(), PHP_URL_PATH );
        if ( $home_path && '/' !== $home_path ) {
            $home_path = rtrim( $home_path, '/' );
            $path      = preg_replace( '#^' . preg_quote( $home_path, '#' ) . '#', '', $path );
        }

        return $path ?: '/';
    }

    /**
     * Check if cookie consent is available.
     *
     * @since 0.10.0
     *
     * @return bool
     */
    protected function has_cookie_consent() {
        // Check if cookie consent plugin is active
        if ( function_exists( 'fp_privacy_has_consent' ) ) {
            return fp_privacy_has_consent( 'necessary' );
        }

        // Default: allow cookies if no consent plugin is active
        return true;
    }
}
















