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
     * Source language slug.
     */
    const SOURCE = 'it';

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

        $language_manager = fpml_get_language_manager();
        $enabled_languages = $language_manager->get_enabled_languages();
        $available_languages = $language_manager->get_all_languages();

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
        $language_manager = fpml_get_language_manager();
        $all_languages = $language_manager->get_all_languages();
        
        $flags = array(
            self::SOURCE => '🇮🇹',
        );
        
        foreach ( $all_languages as $lang_code => $lang_info ) {
            if ( isset( $lang_info['flag'] ) ) {
                $flags[ $lang_code ] = $lang_info['flag'];
            }
        }

        if ( ! isset( $flags[ $code ] ) ) {
            return '';
        }

        $emoji = $flags[ $code ];
        
        if ( function_exists( 'wp_staticize_emoji' ) ) {
            $emoji_html = wp_staticize_emoji( $emoji );
        } else {
            $emoji_html = esc_html( $emoji );
        }

        return sprintf( '<span class="fpml-switcher__flag" aria-hidden="true">%s</span>', $emoji_html );
    }

    /**
     * Get URL for a language.
     *
     * @since 0.10.0
     *
     * @param string $lang Language code.
     * @return string
     */
    protected function get_url_for_language( $lang ) {
        // This should delegate to Language class
        // For now, return simple URL
        $language_manager = fpml_get_language_manager();
        $enabled_languages = $language_manager->get_enabled_languages();
        
        $lang = strtolower( $lang );
        
        if ( ! in_array( $lang, $enabled_languages, true ) && $lang !== self::SOURCE ) {
            $lang = self::SOURCE;
        }

        if ( fpml_is_target_language( $lang ) ) {
            $lang_info = $language_manager->get_language_info( $lang );
            $lang_slug = $lang_info ? trim( $lang_info['slug'], '/' ) : 'en';
            return home_url( '/' . $lang_slug . '/' );
        }

        return home_url( '/' );
    }
}
















