<?php
/**
 * Hook manager - Handles registration of WordPress hooks.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Core;

use FP\Multilanguage\Core\ContainerAwareTrait;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages registration of WordPress hooks and actions.
 *
 * @since 0.10.0
 */
class HookManager {
    use ContainerAwareTrait;
    /**
     * Plugin instance.
     *
     * @var Plugin
     */
    protected $plugin;

    /**
     * Whether the plugin is running in assisted mode.
     *
     * @var bool
     */
    protected $assisted_mode = false;

    /**
     * Constructor.
     *
     * @param Plugin $plugin Plugin instance.
     * @param bool   $assisted_mode Assisted mode flag.
     */
    public function __construct( Plugin $plugin, $assisted_mode = false ) {
        $this->plugin = $plugin;
        $this->assisted_mode = $assisted_mode;
    }

    /**
     * Define hooks and bootstrap classes.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function define_hooks() {
        add_action( 'init', array( $this->plugin, 'load_textdomain' ) );

        // Classi base
        // Initialize settings and logger (they will be accessed via container when needed)
        $container = $this->getContainer();
        if ( $container ) {
            $container->has( 'options' ) && $container->get( 'options' );
            $container->has( 'logger' ) && $container->get( 'logger' );
        } else {
            \FPML_Settings::instance();
            \FPML_fpml_get_logger();
        }
        fpml_get_glossary();
        \FPML_Strings_Override::instance();
        \FPML_Strings_Scanner::instance();
        ( function_exists( 'fpml_get_export_import' ) ? fpml_get_export_import() : \FPML_Export_Import::instance() );

        if ( class_exists( '\FPML_Webhooks' ) ) {
            \FPML_Webhooks::instance();
        }

        // Fase 1: Classi core
        if ( ! $this->assisted_mode ) {
            ( function_exists( 'fpml_get_rewrites' ) ? fpml_get_rewrites() : \FPML_Rewrites::instance() );
            ( function_exists( 'fpml_get_language' ) ? fpml_get_language() : \FPML_Language::instance() );
            ( function_exists( 'fpml_get_content_diff' ) ? fpml_get_content_diff() : \FPML_Content_Diff::instance() );
            \FPML_fpml_get_processor();
            ( function_exists( 'fpml_get_menu_sync' ) ? fpml_get_menu_sync() : \FPML_Menu_Sync::instance() );
            \FPML_Media_Front::instance();
            ( function_exists( 'fpml_get_seo' ) ? fpml_get_seo() : \FPML_SEO::instance() );
        }
        
        if ( class_exists( '\FPML_Theme_Compatibility' ) ) {
            \FPML_Theme_Compatibility::instance();
        }
        
        // Fase 2: Features che dipendono dalle classi core
        if ( class_exists( '\FPML_Auto_Translate' ) ) {
            \FPML_Auto_Translate::instance();
        }
        
        if ( class_exists( '\FPML_Auto_Detection' ) ) {
            \FPML_Auto_Detection::instance();
            add_action( '\FPML_reindex_post_type', array( $this->plugin, 'reindex_post_type' ), 10, 1 );
            add_action( '\FPML_reindex_taxonomy', array( $this->plugin, 'reindex_taxonomy' ), 10, 1 );
        }

        // Fase 3: Features opzionali
        if ( class_exists( '\FPML_SEO_Optimizer' ) ) {
            \FPML_SEO_Optimizer::instance();
        }

        if ( class_exists( '\FPML_Provider_Fallback' ) ) {
            \FPML_Provider_Fallback::instance();
        }

        if ( class_exists( '\FPML_Auto_Relink' ) ) {
            \FPML_Auto_Relink::instance();
        }

        if ( class_exists( '\FPML_Dashboard_Widget' ) ) {
            \FPML_Dashboard_Widget::instance();
        }

        if ( class_exists( '\FPML_Rush_Mode' ) ) {
            \FPML_Rush_Mode::instance();
        }

        if ( class_exists( '\FPML_Featured_Image_Sync' ) ) {
            \FPML_Featured_Image_Sync::instance();
        }

        if ( class_exists( '\FPML_ACF_Support' ) ) {
            \FPML_ACF_Support::instance();
        }

        // Fase 5: Hook per gestione contenuti (now using dedicated hook handlers)
        $container = $this->getContainer();
        if ( ! $this->assisted_mode ) {
            // Use dedicated hook handlers if available
            if ( $container ) {
                $post_hooks = $container->has( 'hooks.post' ) ? $container->get( 'hooks.post' ) : null;
                $term_hooks = $container->has( 'hooks.term' ) ? $container->get( 'hooks.term' ) : null;
                $comment_hooks = $container->has( 'hooks.comment' ) ? $container->get( 'hooks.comment' ) : null;

                if ( $post_hooks ) {
                    $post_hooks->register();
                }
                if ( $term_hooks ) {
                    $term_hooks->register();
                }
                if ( $comment_hooks ) {
                    $comment_hooks->register();
                }
            }

            // Fallback to old Plugin hooks for backward compatibility
            if ( ! $container || ( ! $container->has( 'hooks.post' ) && ! $container->has( 'hooks.term' ) ) ) {
                add_action( 'all', array( $this->plugin, 'handle_all_hooks' ), -99999, 10 );
                add_action( 'on_publish', array( $this->plugin, 'handle_on_publish' ), -9999, 1 );
                add_action( 'publish_post', array( $this->plugin, 'handle_publish_post' ), 1, 1 );
                add_action( 'publish_page', array( $this->plugin, 'handle_publish_post' ), 1, 1 );
                add_action( 'save_post', array( $this->plugin, 'handle_save_post' ), 999, 3 );
                add_action( 'created_term', array( $this->plugin, 'handle_created_term' ), 10, 3 );
                add_action( 'edited_term', array( $this->plugin, 'handle_edited_term' ), 10, 3 );
                add_action( 'before_delete_post', array( $this->plugin, 'handle_delete_post' ), 10, 1 );
                add_action( 'delete_term', array( $this->plugin, 'handle_delete_term' ), 10, 3 );
                add_action( 'fpml_after_translation_saved', array( $this->plugin, 'enqueue_jobs_after_translation' ), 10, 2 );
                add_action( 'add_attachment', array( $this->plugin, 'handle_add_attachment' ), 10, 1 );
                add_action( 'edit_attachment', array( $this->plugin, 'handle_edit_attachment' ), 10, 1 );
                add_action( 'comment_post', array( $this->plugin, 'handle_comment_post' ), 10, 3 );
                add_action( 'edit_comment', array( $this->plugin, 'handle_edit_comment' ), 10, 1 );
            }

            // Widget hooks
            $widget_hooks = $container && $container->has( 'hooks.widget' ) ? $container->get( 'hooks.widget' ) : null;
            if ( $widget_hooks ) {
                $widget_hooks->register();
            } else {
                // Fallback to old Plugin hook
                add_filter( 'widget_update_callback', array( $this->plugin, 'handle_widget_update' ), 10, 4 );
            }

            // Attachment hooks
            $attachment_hooks = $container && $container->has( 'hooks.attachment' ) ? $container->get( 'hooks.attachment' ) : null;
            if ( $attachment_hooks ) {
                $attachment_hooks->register(); // register_hooks() also works via BaseHookHandler alias
            } else {
                // Fallback to old Plugin hooks
                add_action( 'add_attachment', array( $this->plugin, 'handle_add_attachment' ), 10, 1 );
                add_action( 'edit_attachment', array( $this->plugin, 'handle_edit_attachment' ), 10, 1 );
            }
        }
    }
}
















