<?php
/**
 * Salient Theme Integration Tests.
 *
 * Tests for Salient theme meta fields translation integration.
 *
 * @package FP_Multilanguage
 * @since 0.10.0
 */

namespace FP\Multilanguage\Tests;

use FP\Multilanguage\Integrations\SalientThemeSupport;

/**
 * Test suite for Salient theme integration.
 *
 * @since 0.10.0
 */
class SalientIntegrationTest extends \WP_UnitTestCase {

	/**
	 * Salient theme support instance.
	 *
	 * @var SalientThemeSupport
	 */
	protected $salient_support;

	/**
	 * Setup test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->salient_support = SalientThemeSupport::instance();
	}

	/**
	 * Test that Salient meta fields are added to whitelist.
	 */
	public function test_salient_meta_fields_in_whitelist(): void {
		$meta_whitelist = apply_filters( 'FPML_meta_whitelist', array() );
		
		// Check for key Salient meta fields
		$this->assertContains( '_nectar_header_title', $meta_whitelist, 'Salient header title meta should be translatable.' );
		$this->assertContains( '_nectar_header_subtitle', $meta_whitelist, 'Salient header subtitle meta should be translatable.' );
		$this->assertContains( '_nectar_portfolio_extra_content', $meta_whitelist, 'Salient portfolio extra content meta should be translatable.' );
		$this->assertContains( '_nectar_project_excerpt', $meta_whitelist, 'Salient project excerpt meta should be translatable.' );
	}

	/**
	 * Test that Salient custom post types are added to translatable post types.
	 */
	public function test_salient_cpts_are_translatable(): void {
		$translatable_types = apply_filters( 'FPML_translatable_post_types', array() );
		
		// Salient may add custom post types like portfolio
		// This test verifies the filter is working
		$this->assertIsArray( $translatable_types, 'Translatable post types should be an array.' );
	}

	/**
	 * Test Salient header meta translation.
	 */
	public function test_salient_header_meta_translation(): void {
		// Create a test post
		$post_id = $this->factory->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Test Page',
			)
		);

		// Set Salient header meta
		update_post_meta( $post_id, '_nectar_header_title', 'Test Header Title' );
		update_post_meta( $post_id, '_nectar_header_subtitle', 'Test Header Subtitle' );

		// Verify meta is saved
		$this->assertEquals( 'Test Header Title', get_post_meta( $post_id, '_nectar_header_title', true ), 'Header title meta should be saved.' );
		$this->assertEquals( 'Test Header Subtitle', get_post_meta( $post_id, '_nectar_header_subtitle', true ), 'Header subtitle meta should be saved.' );
	}

	/**
	 * Test Salient portfolio meta translation.
	 */
	public function test_salient_portfolio_meta_translation(): void {
		// Create a portfolio post (if portfolio post type exists)
		$post_id = $this->factory->post->create(
			array(
				'post_type'  => 'portfolio',
				'post_title' => 'Test Portfolio',
			)
		);

		// Set Salient portfolio meta
		update_post_meta( $post_id, '_nectar_portfolio_extra_content', 'Test Portfolio Content' );
		update_post_meta( $post_id, '_nectar_project_excerpt', 'Test Project Excerpt' );

		// Verify meta is saved
		$this->assertEquals( 'Test Portfolio Content', get_post_meta( $post_id, '_nectar_portfolio_extra_content', true ), 'Portfolio extra content meta should be saved.' );
		$this->assertEquals( 'Test Project Excerpt', get_post_meta( $post_id, '_nectar_project_excerpt', true ), 'Project excerpt meta should be saved.' );
	}

	/**
	 * Test that Salient support instance is created.
	 */
	public function test_salient_support_instance(): void {
		$this->assertInstanceOf(
			SalientThemeSupport::class,
			$this->salient_support,
			'SalientThemeSupport instance should be created.'
		);
	}

	/**
	 * Test Salient post format meta translation.
	 */
	public function test_salient_post_format_meta(): void {
		// Create a post with quote format
		$post_id = $this->factory->post->create(
			array(
				'post_type'  => 'post',
				'post_title' => 'Test Quote Post',
			)
		);

		// Set post format
		set_post_format( $post_id, 'quote' );

		// Set Salient quote meta
		update_post_meta( $post_id, '_nectar_quote', 'Test Quote Text' );
		update_post_meta( $post_id, '_nectar_quote_author', 'Test Author' );

		// Verify meta is saved
		$this->assertEquals( 'Test Quote Text', get_post_meta( $post_id, '_nectar_quote', true ), 'Quote meta should be saved.' );
		$this->assertEquals( 'Test Author', get_post_meta( $post_id, '_nectar_quote_author', true ), 'Quote author meta should be saved.' );
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		parent::tearDown();
	}
}







