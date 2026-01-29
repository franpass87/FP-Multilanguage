<?php
/**
 * WooCommerce Integration - Complete Support.
 *
 * Handles translation of:
 * - Product variations (size, color, etc) + variation descriptions
 * - Product attributes (global & custom)
 * - Product categories/tags taxonomy
 * - Product gallery alt text
 * - Product tabs (custom)
 * - Upsell/Cross-sell product mapping
 * - Downloadable product files
 * - External/Affiliate product data
 * - Grouped products
 *
 * @package FP_Multilanguage
 * @since 0.8.0
 * @updated 0.9.0 - Complete WooCommerce coverage
 * @since 0.10.0 Refactored to use modular components.
 */

namespace FP\Multilanguage\Integrations;

use FP\Multilanguage\Core\ContainerAwareTrait;
use FP\Multilanguage\Integrations\WooCommerce\WhitelistManager;
use FP\Multilanguage\Integrations\WooCommerce\VariationSynchronizer;
use FP\Multilanguage\Integrations\WooCommerce\GallerySynchronizer;
use FP\Multilanguage\Integrations\WooCommerce\AttributeSynchronizer;
use FP\Multilanguage\Integrations\WooCommerce\RelationSynchronizer;
use FP\Multilanguage\Integrations\WooCommerce\DownloadSynchronizer;
use FP\Multilanguage\Integrations\WooCommerce\TabSynchronizer;
use FP\Multilanguage\Integrations\WooCommerce\WooCommerceAdmin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce integration class.
 *
 * @since 0.8.0
 * @since 0.10.0 Refactored to use modular components.
 */
class WooCommerceSupport {
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
	 * @var \FP\Multilanguage\Logger|null
	 */
	protected $logger = null;

	/**
	 * Whitelist manager instance.
	 *
	 * @since 0.10.0
	 *
	 * @var WhitelistManager
	 */
	protected WhitelistManager $whitelist_manager;

	/**
	 * Variation synchronizer instance.
	 *
	 * @since 0.10.0
	 *
	 * @var VariationSynchronizer
	 */
	protected VariationSynchronizer $variation_synchronizer;

	/**
	 * Gallery synchronizer instance.
	 *
	 * @since 0.10.0
	 *
	 * @var GallerySynchronizer
	 */
	protected GallerySynchronizer $gallery_synchronizer;

	/**
	 * Attribute synchronizer instance.
	 *
	 * @since 0.10.0
	 *
	 * @var AttributeSynchronizer
	 */
	protected AttributeSynchronizer $attribute_synchronizer;

	/**
	 * Relation synchronizer instance.
	 *
	 * @since 0.10.0
	 *
	 * @var RelationSynchronizer
	 */
	protected RelationSynchronizer $relation_synchronizer;

	/**
	 * Download synchronizer instance.
	 *
	 * @since 0.10.0
	 *
	 * @var DownloadSynchronizer
	 */
	protected DownloadSynchronizer $download_synchronizer;

	/**
	 * Tab synchronizer instance.
	 *
	 * @since 0.10.0
	 *
	 * @var TabSynchronizer
	 */
	protected TabSynchronizer $tab_synchronizer;

	/**
	 * WooCommerce admin instance.
	 *
	 * @since 0.10.0
	 *
	 * @var WooCommerceAdmin
	 */
	protected WooCommerceAdmin $admin;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 0.10.0
	 */
	protected function __construct() {
		// Check if WooCommerce is active
		if ( ! $this->is_woocommerce_active() ) {
			return;
		}

		$container = $this->getContainer();
		$this->logger = $container && $container->has( 'logger' ) ? $container->get( 'logger' ) : ( class_exists( '\FP\Multilanguage\Logger' ) ? fpml_get_logger() : null );

		// Initialize modules
		$this->whitelist_manager = new WhitelistManager();
		$this->variation_synchronizer = new VariationSynchronizer( $this->logger );
		$this->gallery_synchronizer = new GallerySynchronizer( $this->logger );
		$this->attribute_synchronizer = new AttributeSynchronizer( $this->logger );
		$this->relation_synchronizer = new RelationSynchronizer( $this->logger );
		$this->download_synchronizer = new DownloadSynchronizer( $this->logger );
		$this->tab_synchronizer = new TabSynchronizer( $this->logger );
		$this->admin = new WooCommerceAdmin();

		// Register hooks
		$this->register_hooks();
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return bool
	 */
	protected function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' ) || function_exists( 'WC' );
	}

	/**
	 * Register WooCommerce hooks.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	protected function register_hooks(): void {
		// Add product post type to translatable
		add_filter( '\FPML_translatable_post_types', array( $this->whitelist_manager, 'add_product_post_type' ) );

		// Add WooCommerce taxonomies to translatable
		add_filter( '\FPML_translatable_taxonomies', array( $this->whitelist_manager, 'add_product_taxonomies' ) );

		// Add WooCommerce meta to whitelist
		add_filter( '\FPML_meta_whitelist', array( $this->whitelist_manager, 'add_woocommerce_meta' ) );

		// Sync product variations after translation
		add_action( 'fpml_after_translation_saved', array( $this->variation_synchronizer, 'sync_product_variations' ), 10, 2 );

		// Sync product gallery (with alt text)
		add_action( 'fpml_after_translation_saved', array( $this->gallery_synchronizer, 'sync_product_gallery' ), 15, 2 );

		// Sync product attributes
		add_action( 'fpml_after_translation_saved', array( $this->attribute_synchronizer, 'sync_product_attributes' ), 20, 2 );

		// Sync upsell/cross-sell
		add_action( 'fpml_after_translation_saved', array( $this->relation_synchronizer, 'sync_product_relations' ), 25, 2 );

		// Sync downloadable files
		add_action( 'fpml_after_translation_saved', array( $this->download_synchronizer, 'sync_downloadable_files' ), 30, 2 );

		// Sync product tabs
		add_action( 'fpml_after_translation_saved', array( $this->tab_synchronizer, 'sync_product_tabs' ), 35, 2 );

		// Admin notice
		add_action( 'admin_notices', array( $this->admin, 'integration_notice' ) );
	}
}
