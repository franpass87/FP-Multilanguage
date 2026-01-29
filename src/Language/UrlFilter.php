<?php
/**
 * URL filter - Handles URL filtering for home_url, site_url, paginate_links, etc.
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
 * Filters various URL functions to add language prefix.
 *
 * @since 0.10.0
 */
class UrlFilter {
    /**
     * Cookie key for language preference.
     */
    const COOKIE_NAME = '\FPML_lang_pref';

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
     * Filtra home_url per aggiungere /en/ quando si è su /en/.
     *
     * @since 0.9.4
     *
     * @param string $url  URL originale.
     * @param string $path Path relativo.
     * @return string URL filtrato.
     */
    public function filter_home_url_for_en( $url, $path ) {
        return $this->add_en_prefix_to_url( $url, $path );
    }

    /**
     * Filtra site_url per aggiungere /en/ quando necessario.
     *
     * @since 0.9.4
     *
     * @param string $url  URL originale.
     * @param string $path Path relativo.
     * @return string URL filtrato.
     */
    public function filter_site_url_for_en( $url, $path ) {
        if ( is_admin() || empty( $path ) ) {
            return $url;
        }

        if ( false === strpos( $path, 'wp-admin' ) && 
             false === strpos( $path, 'wp-login' ) &&
             false === strpos( $path, 'wp-content' ) ) {
            return $this->add_en_prefix_to_url( $url, $path );
        }

        return $url;
    }

    /**
     * Filtra get_pagenum_link per aggiungere /en/ quando si è su /en/.
     *
     * @since 0.9.4
     *
     * @param string $result URL originale.
     * @param int    $pagenum Numero di pagina.
     * @return string URL filtrato.
     */
    public function filter_pagenum_link_for_en( $result, $pagenum = 1 ) {
        if ( preg_match( '#/en/http[s]?://#', $result ) || preg_match( '#http[s]?://[^/]+/en/http[s]?://#', $result ) ) {
            $http_count = substr_count( $result, 'http://' ) + substr_count( $result, 'https://' );
            if ( $http_count > 1 ) {
                $last_http_pos = strrpos( $result, 'http://' );
                $last_https_pos = strrpos( $result, 'https://' );
                $last_pos = false;
                if ( $last_http_pos !== false && $last_https_pos !== false ) {
                    $last_pos = max( $last_http_pos, $last_https_pos );
                } elseif ( $last_http_pos !== false ) {
                    $last_pos = $last_http_pos;
                } elseif ( $last_https_pos !== false ) {
                    $last_pos = $last_https_pos;
                }
                if ( $last_pos !== false ) {
                    $result = substr( $result, $last_pos );
                }
            }
        }
        
        if ( false !== strpos( $result, '/en/' ) ) {
            return $result;
        }
        
        return $this->add_en_prefix_to_url( $result );
    }

    /**
     * Filtra get_comments_pagenum_link per aggiungere /en/ quando si è su /en/.
     *
     * @since 0.9.4
     *
     * @param string $result URL originale.
     * @return string URL filtrato.
     */
    public function filter_comments_pagenum_link_for_en( $result ) {
        return $this->add_en_prefix_to_url( $result );
    }

    /**
     * Filtra bloginfo_url per aggiungere /en/ quando si è su /en/.
     *
     * @since 0.9.4
     *
     * @param string $output URL originale.
     * @param string $show   Tipo di informazione richiesta.
     * @return string URL filtrato.
     */
    public function filter_bloginfo_url_for_en( $output, $show ) {
        if ( 'url' === $show ) {
            return $this->add_en_prefix_to_url( $output );
        }
        
        return $output;
    }

    /**
     * Filtra paginate_links per correggere URL duplicati nei link di paginazione.
     *
     * @since 0.9.4
     *
     * @param string $link URL del link di paginazione.
     * @return string URL filtrato.
     */
    public function filter_paginate_links_for_en( $link ) {
        return $this->add_en_prefix_to_url( $link );
    }

    /**
     * Filtra nectar_logo_url per aggiungere /en/ quando si è su /en/.
     *
     * @since 0.9.4
     *
     * @param string $url URL originale del logo.
     * @return string URL filtrato.
     */
    public function filter_nectar_logo_url_for_en( $url ) {
        if ( preg_match( '#/en/http[s]?://#', $url ) || preg_match( '#http[s]?://[^/]+/en/http[s]?://#', $url ) ) {
            $http_count = substr_count( $url, 'http://' ) + substr_count( $url, 'https://' );
            if ( $http_count > 1 ) {
                $last_http_pos = strrpos( $url, 'http://' );
                $last_https_pos = strrpos( $url, 'https://' );
                $last_pos = false;
                if ( $last_http_pos !== false && $last_https_pos !== false ) {
                    $last_pos = max( $last_http_pos, $last_https_pos );
                } elseif ( $last_http_pos !== false ) {
                    $last_pos = $last_http_pos;
                } elseif ( $last_https_pos !== false ) {
                    $last_pos = $last_https_pos;
                }
                if ( $last_pos !== false ) {
                    $url = substr( $url, $last_pos );
                }
            }
        }
        
        if ( false !== strpos( $url, '/en/' ) ) {
            return $url;
        }
        
        return $this->add_en_prefix_to_url( $url );
    }

    /**
     * Helper centralizzato per aggiungere /en/ agli URL quando necessario.
     *
     * @since 0.9.4
     *
     * @param string $url URL da processare.
     * @param string $path Path opzionale (per home_url).
     * @return string URL processato.
     */
    protected function add_en_prefix_to_url( $url, $path = '' ) {
        if ( is_admin() ) {
            return $url;
        }

        // CRITICO: Se l'URL contiene già un URL completo dentro (es: /en/http://), correggilo PRIMA
        if ( preg_match( '#/en/http[s]?://#', $url ) || preg_match( '#http[s]?://[^/]+/en/http[s]?://#', $url ) ) {
            $http_count = substr_count( $url, 'http://' ) + substr_count( $url, 'https://' );
            
            if ( $http_count > 1 ) {
                $last_http_pos = strrpos( $url, 'http://' );
                $last_https_pos = strrpos( $url, 'https://' );
                
                $last_pos = false;
                if ( $last_http_pos !== false && $last_https_pos !== false ) {
                    $last_pos = max( $last_http_pos, $last_https_pos );
                } elseif ( $last_http_pos !== false ) {
                    $last_pos = $last_http_pos;
                } elseif ( $last_https_pos !== false ) {
                    $last_pos = $last_https_pos;
                }
                
                if ( $last_pos !== false ) {
                    $url = substr( $url, $last_pos );
                    
                    $parsed = parse_url( $url );
                    if ( ! $parsed || ! isset( $parsed['host'] ) ) {
                        if ( preg_match( '#http[s]?://([^/]+)/en/(.*)$#', $url, $match ) ) {
                            $url = ( strpos( $url, 'https://' ) !== false ? 'https://' : 'http://' ) . $match[1] . '/en/' . $match[2];
                        } elseif ( preg_match( '#http[s]?://([^/]+)(/.*)$#', $url, $match ) ) {
                            $url = ( strpos( $url, 'https://' ) !== false ? 'https://' : 'http://' ) . $match[1] . $match[2];
                        }
                    }
                    
                    if ( false !== strpos( $url, '/en/' ) ) {
                        return $url;
                    }
                }
            }
        }
        
        if ( false !== strpos( $url, '/en/' ) || false !== strpos( $url, '/en-' ) ) {
            return $url;
        }

        if ( ! empty( $path ) && false !== strpos( $path, '://' ) ) {
            return $url;
        }

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        $is_english_path = fpml_url_contains_target_language( $request_uri );
        $lang_cookie = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? sanitize_text_field( $_COOKIE[ self::COOKIE_NAME ] ) : '';
        $is_target_lang_preference = ( fpml_is_target_language( $lang_cookie ) || $is_english_path );

        if ( ! $is_target_lang_preference ) {
            return $url;
        }

        $routing = $this->settings->get( 'routing_mode', 'segment' );
        if ( 'segment' !== $routing ) {
            return $url;
        }

        // Evita loop: usa get_option direttamente invece di home_url()
        remove_filter( 'home_url', array( $this, 'filter_home_url_for_en' ), 10 );
        remove_filter( 'site_url', array( $this, 'filter_site_url_for_en' ), 10 );
        
        try {
            $home_url_raw = get_option( 'home' );
            if ( is_ssl() ) {
                $home_url_raw = set_url_scheme( $home_url_raw, 'https' );
            }
            $home_url_base = trailingslashit( $home_url_raw );
        } finally {
            add_filter( 'home_url', array( $this, 'filter_home_url_for_en' ), 10, 2 );
            add_filter( 'site_url', array( $this, 'filter_site_url_for_en' ), 10, 2 );
        }

        $parsed_url = parse_url( $url );
        
        if ( ! $parsed_url || ! isset( $parsed_url['host'] ) ) {
            $url_path = isset( $parsed_url['path'] ) ? $parsed_url['path'] : $url;
            $url_path = ltrim( $url_path, '/' );
            
            if ( 'en/' !== substr( $url_path, 0, 3 ) && 'en' !== $url_path ) {
                $url = $home_url_base . 'en/' . $url_path;
            } else {
                $url = $home_url_base . $url_path;
            }
            
            if ( isset( $parsed_url['query'] ) ) {
                $url .= '?' . $parsed_url['query'];
            }
            if ( isset( $parsed_url['fragment'] ) ) {
                $url .= '#' . $parsed_url['fragment'];
            }
            
            return $url;
        }
        
        $url_scheme = isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] . '://' : 'http://';
        $url_host = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
        $url_path = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '/';
        $url_query = isset( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '';
        $url_fragment = isset( $parsed_url['fragment'] ) ? '#' . $parsed_url['fragment'] : '';
        
        $parsed_home = parse_url( $home_url_base );
        $home_host = isset( $parsed_home['host'] ) ? $parsed_home['host'] : '';
        
        if ( $url_host !== $home_host ) {
            return $url;
        }
        
        $rel_path = ltrim( $url_path, '/' );
        
        if ( 'en/' !== substr( $rel_path, 0, 3 ) && 'en' !== $rel_path ) {
            if ( ! empty( $rel_path ) ) {
                $url = $url_scheme . $url_host . '/' . 'en/' . $rel_path . $url_query . $url_fragment;
            } else {
                $url = $url_scheme . $url_host . '/en/' . $url_query . $url_fragment;
            }
        }

        return $url;
    }
}
















