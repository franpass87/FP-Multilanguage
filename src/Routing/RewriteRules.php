<?php
/**
 * Rewrite rules - Handles registration of rewrite rules and query vars.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Frontend\Routing;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers rewrite rules and query variables for language routing.
 *
 * @since 0.10.0
 */
class RewriteRules {
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
     * Register rewrite rules for enabled languages.
     *
     * @since 0.2.0
     * @since 0.10.0 Updated to support multiple enabled languages dynamically.
     *
     * @return void
     */
    public function register_rewrites(): void {
        if ( ! function_exists( 'add_rewrite_rule' ) || ! function_exists( 'add_rewrite_tag' ) ) {
            return;
        }

        $routing_mode = $this->settings->get( 'routing_mode', 'segment' );
        
        if ( 'segment' !== $routing_mode ) {
            return;
        }

        $language_manager = fpml_get_language_manager();
        $enabled_languages = $language_manager->get_enabled_languages();
        $available_languages = $language_manager->get_all_languages();

        add_rewrite_tag( '%\FPML_path%', '(.+)' );

        if ( $this->settings->get( 'sitemap_en', true ) ) {
            foreach ( $enabled_languages as $lang ) {
                if ( isset( $available_languages[ $lang ] ) ) {
                    add_rewrite_rule( '^sitemap-' . $lang . '\.xml$', 'index.php?\FPML_sitemap=' . $lang, 'top' );
                }
            }
        }

        foreach ( $enabled_languages as $lang ) {
            if ( ! isset( $available_languages[ $lang ] ) ) {
                continue;
            }

            $lang_info = $available_languages[ $lang ];
            if ( ! is_array( $lang_info ) || empty( $lang_info['slug'] ) ) {
                continue;
            }
            $lang_slug = trim( $lang_info['slug'], '/' );

            add_rewrite_rule( '^' . $lang_slug . '/?$', 'index.php?\FPML_lang=' . $lang, 'top' );
            add_rewrite_rule( '^' . $lang_slug . '/(.+)/?$', 'index.php?\FPML_lang=' . $lang . '&\FPML_path=$matches[1]', 'top' );
        }
    }

    /**
     * Register query variables.
     *
     * @param array<string> $vars Existing query vars.
     *
     * @return array<string>
     */
    public function register_query_vars( array $vars ): array {
        $vars[] = '\FPML_lang';
        $vars[] = '\FPML_path';
        $vars[] = 'fpml_lang';
        $vars[] = 'fpml_path';
        $vars[] = '\FPML_sitemap';

        return $vars;
    }
}
















