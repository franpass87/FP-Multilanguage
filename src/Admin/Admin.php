<?php
/**
 * Admin class - Main admin interface coordinator.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.3
 */

namespace FP\Multilanguage\Admin;

use FP\Multilanguage\Core\Container;
use FP\Multilanguage\Admin\Pages\PageRenderer;
use FP\Multilanguage\Admin\Ajax\AjaxHandlers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin class for WordPress admin interface.
 * 
 * Acts as a facade coordinating admin page rendering, AJAX handlers, post handlers, and nonce management.
 *
 * @since 0.4.3
 */
class Admin {
    const MENU_SLUG = 'fpml-settings';

    /**
     * Singleton instance.
     *
     * @var Admin|null
     */
    protected static $instance = null;

    /**
     * Page renderer instance.
     *
     * @since 0.10.0
     *
     * @var PageRenderer
     */
    protected $page_renderer;

    /**
     * AJAX handlers instance.
     *
     * @since 0.10.0
     *
     * @var AjaxHandlers
     */
    protected $ajax_handlers;

    /**
     * Post handlers instance.
     *
     * @since 0.10.0
     *
     * @var PostHandlers
     */
    protected $post_handlers;

    /**
     * Nonce manager instance.
     *
     * @since 0.10.0
     *
     * @var NonceManager
     */
    protected $nonce_manager;

    /**
     * Get singleton instance.
     *
     * @return Admin
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
    public function __construct() {
        // Initialize modules
        $this->page_renderer = new PageRenderer();
        $this->ajax_handlers = new AjaxHandlers();
        $this->post_handlers = new PostHandlers();
        $this->nonce_manager = new NonceManager();

        // Basic admin hooks
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        
        // AJAX handlers
        add_action( 'wp_ajax_fpml_refresh_nonce', array( $this->ajax_handlers, 'handle_refresh_nonce' ) );
        add_action( 'wp_ajax_fpml_reindex_batch_ajax', array( $this->ajax_handlers, 'handle_reindex_batch_ajax' ) );
        add_action( 'wp_ajax_fpml_cleanup_orphaned_pairs', array( $this->ajax_handlers, 'handle_cleanup_orphaned_pairs' ) );
        add_action( 'wp_ajax_fpml_trigger_detection', array( $this->ajax_handlers, 'handle_trigger_detection' ) );
        add_action( 'wp_ajax_fpml_bulk_translate', array( $this->ajax_handlers, 'handle_bulk_translate' ) );
        add_action( 'wp_ajax_fpml_bulk_regenerate', array( $this->ajax_handlers, 'handle_bulk_regenerate' ) );
        add_action( 'wp_ajax_fpml_bulk_sync', array( $this->ajax_handlers, 'handle_bulk_sync' ) );
        add_action( 'wp_ajax_fpml_translate_single', array( $this->ajax_handlers, 'handle_translate_single' ) );
        add_action( 'wp_ajax_fpml_translate_site_part', array( $this->ajax_handlers, 'handle_translate_site_part' ) );
        
        // Admin-post handlers
        add_action( 'admin_post_fpml_save_settings', array( $this->post_handlers, 'handle_save_settings' ) );
        add_action( 'admin_post_fpml_scan_strings', array( $this->post_handlers, 'handle_scan_strings' ) );
        add_action( 'admin_post_fpml_save_overrides', array( $this->post_handlers, 'handle_save_overrides' ) );
        add_action( 'admin_post_fpml_import_overrides', array( $this->post_handlers, 'handle_import_overrides' ) );
        add_action( 'admin_post_fpml_export_overrides', array( $this->post_handlers, 'handle_export_overrides' ) );
        add_action( 'admin_post_fpml_save_glossary', array( $this->post_handlers, 'handle_save_glossary' ) );
        add_action( 'admin_post_fpml_import_glossary', array( $this->post_handlers, 'handle_import_glossary' ) );
        add_action( 'admin_post_fpml_export_glossary', array( $this->post_handlers, 'handle_export_glossary' ) );
        add_action( 'admin_post_fpml_export_state', array( $this->post_handlers, 'handle_export_state' ) );
        add_action( 'admin_post_fpml_import_state', array( $this->post_handlers, 'handle_import_state' ) );
        add_action( 'admin_post_fpml_export_logs', array( $this->post_handlers, 'handle_export_logs' ) );
        add_action( 'admin_post_fpml_import_logs', array( $this->post_handlers, 'handle_import_logs' ) );
        add_action( 'admin_post_fpml_clear_sandbox', array( $this->post_handlers, 'handle_clear_sandbox' ) );
        
        // Nonce handling
        add_action( 'admin_init', array( $this->nonce_manager, 'handle_expired_nonce_redirect' ) );
        add_action( 'init', array( $this->nonce_manager, 'handle_expired_nonce_early' ), 1 );
        add_action( 'plugins_loaded', array( $this->nonce_manager, 'handle_expired_nonce_very_early' ), 1 );
        add_filter( 'wp_die_handler', array( $this->nonce_manager, 'custom_wp_die_handler' ) );
        add_filter( 'check_admin_referer', array( $this->nonce_manager, 'handle_admin_referer_check' ), 10, 2 );
    }
    
    /**
     * Add admin menu.
     *
     * @return void
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'FP Multilanguage', 'fp-multilanguage' ),
            __( 'FP Multilanguage', 'fp-multilanguage' ),
            'manage_options',
            self::MENU_SLUG,
            array( $this, 'render_admin_page' ),
            'dashicons-translation',
            30
        );
    }

    /**
     * Enqueue admin scripts.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( strpos( $hook, self::MENU_SLUG ) === false ) {
            return;
        }

        wp_enqueue_script( 'jquery' );
        wp_enqueue_style( 'fpml-admin', \FPML_PLUGIN_URL . 'assets/admin.css', array(), \FPML_PLUGIN_VERSION );
        wp_enqueue_script( 'fpml-admin', \FPML_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), \FPML_PLUGIN_VERSION, true );
    }

    /**
     * Render admin page.
     *
     * @return void
     */
    public function render_admin_page() {
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
        
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'FP Multilanguage', 'fp-multilanguage' ) . '</h1>';
        
        $this->page_renderer->render_tab_navigation( $current_tab );
        $this->page_renderer->render_tab_content( $current_tab );
        
        echo '</div>';
    }
}
