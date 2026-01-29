<?php
/**
 * Language resolver and helpers.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */


namespace FP\Multilanguage;

use FP\Multilanguage\Core\ContainerAwareTrait;
use WP_Post;
use WP_Query;
use WP_Term;
use WP_Taxonomy;
use FP\Multilanguage\Language\LanguageResolver;
use FP\Multilanguage\Language\PermalinkFilter;
use FP\Multilanguage\Language\UrlFilter;
use FP\Multilanguage\Language\RedirectManager;
use FP\Multilanguage\Language\OutputBuffer;
use FP\Multilanguage\Language\LanguageSwitcherRenderer;
use FP\Multilanguage\Language\Helpers\TermPairManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manage current language state, redirects and switcher helpers.
 *
 * @since 0.2.0
 */
class Language {
	use ContainerAwareTrait;
    /**
     * Cookie key for language preference.
     */
    const COOKIE_NAME = '\FPML_lang_pref';

    /**
     * Cookie lifetime (30 days).
     */
    const COOKIE_TTL = 2592000;

    /**
     * Target language slug.
     * @deprecated Use get_target_languages() for multi-language support.
     */
    const TARGET = 'en';

    /**
     * Source language slug.
     */
    const SOURCE = 'it';

    /**
     * Singleton instance.
     *
     * @var \FPML_Language|null
     */
    protected static $instance = null;

    /**
     * Current language code (it|en).
     *
     * @var string
     */
    protected $current = self::SOURCE;

    /**
     * Cached settings instance.
     *
     * @var \FPML_Settings
     */
    protected $settings;


    /**
     * Cached Forwarded header parameters for the current request.
     *
     * @since 0.3.3
     *
     * @var array<string,string>|null
     */
    protected $forwarded_parameters = null;

    /**
     * Normalized Forwarded header payload used to populate the cache.
     *
     * @since 0.3.3
     *
     * @var string|null
     */
    protected $forwarded_parameters_raw = null;

    /**
     * Language resolver instance.
     *
     * @since 0.10.0
     *
     * @var LanguageResolver
     */
    protected $resolver;

    /**
     * Permalink filter instance.
     *
     * @since 0.10.0
     *
     * @var PermalinkFilter
     */
    protected $permalink_filter;

    /**
     * URL filter instance.
     *
     * @since 0.10.0
     *
     * @var UrlFilter
     */
    protected $url_filter;

    /**
     * Redirect manager instance.
     *
     * @since 0.10.0
     *
     * @var RedirectManager
     */
    protected $redirect_manager;

    /**
     * Output buffer instance.
     *
     * @since 0.10.0
     *
     * @var OutputBuffer
     */
    protected $output_buffer;

    /**
     * Language switcher renderer instance.
     *
     * @since 0.10.0
     *
     * @var LanguageSwitcherRenderer
     */
    protected $switcher_renderer;

    /**
     * Term pair manager instance.
     *
     * @since 0.10.0
     *
     * @var TermPairManager
     */
    protected $term_pair_manager;

    /**
     * Retrieve singleton.
     *
     * @since 0.2.0
     *
     * @return \FPML_Language
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
        $container = $this->getContainer();
        $this->settings = $container && $container->has( 'options' ) ? $container->get( 'options' ) : \FPML_Settings::instance();

        // Initialize modules
        $this->resolver = new LanguageResolver( $this->settings );
        $this->permalink_filter = new PermalinkFilter( $this->settings, $this->resolver );
        $this->url_filter = new UrlFilter( $this->settings );
        $this->redirect_manager = new RedirectManager( $this->settings, $this->resolver, $this->permalink_filter );
        $this->output_buffer = new OutputBuffer( $this->settings );
        $this->switcher_renderer = new LanguageSwitcherRenderer( $this->resolver );
        $this->term_pair_manager = new TermPairManager();

        // Register hooks - delegate to modules
        add_action( 'parse_query', array( $this->resolver, 'determine_language' ) );
        add_action( 'template_redirect', array( $this->redirect_manager, 'maybe_redirect_browser_language' ), 0 );
        add_action( 'template_redirect', array( $this->resolver, 'persist_language_cookie' ), 1 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_shortcode( 'fp_lang_switcher', array( $this->switcher_renderer, 'render_switcher' ) );
        add_filter( 'locale', array( $this, 'filter_locale' ) );
        add_filter( 'language_attributes', array( $this, 'filter_language_attributes' ), 10, 2 );
        add_filter( 'post_link', array( $this->permalink_filter, 'filter_translation_permalink' ), 10, 2 );
        add_filter( 'page_link', array( $this->permalink_filter, 'filter_translation_permalink' ), 10, 2 );
        add_filter( 'post_type_link', array( $this->permalink_filter, 'filter_translation_permalink' ), 10, 2 );
        add_filter( 'get_sample_permalink', array( $this->permalink_filter, 'filter_sample_permalink' ), 10, 5 );
        add_filter( 'get_sample_permalink_html', array( $this->permalink_filter, 'filter_sample_permalink_html' ), 10, 5 );
        add_filter( 'term_link', array( $this->permalink_filter, 'filter_term_permalink' ), 10, 2 );
        add_filter( 'redirect_canonical', array( $this, 'disable_canonical_redirect_for_translations' ), 10, 2 );
        add_filter( 'home_url', array( $this->url_filter, 'filter_home_url_for_en' ), 10, 2 );
        add_filter( 'site_url', array( $this->url_filter, 'filter_site_url_for_en' ), 10, 2 );
        add_filter( 'get_pagenum_link', array( $this->url_filter, 'filter_pagenum_link_for_en' ), 10, 1 );
        add_filter( 'get_comments_pagenum_link', array( $this->url_filter, 'filter_comments_pagenum_link_for_en' ), 10, 1 );
        add_filter( 'bloginfo_url', array( $this->url_filter, 'filter_bloginfo_url_for_en' ), 10, 2 );
        add_filter( 'paginate_links', array( $this->url_filter, 'filter_paginate_links_for_en' ), 10, 1 );
        add_filter( 'nectar_logo_url', array( $this->url_filter, 'filter_nectar_logo_url_for_en' ), 10, 1 );
        if ( ! is_admin() && ! wp_doing_ajax() && ! defined( 'REST_REQUEST' ) ) {
            add_action( 'template_redirect', array( $this->output_buffer, 'start_output_buffer' ), 999 );
            add_action( 'shutdown', array( $this->output_buffer, 'end_output_buffer' ), 0 );
        }
        // add_action( 'template_redirect', array( $this->redirect_manager, 'redirect_translated_posts_to_en' ), 1 );
    }

    /**
     * Enqueue frontend assets for language switcher.
     *
     * @since 0.4.2
     *
     * @return void
     */
    public function enqueue_frontend_assets() {
        if ( is_admin() ) {
            return;
        }

        $css_file = \FPML_PLUGIN_DIR . '/assets/frontend.css';

        if ( file_exists( $css_file ) ) {
            wp_enqueue_style(
                'fpml-frontend',
                \FPML_PLUGIN_URL . '/assets/frontend.css',
                array(),
                filemtime( $css_file )
            );
        }
    }

    /**
     * Filter permalinks for translated pages to use /en/ prefix.
     *
     * @since 0.4.1
     *
     * @param string  $permalink The post's permalink.
     * @param WP_Post $post      The post object.
     *
     * @return string
     */
    public function filter_translation_permalink( $permalink, $post, $force = false ) {
        return $this->permalink_filter->filter_translation_permalink( $permalink, $post, $force );
    }


    /**
     * Filter permalinks for translated terms to use /en/ prefix.
     *
     * @since 0.9.3
     *
     * @param string  $permalink The term's permalink.
     * @param \WP_Term $term      The term object.
     *
     * @return string
     */
    public function filter_term_permalink( $permalink, $term ) {
        return $this->permalink_filter->filter_term_permalink( $permalink, $term );
    }

    /**
     * Filter permalinks for translated terms.
     * 
     * @deprecated Use PermalinkFilter::filter_term_permalink() instead.
     * Kept for backward compatibility.
     *
     * @since 0.9.3
     *
     * @param string  $permalink The term's permalink.
     * @param \WP_Term $term      The term object.
     *
     * @return string
     */
    protected function _filter_term_permalink_legacy( $permalink, $term ) {
        if ( is_admin() || ! $term instanceof \WP_Term ) {
            return $permalink;
        }

        // Solo se il routing mode è 'segment'
        $routing = $this->settings->get( 'routing_mode', 'segment' );
        if ( 'segment' !== $routing ) {
            return $permalink;
        }

        // Determina il contesto corrente (se siamo su un path di lingua target o meno)
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        $is_english_path = fpml_url_contains_target_language( $request_uri );
        
        // No regex error check needed for fpml_url_contains_target_language()
        
        // Verifica se il termine è una traduzione
        $is_translation = get_term_meta( $term->term_id, '_fpml_is_translation', true );

        // Get current language info for dynamic path handling
        $language_manager = fpml_get_language_manager();
        $current_lang = $this->get_current_language();
        $lang_info = $language_manager->get_language_info( $current_lang );
        $lang_slug = $lang_info ? trim( $lang_info['slug'], '/' ) : 'en';
        $lang_path = '/' . $lang_slug . '/';
        
        // Se siamo su un path di lingua target e il termine NON è una traduzione, non aggiungere il path
        // (questo evita di aggiungere il path a categorie originali nella homepage italiana)
        if ( ! $is_english_path && ! $is_translation ) {
            // Se siamo sulla homepage italiana e il termine non è una traduzione, rimuovi il path se presente
            if ( fpml_url_contains_target_language( $permalink ) ) {
                $permalink = preg_replace( '#/' . preg_quote( $lang_slug, '#' ) . '/#', '/', $permalink );
            }
            return $permalink;
        }

        // Se siamo sulla homepage italiana e il termine È una traduzione, non aggiungere il path
        // (questo evita di aggiungere il path a categorie tradotte nella homepage italiana)
        if ( ! $is_english_path && $is_translation ) {
            // Rimuovi il path se presente
            if ( fpml_url_contains_target_language( $permalink ) ) {
                $permalink = preg_replace( '#/' . preg_quote( $lang_slug, '#' ) . '/#', '/', $permalink );
            }
            // Rimuovi anche il prefisso en- dallo slug se presente nel permalink (backward compatibility)
            if ( false !== strpos( $permalink, '/en-' ) ) {
                $permalink = str_replace( '/en-', '/', $permalink );
            }
            return $permalink;
        }

        // Se siamo su una lingua target e il termine NON è una traduzione, dobbiamo trovare la traduzione
        if ( $is_english_path && ! $is_translation ) {
            // Cerca la traduzione del termine usando helper function
            $current_lang = $this->resolver->get_current_language();
            $translation_id = false;
            if ( function_exists( 'fpml_get_term_translation_id' ) ) {
                $translation_id = fpml_get_term_translation_id( $term->term_id, $current_lang );
            } else {
                // Fallback: use new meta key format
                $meta_key = '_fpml_pair_id_' . $current_lang;
                $translation_id = (int) get_term_meta( $term->term_id, $meta_key, true );
                // Backward compatibility: check legacy _fpml_pair_id if lang is 'en'
                if ( ! $translation_id && 'en' === $current_lang ) {
                    $translation_id = (int) get_term_meta( $term->term_id, '_fpml_pair_id', true );
                }
            }
            if ( $translation_id > 0 ) {
                $translation = get_term( $translation_id, $term->taxonomy );
                if ( $translation instanceof \WP_Term && get_term_meta( $translation_id, '_fpml_is_translation', true ) ) {
                    // Usa il termine tradotto invece dell'originale
                    $term = $translation;
                    $is_translation = true;
                } else {
                    // Non c'è traduzione, usa il termine originale ma senza /en/
                    return $permalink;
                }
            } else {
                // Non c'è traduzione, usa il termine originale ma senza /en/
                return $permalink;
            }
        }

        // Solo se è una traduzione E siamo su /en/
        if ( ! $is_translation || ! $is_english_path ) {
            return $permalink;
        }

        $base_slug = $term->slug;

        // Se lo slug inizia con 'en-', rimuovi il prefisso
        if ( 0 === strpos( $base_slug, 'en-' ) ) {
            $base_slug = substr( $base_slug, 3 );
        }

        // Rimuovi temporaneamente i filtri per evitare loop
        remove_filter( 'home_url', array( $this, 'filter_home_url_for_en' ), 10 );
        remove_filter( 'site_url', array( $this, 'filter_site_url_for_en' ), 10 );
        
        try {
            $home_url = trailingslashit( home_url() );
        } finally {
            // Riapplica sempre i filtri, anche in caso di errore
            add_filter( 'home_url', array( $this, 'filter_home_url_for_en' ), 10, 2 );
            add_filter( 'site_url', array( $this, 'filter_site_url_for_en' ), 10, 2 );
        }
        
        // Parse dell'URL per estrarre solo il path
        $parsed = wp_parse_url( $permalink );
        if ( $parsed && isset( $parsed['path'] ) ) {
            $rel_path = $parsed['path'];
        } else {
            // Fallback: rimuovi home_url
            $rel_path = str_replace( $home_url, '', $permalink );
        }
        
        // Rimuovi TUTTI i prefissi /en/ multipli (anche se già presenti)
        // Questo risolve il problema del doppio /en/en/
        $rel_path = preg_replace( '#^(/en/)+#', '/', $rel_path );
        
        // ✅ QA RACCOMANDAZIONE: Verifica errori regex
        if ( preg_last_error() !== PREG_NO_ERROR ) {
                \FP\Multilanguage\Logger::warning(
                        'Regex error in filter_site_url_for_en (remove /en/)',
                        array( 'error' => preg_last_error() )
                );
        }
        
        $rel_path = preg_replace( '#^en/#', '', $rel_path );
        
        // ✅ QA RACCOMANDAZIONE: Verifica errori regex
        if ( preg_last_error() !== PREG_NO_ERROR ) {
                \FP\Multilanguage\Logger::warning(
                        'Regex error in filter_site_url_for_en (remove en/)',
                        array( 'error' => preg_last_error() )
                );
        }
        
        $rel_path = preg_replace( '#/en/#', '/', $rel_path ); // Rimuovi anche /en/ nel mezzo
        
        // ✅ QA RACCOMANDAZIONE: Verifica errori regex
        if ( preg_last_error() !== PREG_NO_ERROR ) {
                \FP\Multilanguage\Logger::warning(
                        'Regex error in filter_site_url_for_en (remove /en/)',
                        array( 'error' => preg_last_error() )
                );
        }
        
        $rel_path = ltrim( $rel_path, '/' );
        
        // Rimuovi anche eventuali domini o percorsi strani (es: fp-development.local/category/...)
        // Cerca pattern come "dominio.local/path" e estrai solo "path"
        if ( preg_match( '#^[^/]+\.(local|com|net|org|it|eu)/(.+)$#', $rel_path, $matches ) ) {
            // ✅ QA RACCOMANDAZIONE: Verifica errori regex
            if ( preg_last_error() !== PREG_NO_ERROR ) {
                    \FP\Multilanguage\Logger::warning(
                            'Regex error in filter_site_url_for_en (domain match)',
                            array( 'error' => preg_last_error() )
                    );
            } else {
                $rel_path = $matches[2];
            }
        }
        
        // Rimuovi eventuali doppi slash
        $rel_path = preg_replace( '#//+#', '/', $rel_path );
        
        // ✅ QA RACCOMANDAZIONE: Verifica errori regex
        if ( preg_last_error() !== PREG_NO_ERROR ) {
                \FP\Multilanguage\Logger::warning(
                        'Regex error in filter_site_url_for_en (remove double slashes)',
                        array( 'error' => preg_last_error() )
                );
        }
        $rel_path = ltrim( $rel_path, '/' );
        
        // Sostituiamo 'en-slug' con 'slug' nel path
        // Facciamo attenzione a sostituire solo l'ultimo segmento o lo slug esatto
        // Per sicurezza, sostituiamo lo slug del termine corrente
        if ( false !== strpos( $rel_path, $term->slug ) ) {
            $rel_path = str_replace( $term->slug, $base_slug, $rel_path );
        }
        
        // Aggiungiamo SEMPRE /en/ all'inizio (dopo aver rimosso tutti i prefissi esistenti)
        $permalink = $home_url . 'en/' . $rel_path;

        return $permalink;
    }

    /**
     * Filter the sample permalink shown in admin edit screen.
     *
     * @since 0.9.5
     *
     * @param array  $permalink Array with 'permalink' and 'slug' keys.
     * @param int    $post_id   Post ID.
     * @param string $title     Post title.
     * @param string $name      Post name (slug).
     * @param object $post      Post object.
     *
     * @return array
     */
    public function filter_sample_permalink( $permalink, $post_id, $title, $name, $post ) {
        return $this->permalink_filter->filter_sample_permalink( $permalink, $post_id, $title, $name, $post );
    }

    /**
     * Filter sample permalink.
     * 
     * @deprecated Use PermalinkFilter::filter_sample_permalink() instead.
     * Kept for backward compatibility.
     *
     * @since 0.9.5
     */
    protected function _filter_sample_permalink_legacy( $permalink, $post_id, $title, $name, $post ) {
        if ( ! is_array( $permalink ) || ! isset( $permalink['permalink'] ) ) {
            return $permalink;
        }

        if ( ! $post instanceof \WP_Post ) {
            $post = get_post( $post_id );
        }

        if ( ! $post instanceof \WP_Post ) {
            return $permalink;
        }

        // Solo per post tradotti
        if ( ! get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
            return $permalink;
        }

        // Solo se il routing mode è 'segment'
        $routing = $this->settings->get( 'routing_mode', 'segment' );
        if ( 'segment' !== $routing ) {
            return $permalink;
        }

        // Applica il filtro al permalink con force=true per applicarlo anche in admin
        $filtered_permalink = $this->filter_translation_permalink( $permalink['permalink'], $post, true );

        if ( $filtered_permalink !== $permalink['permalink'] ) {
            $permalink['permalink'] = $filtered_permalink;
        }

        return $permalink;
    }

    /**
     * Filter the sample permalink HTML shown in admin edit screen.
     *
     * @since 0.9.5
     *
     * @param string  $html      The sample permalink HTML.
     * @param int     $post_id   Post ID.
     * @param string  $new_title New sample permalink title.
     * @param string  $new_slug  New sample permalink slug.
     * @param WP_Post $post      Post object.
     *
     * @return string
     */
    public function filter_sample_permalink_html( $html, $post_id, $new_title, $new_slug, $post ) {
        return $this->permalink_filter->filter_sample_permalink_html( $html, $post_id, $new_title, $new_slug, $post );
    }

    /**
     * Filter sample permalink HTML.
     * 
     * @deprecated Use PermalinkFilter::filter_sample_permalink_html() instead.
     * Kept for backward compatibility.
     *
     * @since 0.9.5
     */
    protected function _filter_sample_permalink_html_legacy( $html, $post_id, $new_title, $new_slug, $post ) {
        if ( ! $post instanceof \WP_Post ) {
            $post = get_post( $post_id );
        }

        if ( ! $post instanceof \WP_Post ) {
            return $html;
        }

        // Solo per post tradotti
        if ( ! get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
            return $html;
        }

        // Solo se il routing mode è 'segment'
        $routing = $this->settings->get( 'routing_mode', 'segment' );
        if ( 'segment' !== $routing ) {
            return $html;
        }

        // Ottieni il permalink corretto con /en/
        $correct_permalink = $this->filter_translation_permalink( get_permalink( $post ), $post, true );
        $decoded_permalink = urldecode( $correct_permalink );
        
        // Ottieni il permalink originale (senza /en/)
        $old_permalink = get_permalink( $post );
        $old_decoded = urldecode( $old_permalink );
        
        // Sostituisci l'URL nell'attributo href se presente
        if ( false !== strpos( $html, 'href=' ) ) {
            // Estrai l'URL corrente dal HTML
            if ( preg_match( '/href=["\']([^"\']+)["\']/', $html, $matches ) ) {
                $old_url = $matches[1];
                if ( $old_url !== $correct_permalink && ! fpml_url_contains_target_language( $old_url ) ) {
                    $html = str_replace( 'href="' . $old_url . '"', 'href="' . $correct_permalink . '"', $html );
                    $html = str_replace( "href='" . $old_url . "'", "href='" . $correct_permalink . "'", $html );
                }
            }
        }
        
        // WordPress usa un pattern specifico: http://domain.com/<span id="editable-post-name">slug</span>/
        // Dobbiamo sostituire la parte prima dello span con l'URL corretto che include /en/
        if ( preg_match( '/(<a[^>]*href=["\'][^"\']*["\'][^>]*>)([^<]*)(<span[^>]*id=["\']editable-post-name["\'][^>]*>)/', $html, $matches ) ) {
            $link_open = $matches[1];
            $url_prefix = trim( $matches[2] );
            $span_open = $matches[3];
            
            // Se l'URL prefix non contiene un path di lingua target, sostituiscilo
            $current_lang = $this->get_current_language();
            $language_manager = fpml_get_language_manager();
            $lang_info = $language_manager->get_language_info( $current_lang );
            $lang_slug = $lang_info ? trim( $lang_info['slug'], '/' ) : 'en';
            
            if ( ! fpml_url_contains_target_language( $url_prefix ) && fpml_url_contains_target_language( $correct_permalink ) ) {
                // Estrai la parte base dell'URL (prima dello slug)
                $base_url = trailingslashit( home_url() );
                $correct_base = $base_url . $lang_slug . '/';
                
                // Sostituisci la parte prima dello span
                $html = str_replace( $link_open . $url_prefix . $span_open, $link_open . $correct_base . $span_open, $html );
            }
        } else {
            // Fallback: cerca pattern standard <a href="...">http://.../multilingual-test-article/</a>
            if ( preg_match( '/(<a[^>]*href=["\'][^"\']*["\'][^>]*>)([^<]+)(<\/a>)/', $html, $matches ) ) {
                $link_open = $matches[1];
                $link_text = trim( $matches[2] );
                $link_close = $matches[3];
                
                // Se il testo contiene l'URL senza path di lingua target, sostituiscilo
                if ( false !== strpos( $link_text, $old_decoded ) || ( ! fpml_url_contains_target_language( $link_text ) && fpml_url_contains_target_language( $correct_permalink ) ) ) {
                    // Sostituisci il testo del link con l'URL corretto decodificato
                    $html = str_replace( $link_open . $link_text . $link_close, $link_open . $decoded_permalink . $link_close, $html );
                }
            }
        }
        
        // Sostituisci anche eventuali occorrenze dell'URL senza path di lingua target nel resto dell'HTML
        // Questo gestisce casi in cui l'URL appare fuori dal tag <a>
        if ( false !== strpos( $html, $old_decoded ) && ! fpml_url_contains_target_language( $old_decoded ) ) {
            $html = str_replace( $old_decoded, $decoded_permalink, $html );
        }

        return $html;
    }

    /**
     * Disable WordPress canonical redirect for translated posts when accessed via /en/ URL.
     *
     * @since 0.9.3
     *
     * @param string $redirect_url  The redirect URL.
     * @param string $requested_url The requested URL.
     *
     * @return string|false
     */
    public function disable_canonical_redirect_for_translations( $redirect_url, $requested_url ) {
        if ( is_admin() || wp_doing_ajax() || defined( 'REST_REQUEST' ) ) {
            return $redirect_url;
        }

        // Solo se il routing mode è 'segment'
        $routing = $this->settings->get( 'routing_mode', 'segment' );
        if ( 'segment' !== $routing ) {
            return $redirect_url;
        }

        // Verifica REQUEST_URI per essere sicuri (contiene l'URL originale richiesto)
        $current_path = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        
        // Se il path corrente contiene un path di lingua target, disabilita SEMPRE il redirect canonico
        // Questo previene loop quando si accede direttamente a URL con path di lingua
        if ( fpml_url_contains_target_language( $current_path ) || false !== strpos( $current_path, '/en-' ) ) {
            // Disabilita completamente il redirect canonico per tutti gli URL con path di lingua target
            return false;
        }
        
        // Se l'URL richiesto contiene un path di lingua target, disabilita anche il redirect
        if ( fpml_url_contains_target_language( $requested_url ) || false !== strpos( $requested_url, '/en-' ) ) {
            return false;
        }

        return $redirect_url;
    }

    /**
     * Redirect translated posts to /en/ URL if accessed without prefix.
     *
     * @since 0.9.2
     *
     * @return void
     */
    public function redirect_translated_posts_to_en() {
        $this->redirect_manager->redirect_translated_posts_to_en();
    }

    /**
     * Redirect translated posts to EN.
     * 
     * @deprecated Use RedirectManager::redirect_translated_posts_to_en() instead.
     * Kept for backward compatibility.
     *
     * @since 0.9.3
     */
    protected function _redirect_translated_posts_to_en_legacy() {
        if ( is_admin() || wp_doing_ajax() || defined( 'REST_REQUEST' ) ) {
            return;
        }

        // Solo se il routing mode è 'segment'
        $routing = $this->settings->get( 'routing_mode', 'segment' );
        if ( 'segment' !== $routing ) {
            return;
        }

        // Se l'URL contiene già /en/, non fare redirect (evita loop)
        $current_path = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        $current_url = home_url( $current_path );
        
        // Controlla se l'URL contiene già un path di lingua target in vari formati (controllo più robusto)
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
        
        // Also check for legacy /en- pattern
        if ( $has_target_lang_path || false !== strpos( $current_path, '/en-' ) ) {
            return;
        }

        // Solo per le pagine singole
        if ( ! is_singular() ) {
            return;
        }

        global $post;
        if ( ! $post instanceof \WP_Post ) {
            return;
        }

        // Solo per le pagine tradotte
        if ( ! get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
            return;
        }

        // Verifica se l'URL corrente corrisponde già al permalink atteso
        // Questo evita redirect quando si accede direttamente a /en/ URL
        $current_url_normalized = rtrim( parse_url( $current_url, PHP_URL_PATH ), '/' );
        
        // Genera il permalink atteso
        $expected_url = $this->filter_translation_permalink( get_permalink( $post->ID ), $post, true );
        
        if ( empty( $expected_url ) ) {
            return;
        }
        
        $expected_url_normalized = rtrim( parse_url( $expected_url, PHP_URL_PATH ), '/' );
        
        // Se l'URL corrente corrisponde già al permalink atteso, non fare redirect
        if ( $current_url_normalized === $expected_url_normalized ) {
            return;
        }
        
        // Se il permalink filtrato è uguale a quello normale, non fare redirect
        if ( $expected_url === get_permalink( $post->ID ) ) {
            return;
        }

        // Verifica che l'URL di destinazione contenga un path di lingua target
        if ( ! fpml_url_contains_target_language( $expected_url_normalized ) ) {
            return;
        }
        
        // Fai redirect solo se necessario
        wp_safe_redirect( $expected_url, 301 );
        exit;
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
        $this->resolver->determine_language( $query );
        // Update current language from resolver
        $this->current = $this->resolver->get_current_language();
    }

    /**
     * Persist language choice in cookie for subsequent visits.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function persist_language_cookie() {
        $this->resolver->persist_language_cookie();
    }

    /**
     * Redirect first-time visitors based on browser language preference.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function maybe_redirect_browser_language() {
        $this->redirect_manager->maybe_redirect_browser_language();
    }

    /**
     * Force English locale on frontend when needed.
     *
     * @since 0.3.0
     *
     * @param string $locale Current locale.
     *
     * @return string
     */
    public function filter_locale( $locale ) {
        if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            return $locale;
        }

        if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return $locale;
        }

        // Verifica sempre direttamente il path per essere sicuri, anche se $this->current non è ancora aggiornato
        // Questo è importante perché il filtro locale viene chiamato molto presto nel processo di WordPress
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        $is_english_path = fpml_url_contains_target_language( $request_uri );
        
        // Verifica anche il cookie
        $lang_cookie = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? sanitize_text_field( $_COOKIE[ self::COOKIE_NAME ] ) : '';
        $is_target_lang_cookie = fpml_is_target_language( $lang_cookie );
        
        // Usa $this->current se disponibile, altrimenti verifica direttamente
        $language = $this->current;
        
        // Check if current language is a target language
        $is_target_lang = fpml_is_target_language( $language );
        
        // Se non siamo sicuri della lingua corrente, verifica direttamente
        if ( ! $is_target_lang || ( ! $is_english_path && ! $is_target_lang_cookie ) ) {
            $requested = '';

            if ( isset( $_GET['\FPML_lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $requested_raw = wp_unslash( $_GET['\FPML_lang'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $requested     = strtolower( sanitize_text_field( $requested_raw ) );
            } elseif ( isset( $_GET['fpml_lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $requested_raw = wp_unslash( $_GET['fpml_lang'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $requested     = strtolower( sanitize_text_field( $requested_raw ) );
            } elseif ( isset( $_GET['lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $requested_raw = wp_unslash( $_GET['lang'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $requested     = strtolower( sanitize_text_field( $requested_raw ) );
            }

            // Se non c'è un parametro GET, verifica il path
            $language_manager = fpml_get_language_manager();
            $enabled_languages = $language_manager->get_enabled_languages();
            $is_requested_target = ! empty( $requested ) && in_array( $requested, $enabled_languages, true );
            
            if ( ! $is_requested_target ) {
                if ( $is_english_path ) {
                    // Get first enabled language as fallback
                    $requested = ! empty( $enabled_languages ) ? $enabled_languages[0] : 'en';
                } elseif ( $is_target_lang_cookie ) {
                    // Get first enabled language as fallback
                    $requested = ! empty( $enabled_languages ) ? $enabled_languages[0] : 'en';
                } else {
                    $path    = $this->get_current_path();
                    $lowered = strtolower( $path );
                    $is_target = fpml_url_contains_target_language( $lowered );

                    if ( $is_target ) {
                        // Get language from path
                        $detected_lang = fpml_get_current_language();
                        $requested = $detected_lang && fpml_is_target_language( $detected_lang ) ? $detected_lang : ( ! empty( $enabled_languages ) ? $enabled_languages[0] : 'en' );
                    }
                }
            }

            // Check if requested is a target language
            if ( ! empty( $requested ) && fpml_is_target_language( $requested ) ) {
                $language = $requested;
            }
        }

        // Se siamo su un path di lingua target o abbiamo un cookie di lingua target, determina la lingua
        if ( $is_english_path || $is_target_lang_cookie ) {
            // Detect language from path
            $language_manager = fpml_get_language_manager();
            $available_languages = $language_manager->get_all_languages();
            $detected_lang = null;
            
            foreach ( $available_languages as $lang_code => $lang_info ) {
                if ( ! is_array( $lang_info ) || empty( $lang_info['slug'] ) ) {
                    continue;
                }
                $lang_slug = trim( $lang_info['slug'], '/' );
                if ( ! empty( $lang_slug ) && preg_match( '#^/' . preg_quote( $lang_slug, '#' ) . '(/|$)#', $request_uri ) ) {
                    $detected_lang = $lang_code;
                    break;
                }
            }
            
            if ( $detected_lang ) {
                $language = $detected_lang;
            } elseif ( ! empty( $enabled_languages ) ) {
                $language = $enabled_languages[0];
            }
        }

        // Check if current language is a target language
        if ( ! fpml_is_target_language( $language ) ) {
            return $locale;
        }

        // Return the correct locale for the detected language
        $language_manager = fpml_get_language_manager();
        $lang_info = $language_manager->get_language_info( $language );
        
        if ( $lang_info && ! empty( $lang_info['locale'] ) ) {
            return $lang_info['locale'];
        }
        
        // Fallback to en_US for backward compatibility
        return 'en_US';
    }

    /**
     * Filter language_attributes to set correct lang attribute on HTML tag.
     *
     * @since 0.10.1
     *
     * @param string $output   A space-separated list of language attributes.
     * @param string $doctype  The type of html document (xhtml or html).
     *
     * @return string Modified language attributes.
     */
    public function filter_language_attributes( $output, $doctype = 'html' ) {
        if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            return $output;
        }

        if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return $output;
        }

        // Detect current language from URL - use direct detection for reliability
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        
        // Direct language slug detection (most reliable)
        $locale_map = array(
            'en' => 'en-US',
            'de' => 'de-DE',
            'fr' => 'fr-FR',
            'es' => 'es-ES',
        );
        
        $detected_lang = null;
        foreach ( array_keys( $locale_map ) as $lang ) {
            if ( preg_match( '#^/' . preg_quote( $lang, '#' ) . '(/|$)#i', $request_uri ) ) {
                $detected_lang = $lang;
                break;
            }
        }
        
        // If no target language detected, return original output
        if ( ! $detected_lang ) {
            return $output;
        }
        
        // Get BCP 47 locale
        $bcp47_lang = $locale_map[ $detected_lang ];
        
        // Replace lang attribute in output
        if ( preg_match( '/lang="[^"]*"/', $output ) ) {
            $output = preg_replace( '/lang="[^"]*"/', 'lang="' . esc_attr( $bcp47_lang ) . '"', $output );
        } else {
            // Add lang attribute if not present
            $output = 'lang="' . esc_attr( $bcp47_lang ) . '" ' . $output;
        }
        
        return $output;
    }

    /**
     * Retrieve current language code.
     *
     * @since 0.2.0
     *
     * @return string
     */
    /**
     * Get current language code.
     *
     * @since 0.2.0
     * @since 0.9.6 Added filter for other plugins.
     *
     * @return string Language code ('it' or 'en').
     */
    public function get_current_language() {
        $lang = $this->resolver->get_current_language();
        $this->current = $lang; // Keep in sync
        return apply_filters( 'fpml_current_language', $lang );
    }

    /**
     * Check if current language is English.
     *
     * @since 0.9.6
     *
     * @return bool True if English, false if Italian.
     */
    /**
     * Get target languages (enabled languages).
     *
     * @since 0.10.0
     *
     * @return array Array of enabled language codes.
     */
    public function get_target_languages() {
        $language_manager = fpml_get_language_manager();
        return $language_manager->get_enabled_languages();
    }

    /**
     * Check if a language is a target language.
     *
     * @since 0.10.0
     *
     * @param string $lang Language code to check.
     * @return bool True if the language is enabled as a target language.
     */
    public function is_target_language( $lang ) {
        $target_languages = $this->get_target_languages();
        return in_array( $lang, $target_languages, true );
    }

    /**
     * Check if current language is English.
     *
     * @since 0.2.0
     *
     * @return bool
     */
    public function is_english() {
        return ( 'en' === $this->get_current_language() );
    }

    /**
     * Check if current language is Italian.
     *
     * @since 0.9.6
     *
     * @return bool True if Italian, false if English.
     */
    public function is_italian() {
        return ( self::SOURCE === $this->get_current_language() );
    }

    /**
     * Build URL for a given language keeping the current request when possible.
     *
     * @since 0.2.0
     *
     * @param string $lang Language code.
     *
     * @return string
     */
    public function get_url_for_language( $lang ) {
        // Get enabled languages
        $language_manager = fpml_get_language_manager();
        $enabled_languages = $language_manager->get_enabled_languages();
        $available_languages = $language_manager->get_all_languages();
        
        $lang = strtolower( $lang );
        
        // If lang is not enabled or not available, default to SOURCE
        if ( ! in_array( $lang, $enabled_languages, true ) && $lang !== self::SOURCE ) {
            $lang = self::SOURCE;
        }

        // Se WPML è attivo, verifica se il post corrente usa WPML
        $wpml_active = defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'icl_object_id' );
        if ( $wpml_active && function_exists( 'icl_object_id' ) && ! is_admin() ) {
            global $post;
            if ( $post && isset( $post->ID ) ) {
                $translation_provider = get_post_meta( $post->ID, '_fpml_translation_provider', true );
                // Se il post usa WPML o 'auto' (e WPML ha la traduzione), usa WPML
                if ( $translation_provider === 'wpml' || ( $translation_provider === 'auto' && function_exists( 'icl_object_id' ) ) ) {
                    // Usa WPML per generare l'URL
                    $wpml_lang_code = $lang === 'it' ? 'it' : $lang; // WPML potrebbe usare codici diversi
                    if ( function_exists( 'apply_filters' ) ) {
                        // Usa il filtro WPML per ottenere l'URL tradotto
                        $wpml_url = apply_filters( 'wpml_permalink', get_permalink( $post->ID ), $wpml_lang_code );
                        if ( $wpml_url && $wpml_url !== get_permalink( $post->ID ) ) {
                            return esc_url_raw( $wpml_url );
                        }
                    }
                }
            }
        }

        $url = $this->get_contextual_url( $lang );

        if ( empty( $url ) ) {
            $url = $this->get_language_home( $lang );
        }

        $url = $this->apply_language_to_url( $url, $lang );

        return esc_url_raw( $url );
    }

    /**
     * Render the [fp_lang_switcher] shortcode.
     *
     * @since 0.2.0
     *
     * @param array $atts Shortcode attributes.
     *
     * @return string
     */
    public function render_switcher( $atts ) {
        return $this->switcher_renderer->render_switcher( $atts );
    }

    /**
     * Render language switcher.
     * 
     * @deprecated Use LanguageSwitcherRenderer::render_switcher() instead.
     * Kept for backward compatibility.
     *
     * @since 0.2.0
     */
    protected function _render_switcher_legacy( $atts ) {
        $atts = shortcode_atts(
            array(
                'style'      => 'auto', // auto, inline, dropdown
                'show_flags' => '0',
            ),
            $atts,
            'fp_lang_switcher'
        );

        $show_flags = in_array( (string) $atts['show_flags'], array( '1', 'true', 'yes' ), true );
        $current    = $this->get_current_language();

        // Get enabled languages dynamically
        $language_manager = fpml_get_language_manager();
        $enabled_languages = $language_manager->get_enabled_languages();
        $available_languages = $language_manager->get_all_languages();

        // Ensure arrays are valid
        if ( ! is_array( $enabled_languages ) ) {
            $enabled_languages = array();
        }
        if ( ! is_array( $available_languages ) ) {
            $available_languages = array();
        }

        $languages = array(
            self::SOURCE => array(
                'label' => __( 'Italiano', 'fp-multilanguage' ),
                'url'   => $this->get_url_for_language( self::SOURCE ),
            ),
        );

        // Add enabled target languages
        foreach ( $enabled_languages as $lang_code ) {
            if ( isset( $available_languages[ $lang_code ] ) ) {
                $lang_info = $available_languages[ $lang_code ];
                if ( is_array( $lang_info ) && isset( $lang_info['name'] ) ) {
                    $languages[ $lang_code ] = array(
                        'label' => $lang_info['name'],
                        'url'   => $this->get_url_for_language( $lang_code ),
                    );
                }
            }
        }

        // Count total languages: IT (always) + enabled languages
        $total_languages = count( $languages );

        // If only IT is available, don't show switcher
        if ( $total_languages <= 1 ) {
            return '';
        }

        // Auto-detect style based on number of languages
        // If only 2 languages total (IT + 1), use inline
        // If more than 2 languages, use dropdown
        $style = $atts['style'];
        if ( 'auto' === $style ) {
            $style = ( $total_languages <= 2 ) ? 'inline' : 'dropdown';
        }

        // Validate style
        $style = in_array( $style, array( 'inline', 'dropdown' ), true ) ? $style : 'inline';

        if ( 'dropdown' === $style ) {
            return $this->render_dropdown_switcher( $languages, $current, $show_flags );
        }

        return $this->render_inline_switcher( $languages, $current, $show_flags );
    }

    /**
     * Render inline language switcher.
     *
     * @since 0.2.0
     *
     * @param array  $languages Language map.
     * @param string $current   Current language code.
     * @param bool   $show_flags Whether to display flags.
     *
     * @return string
     */
    protected function render_inline_switcher( $languages, $current, $show_flags ) {
        $items = array();

        foreach ( $languages as $code => $language ) {
            $classes = array( 'fpml-switcher__item' );

            if ( $current === $code ) {
                $classes[] = 'fpml-switcher__item--current';
            }

            if ( $show_flags ) {
                $label = $this->maybe_prefix_flag( $code );
            } else {
                $label = esc_html( $language['label'] );
            }

            $items[] = sprintf(
                '<a class="%1$s" href="%2$s" rel="nofollow">%3$s</a>',
                esc_attr( implode( ' ', $classes ) ),
                esc_url( $language['url'] ),
                $label
            );
        }

        return sprintf(
            '<div class="fpml-switcher fpml-switcher--inline">%s</div>',
            implode( '', $items )
        );
    }

    /**
     * Render dropdown switcher.
     *
     * @since 0.2.0
     *
     * @param array  $languages Languages map.
     * @param string $current   Current language code.
     * @param bool   $show_flags Whether to display flags.
     *
     * @return string
     */
    protected function render_dropdown_switcher( $languages, $current, $show_flags ) {
        $options = array();

        foreach ( $languages as $code => $language ) {
            if ( $show_flags ) {
                $label = wp_kses_post( $this->maybe_prefix_flag( $code ) );
            } else {
                $label = esc_html( $language['label'] );
            }

            $options[] = sprintf(
                '<option value="%1$s" %3$s data-url="%2$s">%4$s</option>',
                esc_attr( $code ),
                esc_url( $language['url'] ),
                selected( $current, $code, false ),
                $label
            );
        }

        if ( ! wp_script_is( 'fpml-switcher-dropdown', 'registered' ) ) {
            wp_register_script( 'fpml-switcher-dropdown', false, array(), \FPML_PLUGIN_VERSION, true );
        }

        wp_enqueue_script( 'fpml-switcher-dropdown' );

        $script = "document.addEventListener('change',function(event){var el=event.target;if(!el.classList.contains('fpml-switcher__select')){return;}var url=el.options[el.selectedIndex].getAttribute('data-url');if(url){window.location.href=url;}});";
        wp_add_inline_script( 'fpml-switcher-dropdown', $script );

        return sprintf(
            '<div class="fpml-switcher fpml-switcher--dropdown"><select class="fpml-switcher__select">%s</select></div>',
            implode( '', $options )
        );
    }

    /**
     * Maybe prefix label with flag span.
     *
     * @since 0.2.0
     *
     * @param string $code Language code.
     *
     * @return string
     */
    protected function maybe_prefix_flag( $code ) {
        // Get flags from LanguageManager for dynamic support
        $language_manager = fpml_get_language_manager();
        $all_languages = $language_manager->get_all_languages();
        
        $flags = array(
            self::SOURCE => '🇮🇹',
        );
        
        // Add flags for all enabled languages
        foreach ( $all_languages as $lang_code => $lang_info ) {
            if ( isset( $lang_info['flag'] ) ) {
                $flags[ $lang_code ] = $lang_info['flag'];
            }
        }

        if ( ! isset( $flags[ $code ] ) ) {
            return '';
        }

        $emoji = $flags[ $code ];
        
        // Usa wp_staticize_emoji per convertire gli emoji in immagini SVG
        // Questo garantisce che le bandiere siano sempre visibili
        if ( function_exists( 'wp_staticize_emoji' ) ) {
            $emoji_html = wp_staticize_emoji( $emoji );
        } else {
            // Fallback: usa esc_html se wp_staticize_emoji non è disponibile
            $emoji_html = esc_html( $emoji );
        }

        return sprintf( '<span class="fpml-switcher__flag" aria-hidden="true">%s</span>', $emoji_html );
    }

    /**
     * Obtain the current full URL.
     *
     * @since 0.2.0
     *
     * @return string
     */
    protected function get_current_url() {
        $scheme = $this->determine_request_scheme();
        $explicit_scheme = $this->extract_explicit_scheme();

        $host = $this->resolve_current_host( $scheme );
        $uri  = isset( $_SERVER['REQUEST_URI'] ) ? $this->sanitize_request_uri( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.server___SERVER

        if ( '' === $uri ) {
            $uri = '/';
        }

        if ( '' === $host ) {
            $home_scheme = null;

            if ( '' !== $explicit_scheme ) {
                $home_scheme = $explicit_scheme;
            } elseif ( is_ssl() ) {
                $home_scheme = 'https';
            }

            return esc_url_raw( home_url( $uri, $home_scheme ) );
        }

        return esc_url_raw( $scheme . '://' . $host . $uri );
    }

    /**
     * Determine the most accurate request scheme.
     *
     * @since 0.3.2
     *
     * @return string
     */
    protected function determine_request_scheme() {
        $explicit = $this->extract_explicit_scheme();

        if ( '' !== $explicit ) {
            return $explicit;
        }

        return is_ssl() ? 'https' : 'http';
    }

    /**
     * Extract an explicit scheme from proxy headers or server variables.
     *
     * @since 0.3.2
     *
     * @return string
     */
    protected function extract_explicit_scheme() {
        $forwarded_parameters = $this->parse_forwarded_parameters();

        if ( isset( $forwarded_parameters['proto'] ) ) {
            $proto = strtolower( $forwarded_parameters['proto'] );

            if ( in_array( $proto, array( 'http', 'https' ), true ) ) {
                return $proto;
            }
        }

        $forwarded_proto = $this->get_first_forwarded_value( 'HTTP_X_FORWARDED_PROTO' );

        if ( '' !== $forwarded_proto ) {
            $forwarded_proto = strtolower( $forwarded_proto );

            if ( in_array( $forwarded_proto, array( 'http', 'https' ), true ) ) {
                return $forwarded_proto;
            }
        }

        $forwarded_ssl = $this->get_first_forwarded_value( 'HTTP_X_FORWARDED_SSL' );

        if ( '' !== $forwarded_ssl && 'on' === strtolower( $forwarded_ssl ) ) {
            return 'https';
        }

        if ( isset( $_SERVER['REQUEST_SCHEME'] ) ) { // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.server___SERVER
            $request_scheme = strtolower( trim( (string) $_SERVER['REQUEST_SCHEME'] ) ); // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.server___SERVER

            if ( in_array( $request_scheme, array( 'http', 'https' ), true ) ) {
                return $request_scheme;
            }
        }

        return '';
    }

    /**
     * Resolve the current host taking proxy headers into account.
     *
     * @since 0.3.2
     *
     * @param string $scheme Detected request scheme.
     *
     * @return string
     */
    protected function resolve_current_host( $scheme ) {
        $host = '';

        $forwarded_parameters = $this->parse_forwarded_parameters();

        if ( isset( $forwarded_parameters['host'] ) ) {
            $host = $this->sanitize_host_header( $forwarded_parameters['host'] );

            if ( '' !== $host ) {
                $forwarded_port = $this->get_first_forwarded_value( 'HTTP_X_FORWARDED_PORT' );

                if ( '' !== $forwarded_port ) {
                    $host = $this->maybe_append_port( $host, $forwarded_port, $scheme );
                }

                return $host;
            }
        }

        $forwarded_host = $this->get_first_forwarded_value( 'HTTP_X_FORWARDED_HOST' );

        if ( '' !== $forwarded_host ) {
            $host = $this->sanitize_host_header( $forwarded_host );

            if ( '' !== $host ) {
                $forwarded_port = $this->get_first_forwarded_value( 'HTTP_X_FORWARDED_PORT' );

                if ( '' !== $forwarded_port ) {
                    $host = $this->maybe_append_port( $host, $forwarded_port, $scheme );
                }
            }
        }

        if ( '' === $host && isset( $_SERVER['HTTP_HOST'] ) ) { // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.server___SERVER
            $host = $this->sanitize_host_header( $_SERVER['HTTP_HOST'] ); // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.server___SERVER
        }

        if ( '' === $host && isset( $_SERVER['SERVER_NAME'] ) ) { // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.server___SERVER
            $server_name = $this->sanitize_host_header( $_SERVER['SERVER_NAME'] ); // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.server___SERVER

            if ( '' !== $server_name ) {
                $host = $server_name;

                if ( isset( $_SERVER['SERVER_PORT'] ) ) { // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.server___SERVER
                    $host = $this->maybe_append_port( $host, (string) $_SERVER['SERVER_PORT'], $scheme ); // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.server___SERVER
                }
            }
        }

        return $host;
    }

    /**
     * Extract the first value of a forwarded header.
     *
     * @since 0.3.2
     *
     * @param string $key Server key to inspect.
     *
     * @return string
     */
    protected function get_first_forwarded_value( $key ) {
        if ( ! isset( $_SERVER[ $key ] ) ) { // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.server___SERVER
            return '';
        }

        $value = wp_unslash( (string) $_SERVER[ $key ] ); // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.server___SERVER
        $value = trim( $value );

        if ( '' === $value ) {
            return '';
        }

        $segments = explode( ',', $value );
        $first    = trim( $segments[0] );

        return $first;
    }

    /**
     * Parse the first Forwarded header value into sanitized host/proto parameters.
     *
     * Ensures proto is only associated with the same entry that provided the host so
     * we never mix details from different proxy hops.
     *
     * @since 0.3.3
     *
     * @return array<string,string>
     */
    protected function parse_forwarded_parameters() {
        $raw_header = null;

        if ( isset( $_SERVER['HTTP_FORWARDED'] ) ) { // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.server___SERVER
            $raw_header = wp_unslash( (string) $_SERVER['HTTP_FORWARDED'] ); // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.server___SERVER
            $raw_header = trim( $raw_header );

            if ( '' === $raw_header ) {
                $raw_header = '';
            } else {
                $raw_header = preg_replace( '/[\x00-\x1F\x7F]+/', '', $raw_header );

                if ( ! is_string( $raw_header ) ) {
                    $raw_header = '';
                }
            }
        }

        if ( null !== $this->forwarded_parameters && $this->forwarded_parameters_raw === $raw_header ) {
            return $this->forwarded_parameters;
        }

        $this->forwarded_parameters     = array();
        $this->forwarded_parameters_raw = $raw_header;

        if ( null === $raw_header || '' === $raw_header ) {
            return $this->forwarded_parameters;
        }

        $entries = array();
        $length  = strlen( $raw_header );
        $buffer  = '';
        $quoted  = false;
        $escape  = false;

        for ( $i = 0; $i < $length; $i++ ) {
            $character = $raw_header[ $i ];

            if ( $escape ) {
                $buffer .= $character;
                $escape  = false;
                continue;
            }

            $code = ord( $character );

            if ( 92 === $code ) {
                $buffer .= $character;
                $escape  = true;
                continue;
            }

            if ( 34 === $code ) {
                $quoted  = ! $quoted;
                $buffer .= $character;
                continue;
            }

            if ( 44 === $code && ! $quoted ) {
                $entries[] = trim( $buffer );
                $buffer    = '';
                continue;
            }

            $buffer .= $character;
        }

        if ( '' !== trim( $buffer ) ) {
            $entries[] = trim( $buffer );
        }

        if ( empty( $entries ) ) {
            return $this->forwarded_parameters;
        }

        $parameters      = array();
        $proto_candidate = '';

        foreach ( $entries as $entry ) {
            if ( '' === $entry ) {
                continue;
            }

            $pairs        = array();
            $pair_buffer  = '';
            $pair_quoted  = false;
            $pair_escape  = false;
            $entry_length = strlen( $entry );

            for ( $j = 0; $j < $entry_length; $j++ ) {
                $entry_character = $entry[ $j ];

                if ( $pair_escape ) {
                    $pair_buffer .= $entry_character;
                    $pair_escape  = false;
                    continue;
                }

                if ( '\\' === $entry_character ) {
                    $pair_buffer .= $entry_character;
                    $pair_escape  = true;
                    continue;
                }

                if ( '"' === $entry_character ) {
                    $pair_quoted  = ! $pair_quoted;
                    $pair_buffer .= $entry_character;
                    continue;
                }

                if ( ';' === $entry_character && ! $pair_quoted ) {
                    $pairs[]     = trim( $pair_buffer );
                    $pair_buffer = '';
                    continue;
                }

                $pair_buffer .= $entry_character;
            }

            if ( '' !== trim( $pair_buffer ) ) {
                $pairs[] = trim( $pair_buffer );
            }

            if ( empty( $pairs ) ) {
                continue;
            }

            $entry_host  = '';
            $entry_proto = '';

            foreach ( $pairs as $pair ) {
                $equals_position = strpos( $pair, '=' );

                if ( false === $equals_position ) {
                    continue;
                }

                $name  = strtolower( trim( substr( $pair, 0, $equals_position ) ) );
                $value = trim( substr( $pair, $equals_position + 1 ) );

                if ( '' === $name || '' === $value ) {
                    continue;
                }

                if ( '"' === substr( $value, 0, 1 ) && '"' === substr( $value, -1 ) ) {
                    $value = substr( $value, 1, -1 );
                    $value = preg_replace( '/\\\\(.)/', '$1', $value );

                    if ( ! is_string( $value ) ) {
                        continue;
                    }
                }

                if ( 'host' === $name && '' === $entry_host ) {
                    $sanitized_host = $this->sanitize_host_header( $value );

                    if ( '' !== $sanitized_host ) {
                        $entry_host = $sanitized_host;
                    }
                }

                if ( 'proto' === $name && '' === $entry_proto ) {
                    $normalized_proto = strtolower( $value );

                    if ( in_array( $normalized_proto, array( 'http', 'https' ), true ) ) {
                        $entry_proto = $normalized_proto;
                    }
                }
            }

            if ( '' !== $entry_host ) {
                if ( '' === $entry_proto ) {
                    unset( $parameters['proto'] );
                } else {
                    $parameters['proto'] = $entry_proto;
                }

                $parameters['host'] = $entry_host;
                break;
            }

            if ( '' === $proto_candidate && '' !== $entry_proto ) {
                $proto_candidate = $entry_proto;
            }
        }

        if ( ! isset( $parameters['host'] ) && '' !== $proto_candidate ) {
            $parameters['proto'] = $proto_candidate;
        }

        $this->forwarded_parameters = $parameters;

        return $this->forwarded_parameters;
    }



    /**
     * Maybe append a port to a host if appropriate.
     *
     * @since 0.3.2
     *
     * @param string      $host   Sanitized host.
     * @param string|null $port   Candidate port number.
     * @param string      $scheme Request scheme.
     *
     * @return string
     */
    protected function maybe_append_port( $host, $port, $scheme ) {
        $port = trim( (string) $port );

        if ( '' === $port || ! ctype_digit( $port ) ) {
            return $host;
        }

        $port_number = (int) $port;

        if ( $port_number < 1 || $port_number > 65535 ) {
            return $host;
        }

        if ( 'http' === $scheme && 80 === $port_number ) {
            return $host;
        }

        if ( 'https' === $scheme && 443 === $port_number ) {
            return $host;
        }

        if ( str_starts_with( $host, '[' ) ) {
            $closing = strpos( $host, ']' );

            if ( false !== $closing && isset( $host[ $closing + 1 ] ) && ':' === $host[ $closing + 1 ] ) {
                return $host;
            }

            $host_with_port = $host . ':' . $port;

            return $this->sanitize_host_header( $host_with_port );
        }

        if ( str_contains( $host, ':' ) ) {
            return $host;
        }

        $host_with_port = $host . ':' . $port;

        return $this->sanitize_host_header( $host_with_port );
    }

    /**
     * Sanitize the host header preserving valid port or IPv6 notations.
     *
     * @since 0.3.1
     *
     * @param string $host Raw host header.
     *
     * @return string
     */
    protected function sanitize_host_header( $host ) {
        if ( ! is_string( $host ) ) {
            return '';
        }

        $host = wp_unslash( $host );
        $host = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $host ) : strip_tags( $host );
        $host = preg_replace( '/[\r\n\t ]+/', '', $host );

        if ( is_string( $host ) ) {
            $segments = preg_split( '/[\x00-\x1F\x7F]+/', $host, 2 );

            if ( is_array( $segments ) && isset( $segments[0] ) ) {
                $host = $segments[0];
            }
        }

        if ( null === $host ) {
            return '';
        }

        $normalized_host = $host;

        $scheme_position = stripos( $normalized_host, '://' );

        if ( false !== $scheme_position ) {
            $normalized_host = substr( $normalized_host, $scheme_position + 3 );
        } elseif ( str_starts_with( $normalized_host, '//' ) ) {
            $normalized_host = substr( $normalized_host, 2 );
        }

        $at_position = strrpos( $normalized_host, '@' );

        if ( false !== $at_position ) {
            $normalized_host = substr( $normalized_host, $at_position + 1 );
        }

        $maybe_ipv6_literal = str_starts_with( $normalized_host, '[' );

        if ( ! $maybe_ipv6_literal && str_contains( $normalized_host, '%' ) ) {
            $decoded        = $normalized_host;
            $max_iterations = 5;
            $iteration      = 0;

            do {
                $previous = $decoded;
                $decoded  = rawurldecode( $decoded );

                if ( ! is_string( $decoded ) ) {
                    break;
                }

                $decoded = preg_replace( '/[\r\n\t ]+/', '', $decoded );

                if ( ! is_string( $decoded ) ) {
                    break;
                }

                $segments = preg_split( '/[\x00-\x1F\x7F]+/', $decoded, 2 );

                if ( is_array( $segments ) && isset( $segments[0] ) ) {
                    $decoded = $segments[0];
                }

                $iteration++;
            } while ( $iteration < $max_iterations && $decoded !== $previous && str_contains( $decoded, '%' ) );

            if ( is_string( $decoded ) ) {
                $normalized_host = $decoded;

                $scheme_position = stripos( $normalized_host, '://' );

                if ( false !== $scheme_position ) {
                    $normalized_host = substr( $normalized_host, $scheme_position + 3 );
                } elseif ( str_starts_with( $normalized_host, '//' ) ) {
                    $normalized_host = substr( $normalized_host, 2 );
                }

                $at_position = strrpos( $normalized_host, '@' );

                if ( false !== $at_position ) {
                    $normalized_host = substr( $normalized_host, $at_position + 1 );
                }
            }
        }

        $authority_segments = preg_split( '/[\/?#]/', $normalized_host, 2 );

        if ( is_array( $authority_segments ) && isset( $authority_segments[0] ) && '' !== $authority_segments[0] ) {
            $host = $authority_segments[0];
        }

        $host = preg_replace( '/[^A-Za-z0-9\.\-:\[\]_%]/', '', $host );

        if ( ! is_string( $host ) ) {
            return '';
        }

        $is_ipv6 = str_starts_with( $host, '[' );

        if ( ! $is_ipv6 && str_contains( $host, '%' ) ) {
            $host = preg_replace( '/%[0-9A-Fa-f]{2}/', '', $host );

            if ( ! is_string( $host ) ) {
                return '';
            }

            $host = str_replace( '%', '', $host );
        }

        if ( '' === $host ) {
            return '';
        }

        if ( $is_ipv6 ) {
            $closing = strpos( $host, ']' );

            if ( false === $closing ) {
                return '';
            }

            $address   = substr( $host, 0, $closing + 1 );
            $remainder = substr( $host, $closing + 1 );

            if ( '' === $remainder ) {
                return $address;
            }

            if ( ':' !== $remainder[0] ) {
                return $address;
            }

            $port = substr( $remainder, 1 );

            if ( '' === $port ) {
                return $address;
            }

            if ( ctype_digit( $port ) ) {
                if ( strlen( $port ) > 5 ) {
                    return $address;
                }

                if ( (int) $port > 65535 ) {
                    return $address;
                }

                return $address . ':' . $port;
            }

            return $address;
        }

        $segments = explode( ':', $host, 2 );
        $name     = $segments[0];

        if ( '' === $name ) {
            return '';
        }

        if ( ! isset( $segments[1] ) ) {
            return $name;
        }

        $port = $segments[1];

        if ( '' === $port ) {
            return $name;
        }

        if ( ctype_digit( $port ) ) {
            if ( strlen( $port ) > 5 ) {
                return $name;
            }

            if ( (int) $port > 65535 ) {
                return $name;
            }

            return $name . ':' . $port;
        }

        return $name;
    }

    /**
     * Sanitize the current request URI keeping query string and fragments.
     *
     * @since 0.3.1
     *
     * @param string $uri Raw request URI.
     *
     * @return string
     */
    protected function sanitize_request_uri( $uri ) {
        if ( ! is_string( $uri ) ) {
            return '/';
        }

        $uri = wp_unslash( $uri );
        $uri = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $uri ) : strip_tags( $uri );
        $uri = preg_replace( '/[\x00-\x1F\x7F]+/', '', $uri );

        if ( null === $uri ) {
            return '/';
        }

        $uri = trim( $uri );

        if ( '' === $uri ) {
            return '/';
        }

        if ( preg_match( '#^[a-z][a-z0-9+\-.]*://#i', $uri ) ) {
            $parts = wp_parse_url( $uri );

            if ( false === $parts ) {
                return '/';
            }

            $uri = '';

            if ( isset( $parts['path'] ) ) {
                $uri = (string) $parts['path'];
            }

            if ( isset( $parts['query'] ) && '' !== $parts['query'] ) {
                $uri .= '?' . $parts['query'];
            }

            if ( isset( $parts['fragment'] ) && '' !== $parts['fragment'] ) {
                $uri .= '#' . $parts['fragment'];
            }

            $uri = trim( $uri );
        }

        if ( '' === $uri ) {
            return '/';
        }

        return '/' . ltrim( $uri, '/' );
    }

    /**
     * Get current request path.
     *
     * @since 0.2.0
     *
     * @return string
     */
    protected function get_current_path() {
        $current = wp_parse_url( $this->get_current_url(), PHP_URL_PATH );

        if ( ! is_string( $current ) ) {
            return '/';
        }

        if ( '' === $current ) {
            return '/';
        }

        if ( '/' !== substr( $current, 0, 1 ) ) {
            $current = '/' . $current;
        }

        $current = $this->strip_installation_path( $current );

        if ( '' === $current || '/' === $current ) {
            return '/';
        }

        return untrailingslashit( $current ) . '/';
    }

    /**
     * Strip language segment from a path.
     *
     * @since 0.2.0
     *
     * @param string $path Current path.
     *
     * @return string
     */
    protected function strip_language_segment( $path ) {
        $path = '/' === $path ? '/' : untrailingslashit( $path ) . '/';

        $lowered = strtolower( $path );

        if ( 0 === strpos( $lowered, '/en/' ) ) {
            $path = substr( $path, 3 );
        } elseif ( '/en' === rtrim( $lowered, '/' ) ) {
            $path = '/';
        }

        return $path;
    }

    /**
     * Remove the WordPress installation path from a request path.
     *
     * Ensures detection works on subdirectory installs.
     *
     * @since 0.3.1
     *
     * @param string $path Absolute path including the leading slash.
     *
     * @return string
     */
    protected function strip_installation_path( $path ) {
        if ( ! is_string( $path ) || '' === $path ) {
            return '';
        }

        if ( '/' !== substr( $path, 0, 1 ) ) {
            $path = '/' . $path;
        }

        $base_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );

        if ( ! is_string( $base_path ) ) {
            $base_path = '';
        }

        $base_path = '/' . trim( $base_path, '/' );

        if ( '/' === $base_path ) {
            return $path;
        }

        $base_trailing       = trailingslashit( $base_path );
        $base_trailing_lower = strtolower( $base_trailing );
        $path_lower          = strtolower( $path );

        if ( 0 === strpos( $path_lower, $base_trailing_lower ) ) {
            $remainder = substr( $path, strlen( $base_trailing ) );
            $remainder = '/' . ltrim( $remainder, '/' );

            if ( '/' === substr( $path, -1 ) ) {
                return untrailingslashit( $remainder ) . '/';
            }

            return '/' === $remainder ? '/' : $remainder;
        }

        if ( 0 === strcasecmp( rtrim( $path, '/' ), rtrim( $base_path, '/' ) ) ) {
            return '/';
        }

        return $path;
    }

    /**
     * Derive the contextual URL for the requested language.
     *
     * @since 0.2.0
     *
     * @param string $lang Target language code.
     *
     * @return string
     */
    protected function get_contextual_url( $lang ) {
        if ( is_singular() ) {
            $object = get_queried_object();

            if ( $object instanceof WP_Post ) {
                $url = $this->get_post_translation_url( $object, $lang );

                if ( ! empty( $url ) ) {
                    return $url;
                }
            }
        }

        if ( is_tax() || is_category() || is_tag() ) {
            $term = get_queried_object();

            if ( $term instanceof WP_Term ) {
                $url = $this->get_term_translation_url( $term, $lang );

                if ( ! empty( $url ) ) {
                    return $url;
                }
            }
        }

        if ( is_post_type_archive() ) {
            $post_type = get_query_var( 'post_type' );

            if ( is_array( $post_type ) ) {
                $post_type = reset( $post_type );
            }

            if ( is_string( $post_type ) && '' !== $post_type ) {
                $link = get_post_type_archive_link( $post_type );

                if ( $link ) {
                    return $link;
                }
            }

            return $this->get_current_url();
        }

        if ( is_search() || is_author() || is_date() ) {
            return $this->get_current_url();
        }

        return '';
    }

    /**
     * Retrieve the translated permalink for a post.
     *
     * @since 0.2.0
     *
     * @param WP_Post $post Post object.
     * @param string  $lang Language code.
     *
     * @return string
     */
    protected function get_post_translation_url( WP_Post $post, $lang ) {
        // Check if lang is a target language
        if ( fpml_is_target_language( $lang ) ) {
            // Vogliamo andare alla versione inglese
            if ( get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
                // Siamo già sulla versione inglese, restituisci il permalink corrente
                return get_permalink( $post );
            }

            // Siamo sulla versione italiana, cerca la traduzione per la lingua target
            $target_id = fpml_get_translation_id( $post->ID, $lang );
            // Backward compatibility: check legacy _fpml_pair_id if no translation found and lang is 'en'
            if ( ! $target_id && 'en' === $lang ) {
                $target_id = (int) get_post_meta( $post->ID, '_fpml_pair_id', true );
            }

            if ( $target_id > 0 ) {
                $target = get_post( $target_id );

                if ( $target instanceof WP_Post && in_array( $target->post_status, array( 'publish', 'inherit' ), true ) ) {
                    // Rimuovi temporaneamente i filtri per evitare che aggiungano /en/ quando non necessario
                    // Il permalink della traduzione dovrebbe già avere /en/ grazie al filtro
                    return get_permalink( $target );
                }
            }

            return '';
        }

        // Vogliamo andare alla versione italiana
        if ( get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
            // Siamo sulla versione inglese, cerca la versione italiana (source)
            $source_id = (int) get_post_meta( $post->ID, '_fpml_pair_source_id', true );

            if ( $source_id > 0 ) {
                $source = get_post( $source_id );

                if ( $source instanceof WP_Post && in_array( $source->post_status, array( 'publish', 'inherit' ), true ) ) {
                    // Rimuovi temporaneamente i filtri per evitare che aggiungano /en/ alla versione italiana
                    remove_filter( 'post_link', array( $this, 'filter_translation_permalink' ), 10 );
                    remove_filter( 'page_link', array( $this, 'filter_translation_permalink' ), 10 );
                    remove_filter( 'post_type_link', array( $this, 'filter_translation_permalink' ), 10 );
                    remove_filter( 'home_url', array( $this, 'filter_home_url_for_en' ), 10 );
                    
                    try {
                        $permalink = get_permalink( $source );
                    } finally {
                        // Riapplica sempre i filtri, anche in caso di errore
                        add_filter( 'post_link', array( $this, 'filter_translation_permalink' ), 10, 2 );
                        add_filter( 'page_link', array( $this, 'filter_translation_permalink' ), 10, 2 );
                        add_filter( 'post_type_link', array( $this, 'filter_translation_permalink' ), 10, 2 );
                        add_filter( 'home_url', array( $this, 'filter_home_url_for_en' ), 10, 2 );
                    }
                    
                    return $permalink;
                }
            }

            return '';
        }

        // Siamo già sulla versione italiana, restituisci il permalink corrente
        // Rimuovi temporaneamente i filtri per evitare che aggiungano /en/
        remove_filter( 'post_link', array( $this, 'filter_translation_permalink' ), 10 );
        remove_filter( 'page_link', array( $this, 'filter_translation_permalink' ), 10 );
        remove_filter( 'post_type_link', array( $this, 'filter_translation_permalink' ), 10 );
        remove_filter( 'home_url', array( $this, 'filter_home_url_for_en' ), 10 );
        
        try {
            $permalink = get_permalink( $post );
        } finally {
            // Riapplica sempre i filtri, anche in caso di errore
            add_filter( 'post_link', array( $this, 'filter_translation_permalink' ), 10, 2 );
            add_filter( 'page_link', array( $this, 'filter_translation_permalink' ), 10, 2 );
            add_filter( 'post_type_link', array( $this, 'filter_translation_permalink' ), 10, 2 );
            add_filter( 'home_url', array( $this, 'filter_home_url_for_en' ), 10, 2 );
        }
        
        return $permalink;
    }

    /**
     * Retrieve the translated permalink for a taxonomy term.
     *
     * @since 0.2.0
     *
     * @param WP_Term $term Term object.
     * @param string  $lang Language code.
     *
     * @return string
     */
    protected function get_term_translation_url( WP_Term $term, $lang ) {
        // Check if lang is a target language
        if ( fpml_is_target_language( $lang ) ) {
            // Vogliamo andare alla versione inglese
            if ( get_term_meta( $term->term_id, '_fpml_is_translation', true ) ) {
                // Siamo già sulla versione inglese, restituisci il link corrente
                $link = get_term_link( $term );
                return is_wp_error( $link ) ? '' : $link;
            }

            // Siamo sulla versione italiana, cerca la traduzione inglese
            $target_id = (int) get_term_meta( $term->term_id, '_fpml_pair_id', true );

            if ( $target_id > 0 ) {
                $target = get_term( $target_id, $term->taxonomy );

                if ( $target instanceof WP_Term ) {
                    // Il permalink della traduzione dovrebbe già avere /en/ grazie al filtro
                    $link = get_term_link( $target );
                    return is_wp_error( $link ) ? '' : $link;
                }
            }

            return '';
        }

        // Vogliamo andare alla versione italiana
        if ( get_term_meta( $term->term_id, '_fpml_is_translation', true ) ) {
            // Siamo sulla versione inglese, cerca la versione italiana (source)
            $source_id = (int) get_term_meta( $term->term_id, '_fpml_pair_source_id', true );

            if ( $source_id > 0 ) {
                $source = get_term( $source_id, $term->taxonomy );

                if ( $source instanceof WP_Term ) {
                    // Rimuovi temporaneamente i filtri per evitare che aggiungano /en/ alla versione italiana
                    remove_filter( 'term_link', array( $this, 'filter_term_permalink' ), 10 );
                    remove_filter( 'home_url', array( $this, 'filter_home_url_for_en' ), 10 );
                    
                    try {
                        $link = get_term_link( $source );
                    } finally {
                        // Riapplica sempre i filtri, anche in caso di errore
                        add_filter( 'term_link', array( $this, 'filter_term_permalink' ), 10, 2 );
                        add_filter( 'home_url', array( $this, 'filter_home_url_for_en' ), 10, 2 );
                    }
                    
                    return is_wp_error( $link ) ? '' : $link;
                }
            }

            return '';
        }

        // Siamo già sulla versione italiana, restituisci il link corrente
        // Rimuovi temporaneamente i filtri per evitare che aggiungano /en/
        remove_filter( 'term_link', array( $this, 'filter_term_permalink' ), 10 );
        remove_filter( 'home_url', array( $this, 'filter_home_url_for_en' ), 10 );
        
        try {
            $link = get_term_link( $term );
        } finally {
            // Riapplica sempre i filtri, anche in caso di errore
            add_filter( 'term_link', array( $this, 'filter_term_permalink' ), 10, 2 );
            add_filter( 'home_url', array( $this, 'filter_home_url_for_en' ), 10, 2 );
        }
        
        return is_wp_error( $link ) ? '' : $link;
    }

    /**
     * Retrieve cached term pairs from storage.
     *
     * @since 0.3.0
     * @deprecated Use TermPairManager::get_term_pairs() instead.
     *
     * @return array
     */
    protected function get_term_pairs() {
        return $this->term_pair_manager->get_term_pairs();
    }

    /**
     * Retrieve the translated term ID for a given source term.
     *
     * @since 0.3.0
     *
     * @param int $source_id Source term ID.
     *
     * @return int
     */
    public function get_term_translation_id( $source_id ) {
        return $this->term_pair_manager->get_term_translation_id( $source_id );
    }

    /**
     * Retrieve the source term ID for a translated term.
     *
     * @since 0.3.0
     *
     * @param int $target_id Target term ID.
     *
     * @return int
     */
    public function get_term_source_id( $target_id ) {
        return $this->term_pair_manager->get_term_source_id( $target_id );
    }

    /**
     * Persist the mapping between source and translated terms.
     *
     * @since 0.3.0
     *
     * @param int $source_id Source term ID.
     * @param int $target_id Target term ID.
     *
     * @return bool
     */
    public function set_term_pair( $source_id, $target_id ) {
        return $this->term_pair_manager->set_term_pair( $source_id, $target_id );
    }
    /**
     * Return the language-specific home URL baseline.
     *
     * @since 0.2.0
     *
     * @param string $lang Language code.
     *
     * @return string
     */
    protected function get_language_home( $lang ) {
        unset( $lang );

        // Rimuovi temporaneamente i filtri per evitare che aggiungano /en/ quando non necessario
        // Questo è critico quando si genera l'URL per l'italiano da una pagina /en/
        remove_filter( 'home_url', array( $this, 'filter_home_url_for_en' ), 10 );
        remove_filter( 'site_url', array( $this, 'filter_site_url_for_en' ), 10 );
        
        try {
            $url = home_url( '/' );
        } finally {
            // Riapplica sempre i filtri, anche in caso di errore
            add_filter( 'home_url', array( $this, 'filter_home_url_for_en' ), 10, 2 );
            add_filter( 'site_url', array( $this, 'filter_site_url_for_en' ), 10, 2 );
        }
        
        return $url;
    }

    /**
     * Apply routing rules to a base URL according to language.
     *
     * @since 0.2.0
     *
     * @param string $url  Base URL.
     * @param string $lang Language code.
     *
     * @return string
     */
    protected function apply_language_to_url( $url, $lang ) {
        $routing = $this->settings->get( 'routing_mode', 'segment' );

        if ( 'segment' !== $routing ) {
            $url = remove_query_arg( array( 'lang', '\FPML_lang', 'fpml_lang' ), $url );

            // Check if lang is a target language
        if ( fpml_is_target_language( $lang ) ) {
                $url = add_query_arg( 'lang', $lang, $url );
            }

            return $url;
        }

        $url    = remove_query_arg( array( 'lang', '\FPML_lang', 'fpml_lang' ), $url );
        $parsed = wp_parse_url( $url );

        if ( false === $parsed ) {
            return $url;
        }

        $path = isset( $parsed['path'] ) ? $parsed['path'] : '/';
        $path = $this->normalize_path( $path );

        // Check if lang is a target language and get its slug
        if ( fpml_is_target_language( $lang ) ) {
            $language_manager = fpml_get_language_manager();
            $lang_info = $language_manager->get_language_info( $lang );
            $lang_slug = $lang_info ? trim( $lang_info['slug'], '/' ) : 'en';
            $path = $lang_slug . ( '' !== $path ? '/' . $path : '' );
        }

        $path = trim( $path, '/' );

        if ( '' === $path ) {
            $formatted_path = user_trailingslashit( '' );
        } else {
            $formatted_path = user_trailingslashit( $path );
        }

        if ( '' !== $formatted_path && '/' !== substr( $formatted_path, 0, 1 ) ) {
            $formatted_path = '/' . ltrim( $formatted_path, '/' );
        }

        // Rimuovi temporaneamente i filtri per evitare che aggiungano /en/ quando non necessario
        // Questo è critico quando si genera l'URL per l'italiano da una pagina /en/
        remove_filter( 'home_url', array( $this, 'filter_home_url_for_en' ), 10 );
        remove_filter( 'site_url', array( $this, 'filter_site_url_for_en' ), 10 );
        
        try {
            $target = home_url( $formatted_path );
        } finally {
            // Riapplica sempre i filtri, anche in caso di errore
            add_filter( 'home_url', array( $this, 'filter_home_url_for_en' ), 10, 2 );
            add_filter( 'site_url', array( $this, 'filter_site_url_for_en' ), 10, 2 );
        }

        if ( ! empty( $parsed['query'] ) ) {
            $target = rtrim( $target, '?' );
            $target .= '?' . $parsed['query'];
        }

        if ( ! empty( $parsed['fragment'] ) ) {
            $fragment = sanitize_text_field( $parsed['fragment'] );

            if ( '' !== $fragment ) {
                $target .= '#' . rawurlencode( $fragment );
            }
        }

        return $target;
    }

    /**
     * Normalize an URL path stripping the language segment.
     *
     * @since 0.2.0
     *
     * @param string $path Path to normalize.
     *
     * @return string
     */
    protected function normalize_path( $path ) {
        if ( ! is_string( $path ) || '' === $path ) {
            return '';
        }

        if ( '/' !== substr( $path, 0, 1 ) ) {
            $path = '/' . $path;
        }

        $path = $this->strip_installation_path( $path );
        $path = $this->strip_language_segment( $path );

        return trim( $path, '/' );
    }

    /**
     * Determine whether user consent is available for redirect/cookies.
     *
     * @since 0.2.0
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

        /**
         * Filter the detection of cookie consent value.
         *
         * @since 0.2.0
         *
         * @param bool   $has_consent Whether consent is granted.
         * @param string $cookie_name Cookie name.
         * @param string $raw_value   Raw cookie value.
         */
        return (bool) apply_filters( '\FPML_has_cookie_consent', true, $cookie_name, $raw_value );
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
    private function add_en_prefix_to_url( $url, $path = '' ) {
        // Se siamo in admin, non processare
        if ( is_admin() ) {
            return $url;
        }

        // CRITICO: Se l'URL contiene già un URL completo dentro (es: /en/http://), correggilo PRIMA di qualsiasi altra logica
        // Questo indica che è già stato processato erroneamente
        if ( preg_match( '#/en/http[s]?://#', $url ) || preg_match( '#http[s]?://[^/]+/en/http[s]?://#', $url ) ) {
            // Conta quante volte appare http:// o https://
            $http_count = substr_count( $url, 'http://' ) + substr_count( $url, 'https://' );
            
            if ( $http_count > 1 ) {
                // Estrai sempre l'ultimo URL completo (dopo l'ultimo http:// o https://)
                $last_http_pos = strrpos( $url, 'http://' );
                $last_https_pos = strrpos( $url, 'https://' );
                
                // Usa la posizione più grande (più a destra)
                $last_pos = false;
                if ( $last_http_pos !== false && $last_https_pos !== false ) {
                    $last_pos = max( $last_http_pos, $last_https_pos );
                } elseif ( $last_http_pos !== false ) {
                    $last_pos = $last_http_pos;
                } elseif ( $last_https_pos !== false ) {
                    $last_pos = $last_https_pos;
                }
                
                if ( $last_pos !== false ) {
                    // Estrai tutto dopo l'ultimo http:// o https://
                    $url = substr( $url, $last_pos );
                    
                    // Verifica che l'URL estratto sia valido
                    $parsed = parse_url( $url );
                    if ( ! $parsed || ! isset( $parsed['host'] ) ) {
                        // Se l'estrazione non ha funzionato, prova un approccio alternativo
                        // Cerca il pattern: dominio + /en/ + path
                        if ( preg_match( '#http[s]?://([^/]+)/en/(.*)$#', $url, $match ) ) {
                            $url = ( strpos( $url, 'https://' ) !== false ? 'https://' : 'http://' ) . $match[1] . '/en/' . $match[2];
                        } elseif ( preg_match( '#http[s]?://([^/]+)(/.*)$#', $url, $match ) ) {
                            $url = ( strpos( $url, 'https://' ) !== false ? 'https://' : 'http://' ) . $match[1] . $match[2];
                        }
                    }
                    
                    // Se l'URL corretto contiene già /en/, non processarlo di nuovo
                    if ( false !== strpos( $url, '/en/' ) ) {
                        return $url;
                    }
                }
            }
        }
        
        // Evita loop infiniti: se l'URL contiene già /en/, non processarlo di nuovo
        if ( false !== strpos( $url, '/en/' ) || false !== strpos( $url, '/en-' ) ) {
            return $url;
        }

        // Se il path passato è già un URL completo (contiene ://), non processarlo
        if ( ! empty( $path ) && false !== strpos( $path, '://' ) ) {
            return $url;
        }

        // Determina se siamo su un path di lingua target o se il cookie è impostato
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        $is_english_path = fpml_url_contains_target_language( $request_uri );
        $lang_cookie = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? sanitize_text_field( $_COOKIE[ self::COOKIE_NAME ] ) : '';
        $is_target_lang_preference = ( fpml_is_target_language( $lang_cookie ) || $is_english_path );

        if ( ! $is_target_lang_preference ) {
            return $url;
        }

        // Solo se il routing mode è 'segment'
        $routing = $this->settings->get( 'routing_mode', 'segment' );
        if ( 'segment' !== $routing ) {
            return $url;
        }

        // Evita loop: usa get_option direttamente invece di home_url() per evitare ricorsione
        remove_filter( 'home_url', array( $this, 'filter_home_url_for_en' ), 10 );
        remove_filter( 'site_url', array( $this, 'filter_site_url_for_en' ), 10 );
        
        try {
            // Usa get_option direttamente per evitare loop
            $home_url_raw = get_option( 'home' );
            if ( is_ssl() ) {
                $home_url_raw = set_url_scheme( $home_url_raw, 'https' );
            }
            $home_url_base = trailingslashit( $home_url_raw );
        } finally {
            // Riapplica sempre i filtri, anche in caso di errore
            add_filter( 'home_url', array( $this, 'filter_home_url_for_en' ), 10, 2 );
            add_filter( 'site_url', array( $this, 'filter_site_url_for_en' ), 10, 2 );
        }

        // Estrai solo il path relativo dall'URL usando parse_url per maggiore sicurezza
        $parsed_url = parse_url( $url );
        
        // Se parse_url fallisce o non ha host, potrebbe essere un path relativo
        if ( ! $parsed_url || ! isset( $parsed_url['host'] ) ) {
            // È un path relativo, aggiungi direttamente /en/
            $url_path = isset( $parsed_url['path'] ) ? $parsed_url['path'] : $url;
            $url_path = ltrim( $url_path, '/' );
            
            if ( 'en/' !== substr( $url_path, 0, 3 ) && 'en' !== $url_path ) {
                $url = $home_url_base . 'en/' . $url_path;
            } else {
                $url = $home_url_base . $url_path;
            }
            
            // Aggiungi query string e fragment se presenti
            if ( isset( $parsed_url['query'] ) ) {
                $url .= '?' . $parsed_url['query'];
            }
            if ( isset( $parsed_url['fragment'] ) ) {
                $url .= '#' . $parsed_url['fragment'];
            }
            
            return $url;
        }
        
        // URL completo con host
        $url_scheme = isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] . '://' : 'http://';
        $url_host = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
        $url_path = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '/';
        $url_query = isset( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '';
        $url_fragment = isset( $parsed_url['fragment'] ) ? '#' . $parsed_url['fragment'] : '';
        
        // Verifica che l'host corrisponda al nostro dominio
        $parsed_home = parse_url( $home_url_base );
        $home_host = isset( $parsed_home['host'] ) ? $parsed_home['host'] : '';
        
        if ( $url_host !== $home_host ) {
            // URL esterno, non processare
            return $url;
        }
        
        // Estrai il path relativo
        $rel_path = ltrim( $url_path, '/' );
        
        // Se il path non inizia già con en/, aggiungilo
        if ( 'en/' !== substr( $rel_path, 0, 3 ) && 'en' !== $rel_path ) {
            if ( ! empty( $rel_path ) ) {
                $url = $url_scheme . $url_host . '/' . 'en/' . $rel_path . $url_query . $url_fragment;
            } else {
                $url = $url_scheme . $url_host . '/en/' . $url_query . $url_fragment;
            }
        }

        return $url;
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
        return $this->url_filter->filter_home_url_for_en( $url, $path );
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
        return $this->url_filter->filter_site_url_for_en( $url, $path );
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
        return $this->url_filter->filter_pagenum_link_for_en( $result, $pagenum );
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
        return $this->url_filter->filter_comments_pagenum_link_for_en( $result );
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
        return $this->url_filter->filter_bloginfo_url_for_en( $output, $show );
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
        return $this->url_filter->filter_paginate_links_for_en( $link );
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
        return $this->url_filter->filter_nectar_logo_url_for_en( $url );
    }

    /**
     * Filter nectar logo URL.
     * 
     * @deprecated Use UrlFilter::filter_nectar_logo_url_for_en() instead.
     * Kept for backward compatibility.
     *
     * @since 0.9.4
     */
    protected function _filter_nectar_logo_url_for_en_legacy( $url ) {
        // Se l'URL contiene già duplicati, correggilo prima
        if ( preg_match( '#/en/http[s]?://#', $url ) || preg_match( '#http[s]?://[^/]+/en/http[s]?://#', $url ) ) {
            // Estrai solo l'ultimo URL completo
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
        
        // Se l'URL corretto contiene già /en/, non processarlo di nuovo
        if ( false !== strpos( $url, '/en/' ) ) {
            return $url;
        }
        
        // Aggiungi /en/ se necessario
        return $this->add_en_prefix_to_url( $url );
    }

    /**
     * Avvia output buffer per correggere URL duplicati.
     *
     * @since 0.9.4
     */
    public function start_output_buffer() {
        $this->output_buffer->start_output_buffer();
    }

    /**
     * Termina output buffer.
     *
     * @since 0.9.4
     */
    public function end_output_buffer() {
        $this->output_buffer->end_output_buffer();
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
        return $this->output_buffer->fix_duplicate_urls_in_output( $html );
    }

    /**
     * Fix duplicate URLs in output.
     * 
     * @deprecated Use OutputBuffer::fix_duplicate_urls_in_output() instead.
     * Kept for backward compatibility.
     *
     * @since 0.9.4
     */
    protected function _fix_duplicate_urls_in_output_legacy( $html ) {
        // Pattern principale: trova href con URL duplicati
        // Esempio: href="http://domain/en/http://domain" o href="http://domain/en/http://domain/path"
        $html = preg_replace_callback(
            '#href=["\'](http[s]?://[^/]+)/en/http[s]?://([^"\']+)["\']#i',
            function( $matches ) {
                // $matches[1] = http://domain
                // $matches[2] = domain o domain/path o domain/en/path
                
                // Estrai il path dall'URL duplicato
                // Se $matches[2] è solo il dominio, usa /
                if ( ! strpos( $matches[2], '/' ) ) {
                    return 'href="' . $matches[1] . '/en/"';
                }
                
                // Se $matches[2] contiene un path, estrai solo la parte dopo il dominio
                if ( preg_match( '#^[^/]+(/.*)$#', $matches[2], $path_match ) ) {
                    $path = $path_match[1];
                    // Se il path contiene ancora un URL completo, estrai solo la parte finale
                    if ( preg_match( '#http[s]?://[^/]+(/.*)$#', $path, $final_path_match ) ) {
                        $path = $final_path_match[1];
                    }
                    return 'href="' . $matches[1] . $path . '"';
                }
                
                return $matches[0];
            },
            $html
        );
        
        // Pattern alternativo più aggressivo: qualsiasi href con /en/http://
        $html = preg_replace_callback(
            '#href=["\']([^"\']*)/en/http[s]?://([^"\']+)["\']#i',
            function( $matches ) {
                $bad_url = $matches[1] . '/en/' . $matches[2];
                
                // Estrai l'ultimo URL completo (dopo l'ultimo http:// o https://)
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
                    // Verifica che l'URL sia valido e costruisci l'URL corretto
                    if ( preg_match( '#^http[s]?://([^/]+)(/.*)$#', $clean_url, $url_match ) ) {
                        $domain = $url_match[1];
                        $path = $url_match[2];
                        // Se il path contiene ancora un URL completo, estrai solo la parte finale
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



