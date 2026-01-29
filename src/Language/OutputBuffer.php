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
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
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
        // Pattern principale: trova href con URL duplicati
        $html = preg_replace_callback(
            '#href=["\'](http[s]?://[^/]+)/en/http[s]?://([^"\']+)["\']#i',
            function( $matches ) {
                if ( ! strpos( $matches[2], '/' ) ) {
                    return 'href="' . $matches[1] . '/en/"';
                }
                
                if ( preg_match( '#^[^/]+(/.*)$#', $matches[2], $path_match ) ) {
                    $path = $path_match[1];
                    if ( preg_match( '#http[s]?://[^/]+(/.*)$#', $path, $final_path_match ) ) {
                        $path = $final_path_match[1];
                    }
                    return 'href="' . $matches[1] . $path . '"';
                }
                
                return $matches[0];
            },
            $html
        );
        
        // Pattern alternativo più aggressivo
        $html = preg_replace_callback(
            '#href=["\']([^"\']*)/en/http[s]?://([^"\']+)["\']#i',
            function( $matches ) {
                $bad_url = $matches[1] . '/en/' . $matches[2];
                
                $last_http_pos = strrpos( $bad_url, 'http://' );
                $last_https_pos = strrpos( $bad_url, 'https://' );
                $last_pos = false;
                
                if ( $last_http_pos !== false && $last_https_pos !== false ) {
                    $last_pos = max( $last_http_pos, $last_https_pos );
                } elseif ( $last_http_pos !== false ) {
                    $last_pos = $last_http_pos;
                } elseif ( $last_https_pos !== false ) {
                    $last_pos = $last_https_pos;
                }
                
                if ( $last_pos !== false ) {
                    $clean_url = substr( $bad_url, $last_pos );
                    if ( preg_match( '#^http[s]?://([^/]+)(/.*)$#', $clean_url, $url_match ) ) {
                        $domain = $url_match[1];
                        $path = $url_match[2];
                        if ( preg_match( '#http[s]?://[^/]+(/.*)$#', $path, $final_path_match ) ) {
                            $path = $final_path_match[1];
                        }
                        return 'href="http' . ( strpos( $clean_url, 'https://' ) === 0 ? 's' : '' ) . '://' . $domain . $path . '"';
                    }
                }
                
                return $matches[0];
            },
            $html
        );
        
        return $html;
    }
}
















