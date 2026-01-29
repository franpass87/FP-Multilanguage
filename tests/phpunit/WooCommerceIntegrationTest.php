<?php
/**
 * WooCommerce Integration Tests.
 *
 * Tests for WooCommerce product translation integration.
 *
 * @package FP_Multilanguage
 * @since 0.10.0
 */

namespace FP\Multilanguage\Tests;

use FP\Multilanguage\Integrations\WooCommerceSupport;
use FP\Multilanguage\Content\TranslationManager;

/**
 * Test suite for WooCommerce integration.
 *
 * @since 0.10.0
 */
class WooCommerceIntegrationTest extends \WP_UnitTestCase {

	/**
	 * WooCommerce support instance.
	 *
	 * @var WooCommerceSupport
	 */
	protected $woocommerce_support;

	/**
	 * Translation manager instance.
	 *
	 * @var TranslationManager
	 */
	protected $translation_manager;

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Skip tests if WooCommerce is not available
		if ( ! class_exists( 'WooCommerce' ) && ! function_exists( 'WC' ) ) {
			$this->markTestSkipped( 'WooCommerce is not available.' );
		}

		$this->woocommerce_support = WooCommerceSupport::instance();
		$this->translation_manager = TranslationManager::instance();
	}

	/**
	 * Test that product post type is added to translatable post types.
	 */
	public function test_product_post_type_is_translatable(): void {
		$translatable_types = apply_filters( 'FPML_translatable_post_types', array() );
		
		$this->assertContains( 'product', $translatable_types, 'Product post type should be translatable.' );
	}

	/**
	 * Test that product taxonomies are added to translatable taxonomies.
	 */
	public function test_product_taxonomies_are_translatable(): void {
		$translatable_taxonomies = apply_filters( 'FPML_translatable_taxonomies', array() );
		
		$this->assertContains( 'product_cat', $translatable_taxonomies, 'Product category taxonomy should be translatable.' );
		$this->assertContains( 'product_tag', $translatable_taxonomies, 'Product tag taxonomy should be translatable.' );
	}

	/**
	 * Test that WooCommerce meta fields are added to whitelist.
	 */
	public function test_woocommerce_meta_fields_in_whitelist(): void {
		$meta_whitelist = apply_filters( 'FPML_meta_whitelist', array() );
		
		// Check for some key WooCommerce meta fields
		$this->assertContains( '_product_short_description', $meta_whitelist, 'Product short description meta should be translatable.' );
		$this->assertContains( '_regular_price', $meta_whitelist, 'Regular price meta should be in whitelist (for display).' );
	}

	/**
	 * Test product variation translation.
	 */
	public function test_product_variation_translation(): void {
		// Create a parent product
		$parent_product_id = $this->factory->post->create(
			array(
				'post_type'  => 'product',
				'post_title' => 'Test Product',
			)
		);

		// Create a product variation
		$variation_id = $this->factory->post->create(
			array(
				'post_type'   => 'product_variation',
				'post_parent' => $parent_product_id,
				'post_title'  => 'Variation',
			)
		);

		// Set variation attributes
		update_post_meta( $variation_id, '_price', '10.00' );
		update_post_meta( $variation_id, '_regular_price', '10.00' );

		// Verify variation exists
		$variation = get_post( $variation_id );
		$this->assertNotNull( $variation, 'Variation should exist.' );
		$this->assertEquals( 'product_variation', $variation->post_type, 'Post type should be product_variation.' );
	}

	/**
	 * Test product attribute translation.
	 */
	public function test_product_attribute_translation(): void {
		// Create a product attribute
		$attribute_id = $this->factory->term->create(
			array(
				'taxonomy' => 'pa_color',
				'name'     => 'Red',
			)
		);

		$term = get_term( $attribute_id, 'pa_color' );
		$this->assertNotNull( $term, 'Product attribute term should exist.' );
		$this->assertEquals( 'Red', $term->name, 'Attribute name should match.' );
	}

	/**
	 * Test product category translation.
	 */
	public function test_product_category_translation(): void {
		// Create a product category
		$category_id = $this->factory->term->create(
			array(
				'taxonomy' => 'product_cat',
				'name'     => 'Test Category',
			)
		);

		$category = get_term( $category_id, 'product_cat' );
		$this->assertNotNull( $category, 'Product category should exist.' );
		$this->assertEquals( 'Test Category', $category->name, 'Category name should match.' );
	}

	/**
	 * Test that WooCommerce support instance is created.
	 */
	public function test_woocommerce_support_instance(): void {
		$this->assertInstanceOf(
			WooCommerceSupport::class,
			$this->woocommerce_support,
			'WooCommerceSupport instance should be created.'
		);
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		parent::tearDown();
	}
}







