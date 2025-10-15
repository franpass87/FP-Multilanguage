<?php
/**
 * Theme Compatibility for Language Switcher
 *
 * Auto-detects popular themes and integrates the language switcher automatically.
 *
 * @package FP_Multilanguage
 * @since 0.4.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages theme-specific integration for language switcher.
 *
 * @since 0.4.2
 */
class FPML_Theme_Compatibility {
    /**
     * Singleton instance.
     *
     * @var FPML_Theme_Compatibility|null
     */
    protected static $instance = null;

    /**
     * Current theme slug.
     *
     * @var string
     */
    protected $theme_slug = '';

    /**
     * Current theme name.
     *
     * @var string
     */
    protected $theme_name = '';

    /**
     * Settings instance.
     *
     * @var FPML_Settings
     */
    protected $settings;

    /**
     * Retrieve singleton.
     *
     * @since 0.4.2
     *
     * @return FPML_Theme_Compatibility
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
        $this->settings = FPML_Settings::instance();

        // Detect theme - get_template() returns parent theme for child themes
        // This ensures child themes automatically inherit parent theme configuration
        $theme = wp_get_theme();
        $this->theme_slug = strtolower( $theme->get_template() );
        $this->theme_name = $theme->get( 'Name' );

        // Check if auto-integration is enabled
        if ( ! $this->is_auto_integration_enabled() ) {
            return;
        }

        // Initialize theme-specific integration
        $this->init_theme_integration();
    }

    /**
     * Check if auto-integration is enabled.
     *
     * @since 0.4.2
     *
     * @return bool
     */
    protected function is_auto_integration_enabled() {
        return (bool) $this->settings->get( 'auto_integrate_menu_switcher', true );
    }

    /**
     * Initialize theme-specific integration.
     *
     * @since 0.4.2
     *
     * @return void
     */
    protected function init_theme_integration() {
        // Add menu integration
        add_filter( 'wp_nav_menu_items', array( $this, 'add_switcher_to_menu' ), 10, 2 );

        // Add theme-specific CSS
        add_action( 'wp_head', array( $this, 'add_theme_specific_css' ), 999 );
    }

    /**
     * Add language switcher to navigation menu.
     *
     * @since 0.4.2
     *
     * @param string $items Menu items HTML.
     * @param object $args  Menu arguments.
     *
     * @return string
     */
    public function add_switcher_to_menu( $items, $args ) {
        $location = $this->get_primary_menu_location();

        // Check if this is the primary menu
        if ( empty( $location ) || $args->theme_location !== $location ) {
            return $items;
        }

        // Get switcher style from settings
        $style = $this->settings->get( 'menu_switcher_style', 'inline' );
        $show_flags = $this->settings->get( 'menu_switcher_show_flags', true );

        // Generate shortcode
        $shortcode = sprintf(
            '[fp_lang_switcher style="%s" show_flags="%s"]',
            esc_attr( $style ),
            $show_flags ? '1' : '0'
        );

        $switcher = do_shortcode( $shortcode );

        // Get position preference
        $position = $this->settings->get( 'menu_switcher_position', 'end' );

        $switcher_html = '<li class="menu-item menu-item-language-switcher fpml-auto-integrated">' . $switcher . '</li>';

        if ( 'start' === $position ) {
            return $switcher_html . $items;
        }

        return $items . $switcher_html;
    }

    /**
     * Get primary menu location for current theme.
     *
     * @since 0.4.2
     *
     * @return string
     */
    protected function get_primary_menu_location() {
        // Note: Child themes automatically inherit parent theme location
        // because $this->theme_slug uses get_template() which returns parent theme.
        $locations = array(
            'salient'           => 'top_nav',
            'astra'             => 'primary',
            'generatepress'     => 'primary',
            'oceanwp'           => 'main_menu',
            'neve'              => 'primary',
            'kadence'           => 'primary',
            'blocksy'           => 'primary',
            'hello-elementor'   => 'primary',
            'storefront'        => 'primary',
            'twentytwentyfour'  => 'primary',
            'twentytwentythree' => 'primary',
            'twentytwentytwo'   => 'primary',
            'twentytwentyone'   => 'primary',
            'divi'              => 'primary-menu',
            'avada'             => 'main_navigation',
            'enfold'            => 'avia',
            'flatsome'          => 'primary',
            'bridge'            => 'main-menu',
            'the7'              => 'primary',
        );

        return isset( $locations[ $this->theme_slug ] ) ? $locations[ $this->theme_slug ] : 'primary';
    }

    /**
     * Add theme-specific CSS.
     *
     * @since 0.4.2
     *
     * @return void
     */
    public function add_theme_specific_css() {
        $method = 'get_' . str_replace( '-', '_', $this->theme_slug ) . '_css';

        if ( method_exists( $this, $method ) ) {
            $css = call_user_func( array( $this, $method ) );
        } else {
            $css = $this->get_default_css();
        }

        if ( ! empty( $css ) ) {
            echo '<style id="fpml-theme-compat-' . esc_attr( $this->theme_slug ) . '">' . "\n";
            echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo "\n" . '</style>' . "\n";
        }
    }

    /**
     * Get default CSS for unknown themes.
     *
     * @since 0.4.2
     *
     * @return string
     */
    protected function get_default_css() {
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
     * @since 0.4.2
     *
     * @return string
     */
    protected function get_salient_css() {
        return '
            /* Desktop menu */
            #header-outer #top nav > ul > li.menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
                padding: 0 15px;
            }
            
            #header-outer .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher {
                margin: 0;
                line-height: normal;
            }
            
            #header-outer .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher--inline {
                display: inline-flex;
                align-items: center;
            }
            
            #header-outer .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                font-size: inherit;
                font-family: inherit;
                font-weight: inherit;
                letter-spacing: inherit;
                text-transform: inherit;
                color: inherit;
                padding: 6px 10px;
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
            
            #header-outer .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__separator {
                color: inherit;
                opacity: 0.5;
            }
            
            #header-outer .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__flag {
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
                    padding: 8px 15px;
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
     * @since 0.4.2
     *
     * @return string
     */
    protected function get_astra_css() {
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
     * @since 0.4.2
     *
     * @return string
     */
    protected function get_generatepress_css() {
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
     * @since 0.4.2
     *
     * @return string
     */
    protected function get_oceanwp_css() {
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
     * @since 0.4.2
     *
     * @return string
     */
    protected function get_kadence_css() {
        return '
            .primary-navigation .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .primary-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                font-family: inherit;
                padding: 6px 12px;
            }
        ';
    }

    /**
     * Get CSS for Neve theme.
     *
     * @since 0.4.2
     *
     * @return string
     */
    protected function get_neve_css() {
        return '
            .primary-menu-ul .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .primary-menu-ul .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                font-family: inherit;
                padding: 6px 12px;
            }
            
            .primary-menu-ul .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item:hover {
                opacity: 0.7;
            }
        ';
    }

    /**
     * Get CSS for Blocksy theme.
     *
     * @since 0.4.2
     *
     * @return string
     */
    protected function get_blocksy_css() {
        return '
            .ct-header .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .ct-header .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                font-family: inherit;
                padding: 6px 12px;
            }
            
            .ct-header .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item:hover {
                opacity: 0.7;
            }
        ';
    }

    /**
     * Get CSS for Divi theme.
     *
     * @since 0.4.2
     *
     * @return string
     */
    protected function get_divi_css() {
        return '
            #et-top-navigation .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            #et-top-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                font-family: inherit;
                padding: 6px 12px;
            }
            
            #et-top-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item:hover {
                opacity: 0.7;
            }
            
            @media (max-width: 980px) {
                #mobile_menu .menu-item-language-switcher.fpml-auto-integrated {
                    text-align: center;
                    padding: 10px 20px;
                }
            }
        ';
    }

    /**
     * Get CSS for Avada theme.
     *
     * @since 0.4.2
     *
     * @return string
     */
    protected function get_avada_css() {
        return '
            .fusion-main-menu .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .fusion-main-menu .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                font-family: inherit;
                padding: 6px 12px;
            }
            
            .fusion-main-menu .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item:hover {
                opacity: 0.7;
            }
            
            @media (max-width: 800px) {
                .fusion-mobile-menu-icons .menu-item-language-switcher.fpml-auto-integrated {
                    text-align: center;
                    padding: 10px 20px;
                }
            }
        ';
    }

    /**
     * Get CSS for Enfold theme.
     *
     * @since 0.4.2
     *
     * @return string
     */
    protected function get_enfold_css() {
        return '
            .avia-menu .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .avia-menu .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                font-family: inherit;
                padding: 6px 12px;
            }
            
            .avia-menu .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item:hover {
                opacity: 0.7;
            }
        ';
    }

    /**
     * Get CSS for Flatsome theme.
     *
     * @since 0.4.2
     *
     * @return string
     */
    protected function get_flatsome_css() {
        return '
            .header-nav .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .header-nav .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                font-family: inherit;
                padding: 6px 12px;
            }
            
            .header-nav .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item:hover {
                opacity: 0.7;
            }
        ';
    }

    /**
     * Get CSS for The7 theme.
     *
     * @since 0.4.2
     *
     * @return string
     */
    protected function get_the7_css() {
        return '
            .dt-primary-nav .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .dt-primary-nav .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                font-family: inherit;
                padding: 6px 12px;
            }
            
            .dt-primary-nav .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item:hover {
                opacity: 0.7;
            }
        ';
    }

    /**
     * Get CSS for Bridge theme.
     *
     * @since 0.4.2
     *
     * @return string
     */
    protected function get_bridge_css() {
        return '
            .main-menu .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .main-menu .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                font-family: inherit;
                padding: 6px 12px;
            }
            
            .main-menu .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item:hover {
                opacity: 0.7;
            }
        ';
    }

    /**
     * Get CSS for Hello Elementor theme.
     *
     * @since 0.4.2
     *
     * @return string
     */
    protected function get_hello_elementor_css() {
        return '
            .site-navigation .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .site-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                font-family: inherit;
                padding: 6px 12px;
            }
            
            .site-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item:hover {
                opacity: 0.7;
            }
        ';
    }

    /**
     * Get CSS for Storefront theme.
     *
     * @since 0.4.2
     *
     * @return string
     */
    protected function get_storefront_css() {
        return '
            .main-navigation .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .main-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                font-family: inherit;
                padding: 6px 12px;
            }
            
            .main-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item:hover {
                opacity: 0.7;
            }
        ';
    }

    /**
     * Get CSS for Twenty Twenty-Four theme.
     *
     * @since 0.4.2
     *
     * @return string
     */
    protected function get_twentytwentyfour_css() {
        return '
            .wp-block-navigation .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .wp-block-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                font-family: inherit;
                padding: 6px 12px;
            }
            
            .wp-block-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item:hover {
                opacity: 0.7;
            }
        ';
    }

    /**
     * Get CSS for Twenty Twenty-Three theme.
     *
     * @since 0.4.2
     *
     * @return string
     */
    protected function get_twentytwentythree_css() {
        return '
            .wp-block-navigation .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .wp-block-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                font-family: inherit;
                padding: 6px 12px;
            }
            
            .wp-block-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item:hover {
                opacity: 0.7;
            }
        ';
    }

    /**
     * Get CSS for Twenty Twenty-Two theme.
     *
     * @since 0.4.2
     *
     * @return string
     */
    protected function get_twentytwentytwo_css() {
        return '
            .wp-block-navigation .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .wp-block-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                font-family: inherit;
                padding: 6px 12px;
            }
            
            .wp-block-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item:hover {
                opacity: 0.7;
            }
        ';
    }

    /**
     * Get CSS for Twenty Twenty-One theme.
     *
     * @since 0.4.2
     *
     * @return string
     */
    protected function get_twentytwentyone_css() {
        return '
            .primary-navigation .menu-item-language-switcher.fpml-auto-integrated {
                display: inline-flex;
                align-items: center;
            }
            
            .primary-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item {
                color: inherit;
                font-size: inherit;
                font-family: inherit;
                padding: 6px 12px;
            }
            
            .primary-navigation .menu-item-language-switcher.fpml-auto-integrated .fpml-switcher__item:hover {
                opacity: 0.7;
            }
            
            @media (max-width: 481px) {
                .primary-navigation .menu-item-language-switcher.fpml-auto-integrated {
                    text-align: center;
                    padding: 10px 15px;
                }
            }
        ';
    }

    /**
     * Get detected theme info.
     *
     * @since 0.4.2
     *
     * @return array
     */
    public function get_theme_info() {
        return array(
            'slug'     => $this->theme_slug,
            'name'     => $this->theme_name,
            'location' => $this->get_primary_menu_location(),
            'supported' => $this->is_theme_supported(),
        );
    }

    /**
     * Check if current theme is explicitly supported.
     *
     * @since 0.4.2
     *
     * @return bool
     */
    protected function is_theme_supported() {
        $supported_themes = array(
            'salient',
            'astra',
            'generatepress',
            'oceanwp',
            'neve',
            'kadence',
            'blocksy',
            'hello-elementor',
            'storefront',
            'twentytwentyfour',
            'twentytwentythree',
            'twentytwentytwo',
            'twentytwentyone',
            'divi',
            'avada',
            'enfold',
            'flatsome',
            'the7',
            'bridge',
        );

        return in_array( $this->theme_slug, $supported_themes, true );
    }
}
