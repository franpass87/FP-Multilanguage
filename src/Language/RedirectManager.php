<?php
/**
 * Redirect manager - Handles browser language redirects and post redirects.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Language;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages redirects based on language preferences.
 *
 * @since 0.10.0
 */
class RedirectManager {
    /**
     * Cookie key for language preference.
     */
    const COOKIE_NAME = '\FPML_lang_pref';

    /**
     * Source language slug.
     */
    const SOURCE = 'it';

    /**
     * Cached settings instance.
     *
     * @var \FPML_Settings
     */
    protected $settings;

    /**
     * Language resolver instance.
     *
     * @var LanguageResolver
     */
    protected $resolver;

    /**
     * Permalink filter instance.
     *
     * @var PermalinkFilter
     */
    protected $permalink_filter;

    /**
     * Constructor.
     *
     * @param \FPML_Settings    $settings        Settings instance.
     * @param LanguageResolver $resolver        Language resolver instance.
     * @param PermalinkFilter  $permalink_filter Permalink filter instance.
     */
    public function __construct( $settings, LanguageResolver $resolver, PermalinkFilter $permalink_filter ) {
        $this->settings = $settings;
        $this->resolver = $resolver;
        $this->permalink_filter = $permalink_filter;
    }

    /**
     * Redirect first-time visitors based on browser language preference.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function maybe_redirect_browser_language() {
        if ( is_admin() || wp_doing_ajax() || defined( 'REST_REQUEST' ) ) {
            return;
        }

        $current_lang = $this->resolver->get_current_language();
        if ( fpml_is_target_language( $current_lang ) ) {
            return;
        }

        if ( ! $this->settings->get( 'browser_redirect', false ) ) {
            return;
        }

        if ( ! $this->has_cookie_consent() ) {
            return;
        }

        if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) { // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
            return;
        }

        $accept_language = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) : ''; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.server___SERVER

        if ( empty( $accept_language ) ) {
            return;
        }

        if ( false === stripos( $accept_language, 'en' ) ) {
            return;
        }

        $language_manager = fpml_get_language_manager();
        $enabled_languages = $language_manager->get_enabled_languages();
        $target_lang = ! empty( $enabled_languages ) ? $enabled_languages[0] : 'en';
        $target_url = $this->get_url_for_language( $target_lang );

        if ( empty( $target_url ) || headers_sent() ) {
            return;
        }

        wp_safe_redirect( $target_url, 302 );
        exit;
    }

    /**
     * Redirect translated posts to /en/ URL when accessed via Italian URL.
     *
     * @since 0.9.3
     *
     * @return void
     */
    public function redirect_translated_posts_to_en() {
        if ( is_admin() || wp_doing_ajax() || defined( 'REST_REQUEST' ) ) {
            return;
        }

        $routing = $this->settings->get( 'routing_mode', 'segment' );
        if ( 'segment' !== $routing ) {
            return;
        }

        $current_path = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        $current_url = home_url( $current_path );
        
        $language_manager = fpml_get_language_manager();
        $enabled_languages = $language_manager->get_enabled_languages();
        $has_target_lang_path = false;
        
        foreach ( $enabled_languages as $lang_code ) {
            $lang_info = $language_manager->get_language_info( $lang_code );
            if ( $lang_info && ! empty( $lang_info['slug'] ) ) {
                $lang_slug = trim( $lang_info['slug'], '/' );
                if ( false !== strpos( $current_path, '/' . $lang_slug . '/' ) ||
                     false !== strpos( $current_path, '/' . $lang_slug . '-' ) ||
                     '/' . $lang_slug === rtrim( $current_path, '/' ) ||
                     '/' . $lang_slug . '/' === rtrim( $current_path, '/' ) ||
                     preg_match( '#^/' . preg_quote( $lang_slug, '#' ) . '(/|$)#', $current_path ) ) {
                    $has_target_lang_path = true;
                    break;
                }
            }
        }
        
        if ( $has_target_lang_path || false !== strpos( $current_path, '/en-' ) ) {
            return;
        }

        if ( ! is_singular() ) {
            return;
        }

        global $post;
        if ( ! $post instanceof \WP_Post ) {
            return;
        }

        if ( ! get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
            return;
        }

        $current_url_normalized = rtrim( parse_url( $current_url, PHP_URL_PATH ), '/' );
        
        $expected_url = $this->permalink_filter->filter_translation_permalink( get_permalink( $post->ID ), $post, true );
        
        if ( empty( $expected_url ) ) {
            return;
        }
        
        $expected_url_normalized = rtrim( parse_url( $expected_url, PHP_URL_PATH ), '/' );
        
        if ( $current_url_normalized === $expected_url_normalized ) {
            return;
        }
        
        if ( $expected_url === get_permalink( $post->ID ) ) {
            return;
        }

        if ( ! fpml_url_contains_target_language( $expected_url_normalized ) ) {
            return;
        }
        
        wp_safe_redirect( $expected_url, 301 );
        exit;
    }

    /**
     * Build URL for a given language.
     *
     * @since 0.10.0
     *
     * @param string $lang Language code.
     * @return string
     */
    protected function get_url_for_language( $lang ) {
        // This method should be delegated to Language class
        // For now, return home URL with language prefix
        $language_manager = fpml_get_language_manager();
        $enabled_languages = $language_manager->get_enabled_languages();
        
        $lang = strtolower( $lang );
        
        if ( ! in_array( $lang, $enabled_languages, true ) && $lang !== self::SOURCE ) {
            $lang = self::SOURCE;
        }

        $routing = $this->settings->get( 'routing_mode', 'segment' );
        
        if ( 'segment' !== $routing ) {
            return home_url( '/' );
        }

        if ( fpml_is_target_language( $lang ) ) {
            $lang_info = $language_manager->get_language_info( $lang );
            $lang_slug = $lang_info ? trim( $lang_info['slug'], '/' ) : 'en';
            return home_url( '/' . $lang_slug . '/' );
        }

        return home_url( '/' );
    }

    /**
     * Check if cookie consent is available.
     *
     * @since 0.10.0
     *
     * @return bool
     */
    protected function has_cookie_consent() {
        if ( ! $this->settings->get( 'browser_redirect_requires_consent', false ) ) {
            return true;
        }

        $cookie_name = $this->settings->get( 'browser_redirect_consent_cookie', '' );

        if ( '' === $cookie_name ) {
            return false;
        }

        if ( ! isset( $_COOKIE[ $cookie_name ] ) ) { // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
            return false;
        }

        $raw_value = wp_unslash( $_COOKIE[ $cookie_name ] ); // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
        $value     = strtolower( trim( wp_check_invalid_utf8( $raw_value ) ) );

        if ( '' === $value ) {
            return false;
        }

        $negative = array( '0', 'false', 'deny', 'denied', 'reject', 'no' );

        if ( in_array( $value, $negative, true ) ) {
            return false;
        }

        return (bool) apply_filters( '\FPML_has_cookie_consent', true, $cookie_name, $raw_value );
    }
}
















