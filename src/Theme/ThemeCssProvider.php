<?php
/**
 * Theme CSS provider - Provides CSS for different themes.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Theme;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides CSS styles for different WordPress themes.
 *
 * @since 0.10.0
 */
class ThemeCssProvider {
    /**
     * Get CSS for a specific theme.
     *
     * @since 0.10.0
     *
     * @param string $theme_slug Theme slug.
     * @return string CSS code.
     */
    public function get_css( $theme_slug ) {
        $method = 'get_' . str_replace( '-', '_', $theme_slug ) . '_css';
        
        if ( method_exists( $this, $method ) ) {
            return $this->$method();
        }
        
        return $this->get_default_css();
    }

    /**
     * Get default CSS for unknown themes.
     *
     * @since 0.10.0
     *
     * @return string
     */
    public function get_default_css() {
        return '
            .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
                padding: 0 10px;
            }
            
            .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher {
                margin: 0;
            }
            
            .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                font-size: inherit;
                font-family: inherit;
                font-weight: inherit;
                color: inherit;
            }
            
            .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item:hover {
                opacity: 0.7;
            }
        ';
    }

    /**
     * Get CSS for Salient theme.
     *
     * @since 0.10.0
     *
     * @return string
     */
    public function get_salient_css() {
        return '
            body.fpml-salient-enhanced {
                width: 100%;
            }
            body.fpml-salient-enhanced #ajax-content-wrap {
                margin-left: auto;
                margin-right: auto;
                width: 100%;
                max-width: 100%;
            }
            /* Desktop menu */
            #header-outer #top nav > ul > li.menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
                padding: 0 15px;
            }

            #header-outer .fpml-salient-switcher {
                display: inline-flex;
                align-items: center;
                margin-right: 10px;
                gap: 6px;
            }

            #header-outer .fpml-salient-switcher:last-of-type {
                margin-right: 0;
            }

            #header-outer .fpml-salient-switcher .fpml-switcher {
                margin: 0;
                line-height: normal;
            }

            #header-outer .fpml-salient-switcher .fpml-switcher--inline {
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }

            #header-outer .fpml-salient-switcher .fpml-switcher__item {
                font-size: inherit;
                font-family: inherit;
                font-weight: inherit;
                letter-spacing: inherit;
                text-transform: inherit;
                color: inherit;
                padding: 4px 8px;
            }
            
            #header-outer .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher {
                margin: 0;
                line-height: normal;
            }
            
            #header-outer .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher--inline {
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            
            #header-outer .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                font-size: inherit;
                font-family: inherit;
                font-weight: inherit;
                letter-spacing: inherit;
                text-transform: inherit;
                color: inherit;
                padding: 4px 8px;
            }
            
            #header-outer .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item:hover {
                background-color: transparent;
                opacity: 0.7;
                color: inherit;
            }
            
            #header-outer .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item--current {
                font-weight: 700;
                background-color: transparent;
                border: none;
                color: inherit;
            }
            
            #header-outer .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__separator,
            #header-outer .fpml-salient-switcher .fpml-switcher__separator {
                display: none;
            }
            
            #header-outer .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__flag,
            #header-outer .fpml-salient-switcher .fpml-switcher__flag {
                font-size: 18px;
            }

            #header-outer .fpml-salient-switcher .fpml-switcher__item:hover {
                background-color: transparent;
                opacity: 0.7;
                color: inherit;
            }

            #header-outer .fpml-salient-switcher .fpml-switcher__item--current {
                font-weight: 700;
                background-color: transparent;
                border: none;
                color: inherit;
            }

            #header-outer .fpml-salient-switcher .fpml-switcher__separator {
                color: inherit;
                opacity: 0.5;
            }

            #header-outer .fpml-salient-switcher .fpml-switcher__flag {
                font-size: 18px;
            }
            
            /* Sticky header */
            #header-outer.sticky-header .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                padding: 4px 8px;
            }
            
            /* Transparent header */
            body.transparent-header #header-outer:not(.scrolled-down) .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: #ffffff;
            }
            
            /* Mobile menu */
            @media only screen and (max-width: 999px) {
                #header-outer #mobile-menu .menu-item-language-switcher.fpml-auto-integrated {
                    display: block;
                    padding: 15px 20px;
                    border-bottom: 1px solid rgba(255,255,255,0.1);
                    text-align: center;
                }
                
                #header-outer #mobile-menu .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher {
                    justify-content: center;
                }
                
                #header-outer #mobile-menu .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                    font-size: 15px;
                    color: inherit;
                    padding: 8px 12px;
                }

                #header-outer .fpml-salient-switcher {
                    margin-right: 8px;
                    padding: 0;
                }

                #header-outer .fpml-salient-switcher .fpml-switcher__item {
                    font-size: 15px;
                    color: inherit;
                    padding: 8px 12px;
                }
                
                #header-outer #mobile-menu .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__flag {
                    font-size: 22px;
                }
            }
            
            /* Side header */
            #header-outer.side-header .menu-item-language-switcher.fpml-auto-integrated {
                text-align: center;
                padding: 20px 0;
                border-top: 1px solid rgba(255,255,255,0.1);
            }
        ';
    }

    /**
     * Get CSS for Astra theme.
     *
     * @since 0.10.0
     *
     * @return string
     */
    public function get_astra_css() {
        return '
            .main-header-menu .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .main-header-menu .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                font-family: inherit;
                padding: 6px 12px;
            }
            
            .main-header-menu .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item:hover {
                opacity: 0.7;
            }
            
            @media (max-width: 921px) {
                .main-header-menu .menu-item-language-switcher.fpml-auto-integrated {
                    padding: 10px 20px;
                    text-align: center;
                }
            }
        ';
    }

    /**
     * Get CSS for GeneratePress theme.
     *
     * @since 0.10.0
     *
     * @return string
     */
    public function get_generatepress_css() {
        return '
            .main-navigation .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .main-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                font-weight: inherit;
                padding: 6px 12px;
            }
            
            .main-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item:hover {
                opacity: 0.7;
            }
        ';
    }

    /**
     * Get CSS for OceanWP theme.
     *
     * @since 0.10.0
     *
     * @return string
     */
    public function get_oceanwp_css() {
        return '
            #site-navigation .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            #site-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                padding: 6px 12px;
            }
            
            #site-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item:hover {
                opacity: 0.7;
            }
        ';
    }

    /**
     * Get CSS for Kadence theme.
     *
     * @since 0.10.0
     *
     * @return string
     */
    public function get_kadence_css() {
        return '
            .header-navigation .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .header-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                padding: 6px 12px;
            }
        ';
    }

    /**
     * Get CSS for Neve theme.
     *
     * @since 0.10.0
     *
     * @return string
     */
    public function get_neve_css() {
        return '
            .nv-nav-wrap .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .nv-nav-wrap .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                padding: 6px 12px;
            }
        ';
    }

    /**
     * Get CSS for Blocksy theme.
     *
     * @since 0.10.0
     *
     * @return string
     */
    public function get_blocksy_css() {
        return '
            .ct-navbar .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .ct-navbar .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                padding: 6px 12px;
            }
        ';
    }

    /**
     * Get CSS for Divi theme.
     *
     * @since 0.10.0
     *
     * @return string
     */
    public function get_divi_css() {
        return '
            #et-top-navigation .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            #et-top-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                padding: 6px 12px;
            }
        ';
    }

    /**
     * Get CSS for Avada theme.
     *
     * @since 0.10.0
     *
     * @return string
     */
    public function get_avada_css() {
        return '
            .fusion-main-menu .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .fusion-main-menu .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                padding: 6px 12px;
            }
        ';
    }

    /**
     * Get CSS for Enfold theme.
     *
     * @since 0.10.0
     *
     * @return string
     */
    public function get_enfold_css() {
        return '
            .av-main-nav .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .av-main-nav .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                padding: 6px 12px;
            }
        ';
    }

    /**
     * Get CSS for Flatsome theme.
     *
     * @since 0.10.0
     *
     * @return string
     */
    public function get_flatsome_css() {
        return '
            .nav .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .nav .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                padding: 6px 12px;
            }
        ';
    }

    /**
     * Get CSS for The7 theme.
     *
     * @since 0.10.0
     *
     * @return string
     */
    public function get_the7_css() {
        return '
            .main-nav .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .main-nav .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                padding: 6px 12px;
            }
        ';
    }

    /**
     * Get CSS for Bridge theme.
     *
     * @since 0.10.0
     *
     * @return string
     */
    public function get_bridge_css() {
        return '
            .nav .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .nav .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                padding: 6px 12px;
            }
        ';
    }

    /**
     * Get CSS for Hello Elementor theme.
     *
     * @since 0.10.0
     *
     * @return string
     */
    public function get_hello_elementor_css() {
        return '
            .site-navigation .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .site-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                padding: 6px 12px;
            }
        ';
    }

    /**
     * Get CSS for Storefront theme.
     *
     * @since 0.10.0
     *
     * @return string
     */
    public function get_storefront_css() {
        return '
            .main-navigation .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .main-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                padding: 6px 12px;
            }
        ';
    }

    /**
     * Get CSS for Twenty Twenty-Four theme.
     *
     * @since 0.10.0
     *
     * @return string
     */
    public function get_twentytwentyfour_css() {
        return '
            .wp-block-navigation .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .wp-block-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                padding: 6px 12px;
            }
        ';
    }

    /**
     * Get CSS for Twenty Twenty-Three theme.
     *
     * @since 0.10.0
     *
     * @return string
     */
    public function get_twentytwentythree_css() {
        return '
            .wp-block-navigation .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .wp-block-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                padding: 6px 12px;
            }
        ';
    }

    /**
     * Get CSS for Twenty Twenty-Two theme.
     *
     * @since 0.10.0
     *
     * @return string
     */
    public function get_twentytwentytwo_css() {
        return '
            .wp-block-navigation .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .wp-block-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                padding: 6px 12px;
            }
        ';
    }

    /**
     * Get CSS for Twenty Twenty-One theme.
     *
     * @since 0.10.0
     *
     * @return string
     */
    public function get_twentytwentyone_css() {
        return '
            .primary-navigation .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .primary-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                padding: 6px 12px;
            }
        ';
    }
}
















