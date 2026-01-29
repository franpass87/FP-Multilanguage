<?php
/**
 * Navigation Menu Synchronization.
 *
 * Automatically duplicates and translates navigation menus from IT to EN:
 * - Creates EN menu counterparts
 * - Maps menu items (IT post → EN post)
 * - Translates custom menu labels
 * - Syncs menu locations
 *
 * @package FP_Multilanguage
 * @since 0.8.0
 * @since 0.10.0 Refactored to use modular components.
 */

namespace FP\Multilanguage;

use FP\Multilanguage\Core\ContainerAwareTrait;
use FP\Multilanguage\Foundation\Logger\LoggerInterface;
use FP\Multilanguage\Foundation\Options\SettingsAdapter;
use FP\Multilanguage\Menu\MenuSynchronizer;
use FP\Multilanguage\Menu\MenuItemManager;
use FP\Multilanguage\Menu\MenuLocationManager;
use FP\Multilanguage\Menu\MenuFilter;
use FP\Multilanguage\Menu\MenuAjax;
use FP\Multilanguage\Menu\MenuAdmin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menu synchronization class.
 *
 * @since 0.8.0
 * @since 0.10.0 Refactored to use modular components.
 */
class MenuSync {
	use ContainerAwareTrait;
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface|Logger
	 */
	protected $logger;

	/**
	 * Settings instance.
	 *
	 * @var Settings|SettingsAdapter
	 */
	protected $settings;

	/**
	 * Menu synchronizer instance.
	 *
	 * @since 0.10.0
	 *
	 * @var MenuSynchronizer
	 */
	protected MenuSynchronizer $synchronizer;

	/**
	 * Menu filter instance.
	 *
	 * @since 0.10.0
	 *
	 * @var MenuFilter
	 */
	protected MenuFilter $filter;

	/**
	 * Menu AJAX handler instance.
	 *
	 * @since 0.10.0
	 *
	 * @var MenuAjax
	 */
	protected MenuAjax $ajax;

	/**
	 * Menu admin instance.
	 *
	 * @since 0.10.0
	 *
	 * @var MenuAdmin
	 */
	protected MenuAdmin $admin;

	/**
	 * Get singleton instance (for backward compatibility).
	 *
	 * @deprecated 1.0.0 Use dependency injection via container instead
	 * @return self
	 */
	public static function instance() {
		_doing_it_wrong( 
			'FP\Multilanguage\MenuSync::instance()', 
			'MenuSync::instance() is deprecated. Use dependency injection via container instead.', 
			'1.0.0' 
		);
		
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 0.10.0
	 * @since 1.0.0 Now public to support dependency injection
	 *
	 * @param LoggerInterface|Logger|null $logger   Optional logger instance for DI.
	 * @param Settings|SettingsAdapter|null $settings Optional settings instance for DI.
	 */
	public function __construct( $logger = null, $settings = null ) {
		// Use injected dependencies or get from container/singleton
		if ( null === $logger ) {
			$container = $this->getContainer();
			$this->logger = $container && $container->has( 'logger' ) ? $container->get( 'logger' ) : fpml_get_logger();
		} else {
			$this->logger = $logger;
		}
		
		if ( null === $settings ) {
			$container = $this->getContainer();
			$this->settings = $container && $container->has( 'options' ) ? $container->get( 'options' ) : Settings::instance();
		} else {
			$this->settings = $settings;
		}
		
		// Initialize modules
		$item_manager = new MenuItemManager( $this->logger );
		$location_manager = new MenuLocationManager( $this->logger );
		$this->synchronizer = new MenuSynchronizer( $this->logger, $this->settings, $item_manager, $location_manager );
		$this->filter = new MenuFilter();
		$this->ajax = new MenuAjax( $this->synchronizer );
		$this->admin = new MenuAdmin();

		// Register hooks
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	protected function register_hooks(): void {
		// Auto-sync menu when updated
		add_action( 'wp_update_nav_menu', array( $this->synchronizer, 'auto_sync_menu' ), 10, 2 );

		// Sync when menu item is added
		add_action( 'wp_update_nav_menu_item', array( $this, 'sync_single_menu_item' ), 10, 3 );

		// Delete EN menu when IT menu is deleted
		add_action( 'delete_nav_menu', array( $this->synchronizer, 'handle_menu_deletion' ), 10, 1 );

		// Filter menu items on frontend to show correct language
		add_filter( 'wp_get_nav_menu_items', array( $this->filter, 'filter_menu_items_by_language' ), 10, 3 );

		// Admin notice
		add_action( 'admin_notices', array( $this->admin, 'menu_sync_notice' ) );

		// Add meta box in nav-menus screen
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_admin_scripts' ) );

		// AJAX handlers
		add_action( 'wp_ajax_fpml_sync_menu', array( $this->ajax, 'ajax_sync_menu' ) );
		add_action( 'wp_ajax_fpml_get_menu_status', array( $this->ajax, 'ajax_get_menu_status' ) );
	}

	/**
	 * Sync single menu item when it's updated.
	 *
	 * @param int   $menu_id         Menu ID.
	 * @param int   $menu_item_db_id Menu item ID.
	 * @param array $args            Menu item args.
	 *
	 * @return void
	 */
	public function sync_single_menu_item( int $menu_id, int $menu_item_db_id, array $args ): void { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		// Check if this menu has an EN counterpart
		$en_menu_id = get_term_meta( $menu_id, '_fpml_menu_en_id', true );

		if ( ! $en_menu_id ) {
			return;
		}

		// Re-sync entire menu (simpler than syncing single item)
		$this->synchronizer->sync_menu( $menu_id );
	}

	/**
	 * Sync a menu to create/update its English version.
	 * Public method for backward compatibility.
	 *
	 * @param int $source_menu_id IT menu ID.
	 * @return int|false EN menu ID or false on failure.
	 */
	public function sync_menu( int $source_menu_id ) {
		return $this->synchronizer->sync_menu( $source_menu_id );
	}
}
