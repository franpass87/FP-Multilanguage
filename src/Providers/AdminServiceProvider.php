<?php
/**
 * Admin Service Provider.
 *
 * Registers admin interface services and hooks.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Providers;

use FP\Multilanguage\Kernel\Container;
use FP\Multilanguage\Kernel\ServiceProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin service provider.
 *
 * @since 1.0.0
 */
class AdminServiceProvider implements ServiceProvider {
	/**
	 * Register services with the container.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Admin - Only register if in admin context
		if ( ! is_admin() ) {
			return;
		}

		// Admin main class
		$container->bind( 'admin', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Admin\Admin' ) ) {
				return \FP\Multilanguage\Admin\Admin::instance();
			}
			return null;
		}, true );

		// Admin Page Renderer
		$container->bind( 'admin.page_renderer', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Admin\Pages\PageRenderer' ) ) {
				return new \FP\Multilanguage\Admin\Pages\PageRenderer();
			}
			// Fallback to old location for backward compatibility
			if ( class_exists( '\FP\Multilanguage\Admin\PageRenderer' ) ) {
				return new \FP\Multilanguage\Admin\PageRenderer();
			}
			return null;
		}, true );

		// Admin AJAX Handlers
		$container->bind( 'admin.ajax_handlers', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Admin\Ajax\AjaxHandlers' ) ) {
				return new \FP\Multilanguage\Admin\Ajax\AjaxHandlers();
			}
			// Fallback to old location for backward compatibility
			if ( class_exists( '\FP\Multilanguage\Admin\AjaxHandlers' ) ) {
				$logger              = $c->has( 'logger' ) ? $c->get( 'logger' ) : null;
				$queue               = $c->has( 'queue' ) ? $c->get( 'queue' ) : null;
				$translation_manager = $c->has( 'translation.manager' ) ? $c->get( 'translation.manager' ) : null;
				return new \FP\Multilanguage\Admin\AjaxHandlers( $logger, $queue, $translation_manager );
			}
			return null;
		}, true );

		// Admin Post Handlers
		$container->bind( 'admin.post_handlers', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Admin\PostHandlers' ) ) {
				$logger = $c->has( 'logger' ) ? $c->get( 'logger' ) : null;
				$options = $c->has( 'options' ) ? $c->get( 'options' ) : null;
				return new \FP\Multilanguage\Admin\PostHandlers( $logger, $options );
			}
			return null;
		}, true );

		// Admin Nonce Manager
		$container->bind( 'admin.nonce_manager', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Admin\NonceManager' ) ) {
				return new \FP\Multilanguage\Admin\NonceManager();
			}
			return null;
		}, true );

		// Bulk Translator
		$container->bind( 'admin.bulk_translator', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Admin\BulkTranslator' ) ) {
				return \FP\Multilanguage\Admin\BulkTranslator::instance();
			}
			return null;
		}, true );

		// Preview Inline
		$container->bind( 'admin.preview_inline', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Admin\PreviewInline' ) ) {
				return \FP\Multilanguage\Admin\PreviewInline::instance();
			}
			return null;
		}, true );

		// Translation History UI
		$container->bind( 'admin.translation_history_ui', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Admin\TranslationHistoryUI' ) ) {
				return \FP\Multilanguage\Admin\TranslationHistoryUI::instance();
			}
			return null;
		}, true );

		// Translation Metabox
		$container->bind( 'admin.translation_metabox', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Admin\TranslationMetabox' ) ) {
				return \FP\Multilanguage\Admin\TranslationMetabox::instance();
			}
			return null;
		}, true );

		// Analytics Dashboard
		$container->bind( 'admin.analytics_dashboard', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Analytics\Dashboard' ) ) {
				return \FP\Multilanguage\Analytics\Dashboard::instance();
			}
			return null;
		}, true );

		// Post List Column
		$container->bind( 'admin.post_list_column', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Admin\PostListColumn' ) ) {
				return \FP\Multilanguage\Admin\PostListColumn::instance();
			}
			return null;
		}, true );

		// Admin Bar Switcher (frontend + admin)
		$container->bind( 'admin.bar_switcher', function( Container $c ) {
			if ( class_exists( '\FP\Multilanguage\Admin\AdminBarSwitcher' ) ) {
				return \FP\Multilanguage\Admin\AdminBarSwitcher::instance();
			}
			return null;
		}, true );
	}

	/**
	 * Boot services after all providers have registered.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// wp_doing_ajax() è true su admin-ajax.php, che è un contesto admin ma is_admin() lo rileva correttamente.
		// Tuttavia, is_admin() potrebbe essere false in alcuni contesti REST/cron; usiamo il check
		// più ampio per garantire che gli AJAX handler vengano sempre registrati.
		$is_admin_context = is_admin() || wp_doing_ajax();

		if ( ! $is_admin_context ) {
			return;
		}

		// Force instantiation so constructors register their hooks (admin_menu, add_meta_boxes, etc.)
		$services_to_boot = array(
			'admin',
			'admin.bulk_translator',
			'admin.translation_metabox',
			'admin.ajax_handlers',
			'admin.post_handlers',
			'admin.nonce_manager',
			'admin.preview_inline',
			'admin.translation_history_ui',
			'admin.analytics_dashboard',
			'admin.post_list_column',
			'admin.bar_switcher',
		);
		foreach ( $services_to_boot as $service_id ) {
			if ( $container->has( $service_id ) ) {
				$container->get( $service_id );
			}
		}

		// Enqueue admin scripts
		add_action( 'admin_enqueue_scripts', function( $hook ) use ( $container ) {
			$admin = $container->has( 'admin' ) ? $container->get( 'admin' ) : null;
			if ( $admin && method_exists( $admin, 'enqueue_admin_scripts' ) ) {
				$admin->enqueue_admin_scripts( $hook );
			}
		} );

		// Admin hooks are also registered in Admin::__construct() for backward compatibility
		// This ensures they work even if Admin is instantiated directly
	}

	/**
	 * Get list of service IDs this provider registers.
	 *
	 * @return array<string> Service IDs.
	 */
	public function provides(): array {
		return array(
			'admin',
			'admin.page_renderer',
			'admin.ajax_handlers',
			'admin.post_handlers',
			'admin.nonce_manager',
			'admin.bulk_translator',
			'admin.preview_inline',
			'admin.translation_history_ui',
			'admin.translation_metabox',
			'admin.analytics_dashboard',
			'admin.post_list_column',
			'admin.bar_switcher',
		);
	}
}


