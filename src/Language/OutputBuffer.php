<?php
/**
 * Output buffer - Handles output buffering to fix duplicate URLs.
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
 * Manages output buffering to fix duplicate URLs in HTML output.
 *
 * @since 0.10.0
 */
class OutputBuffer {
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
    }

    /**
     * Avvia output buffer per correggere URL duplicati.
     *
     * @since 0.9.4
     */
    public function start_output_buffer() {
        $request_uri     = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        $is_english_path = fpml_url_contains_target_language( $request_uri );
        
        if ( ! $is_english_path ) {
            return;
        }
        
        $routing = $this->settings->get( 'routing_mode', 'segment' );
        if ( 'segment' !== $routing ) {
            return;
        }
        
        if ( ob_get_level() > 10 ) {
            return;
        }
        
        ob_start( array( $this, 'fix_duplicate_urls_in_output' ) );
    }

    /**
     * Termina output buffer.
     *
     * @since 0.9.4
     */
    public function end_output_buffer() {
        if ( ob_get_level() > 0 ) {
            ob_end_flush();
        }
    }

    /**
     * Corregge URL duplicati nell'output HTML.
     *
     * @since 0.9.4
     *
     * @param string $html HTML da processare.
     * @return string HTML corretto.
     */
    public function fix_duplicate_urls_in_output( $html ) {
        // Build a pattern that matches any enabled language slug
        $lang_slugs = array();
        $lm = function_exists( 'fpml_get_language_manager' ) ? fpml_get_language_manager() : null;
        if ( $lm ) {
            foreach ( $lm->get_enabled_languages() as $lang ) {
                $info = $lm->get_language_info( $lang );
                $slug = $info ? trim( $info['slug'], '/' ) : $lang;
                if ( $slug ) {
                    $lang_slugs[] = preg_quote( $slug, '#' );
                }
            }
        }
        if ( empty( $lang_slugs ) ) {
            // No language manager available yet; skip URL-fix to avoid false positives.
            return $html;
        }
        $lang_pattern = '(?:' . implode( '|', $lang_slugs ) . ')';

        // Fix href="/lang/https?://..." duplicates in any attribute value
        $attrs = 'href|src|action|data-url|data-href';
        $html  = preg_replace_callback(
            '#(' . $attrs . ')=(["\'])([^"\']*/' . $lang_pattern . '/)http[s]?://[^/]+(/[^"\']*)\2#i',
            static function ( $m ) {
                // Keep the base up to and including /lang/, then append only the path
                return $m[1] . '=' . $m[2] . $m[3] . $m[4] . $m[2];
            },
            $html
        );

        return $html;
    }
}
















