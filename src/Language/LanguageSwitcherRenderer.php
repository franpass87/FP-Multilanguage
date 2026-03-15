<?php
/**
 * Language switcher renderer - Handles rendering of language switcher shortcode.
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
 * Renders language switcher UI elements.
 *
 * @since 0.10.0
 */
class LanguageSwitcherRenderer {
    /**
     * Language resolver instance.
     *
     * @var LanguageResolver
     */
    protected $resolver;

    /**
     * Constructor.
     *
     * @param LanguageResolver $resolver Language resolver instance.
     */
    public function __construct( LanguageResolver $resolver ) {
        $this->resolver = $resolver;
    }

    /**
     * Get the configured source language code.
     *
     * @return string
     */
    protected function get_source_language(): string {
        $manager = function_exists( 'fpml_get_language_manager' ) ? fpml_get_language_manager() : null;
        if ( $manager && method_exists( $manager, 'get_source_language' ) ) {
            return (string) $manager->get_source_language();
        }
        $settings = function_exists( 'fpml_get_settings' ) ? fpml_get_settings() : null;
        if ( $settings ) {
            return (string) $settings->get( 'source_language', 'it' );
        }
        return 'it';
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
        $atts = shortcode_atts(
            array(
                'style'      => 'auto',
                'show_flags' => '0',
            ),
            $atts,
            'fp_lang_switcher'
        );

        $show_flags = in_array( (string) $atts['show_flags'], array( '1', 'true', 'yes' ), true );
        $current    = $this->resolver->get_current_language();

        $language_manager    = function_exists( 'fpml_get_language_manager' ) ? fpml_get_language_manager() : null;
        $enabled_languages   = $language_manager ? $language_manager->get_enabled_languages() : array();
        $available_languages = $language_manager ? $language_manager->get_all_languages() : array();

        if ( ! is_array( $enabled_languages ) ) {
            $enabled_languages = array();
        }
        if ( ! is_array( $available_languages ) ) {
            $available_languages = array();
        }

        $source_lang = $this->get_source_language();
        $source_name = isset( $available_languages[ $source_lang ]['name'] )
            ? $available_languages[ $source_lang ]['name']
            : strtoupper( $source_lang );

        $languages = array(
            $source_lang => array(
                'label' => $source_name,
                'url'   => $this->get_url_for_language( $source_lang ),
            ),
        );

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

        $total_languages = count( $languages );

        if ( $total_languages <= 1 ) {
            return '';
        }

        $style = $atts['style'];
        if ( 'auto' === $style ) {
            $style = ( $total_languages <= 2 ) ? 'inline' : 'dropdown';
        }

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

            $lang_label = esc_html( $language['label'] );
            if ( $show_flags ) {
                $flag = $this->maybe_prefix_flag( $code );
                // Always include a visually-hidden language name for screen readers
                $label = $flag . '<span class="screen-reader-text">' . $lang_label . '</span>';
            } else {
                $label = $lang_label;
            }

            $items[] = sprintf(
                '<a class="%1$s" href="%2$s" rel="nofollow" aria-label="%3$s">%4$s</a>',
                esc_attr( implode( ' ', $classes ) ),
                esc_url( $language['url'] ),
                esc_attr( $language['label'] ),
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
            wp_register_script( 'fpml-switcher-dropdown', false, array(), FPML_PLUGIN_VERSION, true );
        }
        if ( ! wp_script_is( 'fpml-switcher-dropdown', 'enqueued' ) ) {
            wp_enqueue_script( 'fpml-switcher-dropdown' );
            wp_add_inline_script(
                'fpml-switcher-dropdown',
                "document.addEventListener('change',function(e){var el=e.target;if(!el.classList.contains('fpml-switcher__select')){return;}var url=el.options[el.selectedIndex].getAttribute('data-url');if(url){window.location.href=url;}});"
            );
        }

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
        $language_manager = function_exists( 'fpml_get_language_manager' ) ? fpml_get_language_manager() : null;
        $all_languages    = $language_manager ? $language_manager->get_all_languages() : array();
        $source_lang      = $this->get_source_language();

        $flags = array();

        foreach ( $all_languages as $lang_code => $lang_info ) {
            if ( isset( $lang_info['flag'] ) ) {
                $flags[ $lang_code ] = $lang_info['flag'];
            }
        }

        // Ensure source language has a fallback flag entry if not already set via lang info.
        if ( ! isset( $flags[ $source_lang ] ) ) {
            $flags[ $source_lang ] = '';
        }

        if ( ! isset( $flags[ $code ] ) ) {
            // No flag configured: return empty so the caller falls back to the language name
            return '';
        }

        $emoji = $flags[ $code ];

        // Keep native emoji characters to avoid remote image fallback issues.
        $emoji_html = esc_html( $emoji );

        // aria-hidden because the parent <a> already carries aria-label with the language name
        return sprintf( '<span class="fpml-switcher__flag" aria-hidden="true">%s</span>', $emoji_html );
    }

    /**
     * Get URL for a language, preserving the current page context.
     *
     * @since 0.10.0
     *
     * @param string $lang Language code.
     * @return string
     */
    protected function get_url_for_language( $lang ) {
        $language_manager  = function_exists( 'fpml_get_language_manager' ) ? fpml_get_language_manager() : null;
        $enabled_languages = $language_manager ? $language_manager->get_enabled_languages() : array();
        $source_lang       = $this->get_source_language();
        $lang              = strtolower( $lang );

        if ( ! in_array( $lang, $enabled_languages, true ) && $lang !== $source_lang ) {
            $lang = $source_lang;
        }

        // Delegate to FPML_Language if available — it knows the current post/term context
        $language_instance = function_exists( 'fpml_get_language' ) ? fpml_get_language() : null;
        if ( $language_instance && method_exists( $language_instance, 'get_url_for_language' ) ) {
            $url = $language_instance->get_url_for_language( $lang );
            if ( ! empty( $url ) ) {
                return $url;
            }
        }

        // Fallback: source → homepage, target → /lang/ prefix
        if ( function_exists( 'fpml_is_target_language' ) && fpml_is_target_language( $lang ) ) {
            $lang_info = $language_manager ? $language_manager->get_language_info( $lang ) : null;
            $lang_slug = $lang_info ? trim( $lang_info['slug'], '/' ) : $lang;
            return home_url( '/' . $lang_slug . '/' );
        }

        return home_url( '/' );
    }
}
















