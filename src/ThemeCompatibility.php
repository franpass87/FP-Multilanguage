<?php
/**
 * Theme Compatibility for Language Switcher
 *
 * Auto-detects popular themes and integrates the language switcher automatically.
 *
 * @package FP_Multilanguage
 * @since 0.4.2
 * @since 0.10.0 Refactored to use modular components.
 */


namespace FP\Multilanguage;

use FP\Multilanguage\Core\ContainerAwareTrait;
use FP\Multilanguage\Theme\ThemeCssProvider;
use FP\Multilanguage\Theme\Compatibility\ThemeDetector;
use FP\Multilanguage\Theme\Compatibility\MenuLocationMapper;
use FP\Multilanguage\Theme\Compatibility\SwitcherMarkupGenerator;
use FP\Multilanguage\Theme\Compatibility\MenuIntegrator;
use FP\Multilanguage\Theme\Compatibility\SalientHandler;
use FP\Multilanguage\Theme\Compatibility\ThemeInfoProvider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages theme-specific integration for language switcher.
 *
 * @since 0.4.2
 * @since 0.10.0 Refactored to use modular components.
 */
class ThemeCompatibility {
	use ContainerAwareTrait;
    /**
     * Singleton instance.
     *
     * @var \FPML_Theme_Compatibility|null
     */
    protected static $instance = null;

    /**
     * Settings instance.
     *
     * @var \FPML_Settings
     */
    protected $settings;

    /**
     * Theme CSS provider instance.
     *
     * @since 0.10.0
     *
     * @var ThemeCssProvider
     */
    protected $css_provider;

    /**
     * Theme detector instance.
     *
     * @since 0.10.0
     *
     * @var ThemeDetector
     */
    protected $detector;

    /**
     * Menu location mapper instance.
     *
     * @since 0.10.0
     *
     * @var MenuLocationMapper
     */
    protected $location_mapper;

    /**
     * Switcher markup generator instance.
     *
     * @since 0.10.0
     *
     * @var SwitcherMarkupGenerator
     */
    protected $markup_generator;

    /**
     * Menu integrator instance.
     *
     * @since 0.10.0
     *
     * @var MenuIntegrator
     */
    protected $menu_integrator;

    /**
     * Salient handler instance.
     *
     * @since 0.10.0
     *
     * @var SalientHandler
     */
    protected $salient_handler;

    /**
     * Theme info provider instance.
     *
     * @since 0.10.0
     *
     * @var ThemeInfoProvider
     */
    protected $info_provider;

    /**
     * Retrieve singleton.
     *
     * @since 0.4.2
     *
     * @return \FPML_Theme_Compatibility
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
        $this->css_provider = new ThemeCssProvider();

        // Initialize detector
        $this->detector = new ThemeDetector();
        $this->location_mapper = new MenuLocationMapper( $this->detector->get_theme_slug() );
        $this->markup_generator = new SwitcherMarkupGenerator( $this->settings );
        $this->menu_integrator = new MenuIntegrator( $this->settings, $this->location_mapper, $this->markup_generator );
        $this->salient_handler = new SalientHandler( $this->location_mapper, $this->markup_generator );
        $this->info_provider = new ThemeInfoProvider( $this->detector, $this->location_mapper );

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
        add_filter( 'wp_nav_menu_items', array( $this->menu_integrator, 'add_switcher_to_menu' ), 10, 2 );

        if ( $this->detector->is_salient() ) {
            add_action( 'wp_enqueue_scripts', array( $this->salient_handler, 'enqueue_salient_switcher_script' ), 20 );
            add_action( 'nectar_hook_before_menu_items', array( $this->salient_handler, 'render_salient_switcher_seed' ), 5 );
        }

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
        return $this->menu_integrator->add_switcher_to_menu( $items, $args );
    }

    /**
     * Output a switcher placeholder for Salient when no menu is assigned.
     *
     * @since 0.9.0
     *
     * @return void
     */
    public function render_salient_switcher_seed() {
        $this->salient_handler->render_salient_switcher_seed();
    }

    /**
     * Get primary menu location for current theme.
     *
     * @since 0.4.2
     *
     * @return string
     */
    protected function get_primary_menu_location() {
        return $this->location_mapper->get_primary_menu_location();
    }

    /**
     * Generate language switcher markup.
     *
     * @since 0.9.0
     *
     * @return string
     */
    protected function get_switcher_markup() {
        return $this->markup_generator->get_switcher_markup();
    }

    /**
     * Enqueue custom script for Salient header integration.
     *
     * @since 0.9.0
     *
     * @return void
     */
    public function enqueue_salient_switcher_script() {
        $this->salient_handler->enqueue_salient_switcher_script();
    }

    /**
     * Add theme-specific CSS.
     *
     * @since 0.4.2
     *
     * @return void
     */
    public function add_theme_specific_css() {
        $css = $this->css_provider->get_css( $this->detector->get_theme_slug() );

        if ( ! empty( $css ) ) {
            echo '<style id="fpml-theme-compat-' . esc_attr( $this->detector->get_theme_slug() ) . '">' . "\n";
            echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo "\n" . '</style>' . "\n";
        }
    }

    /**
     * Get detected theme info.
     *
     * @since 0.4.2
     *
     * @return array
     */
    public function get_theme_info() {
        return $this->info_provider->get_theme_info();
    }

    /**
     * Check if current theme is explicitly supported.
     *
     * @since 0.4.2
     *
     * @return bool
     */
    protected function is_theme_supported() {
        return $this->location_mapper->is_theme_supported();
    }
}
