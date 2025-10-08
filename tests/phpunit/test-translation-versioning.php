<?php
/**
 * Test Translation Versioning functionality.
 *
 * @package FP_Multilanguage
 */

class Test_FPML_Translation_Versioning extends WP_UnitTestCase {
	/**
	 * Versioning instance.
	 *
	 * @var FPML_Translation_Versioning
	 */
	protected $versioning;

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected $post_id;

	/**
	 * Setup test.
	 */
	public function setUp(): void {
		parent::setUp();
		
		$this->versioning = FPML_Translation_Versioning::instance();
		$this->versioning->install_table();

		// Create test post
		$this->post_id = $this->factory->post->create( array(
			'post_title'   => 'Test Post',
			'post_content' => 'Test content',
		) );
	}

	/**
	 * Cleanup after test.
	 */
	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}fpml_translation_versions" );
		parent::tearDown();
	}

	/**
	 * Test table installation.
	 */
	public function test_table_installed() {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'fpml_translation_versions';
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );
		
		$this->assertEquals( $table_name, $table_exists, 'Versioning table should exist' );
	}

	/**
	 * Test saving a version.
	 */
	public function test_save_version() {
		$version_id = $this->versioning->save_version(
			'post',
			$this->post_id,
			'post_title',
			'Old Title',
			'New Title',
			'openai'
		);

		$this->assertIsInt( $version_id, 'Should return version ID' );
		$this->assertGreaterThan( 0, $version_id, 'Version ID should be positive' );
	}

	/**
	 * Test retrieving versions.
	 */
	public function test_get_versions() {
		// Save multiple versions
		$this->versioning->save_version( 'post', $this->post_id, 'post_title', 'Title 1', 'Title 2', 'openai' );
		$this->versioning->save_version( 'post', $this->post_id, 'post_title', 'Title 2', 'Title 3', 'deepl' );
		$this->versioning->save_version( 'post', $this->post_id, 'post_content', 'Content 1', 'Content 2', 'openai' );

		// Get all versions for this post
		$versions = $this->versioning->get_versions( 'post', $this->post_id );
		$this->assertCount( 3, $versions, 'Should have 3 versions' );

		// Get versions for specific field
		$title_versions = $this->versioning->get_versions( 'post', $this->post_id, 'post_title' );
		$this->assertCount( 2, $title_versions, 'Should have 2 title versions' );
	}

	/**
	 * Test rollback functionality.
	 */
	public function test_rollback_post() {
		// Update post title
		wp_update_post( array(
			'ID'         => $this->post_id,
			'post_title' => 'New Title',
		) );

		// Save version
		$version_id = $this->versioning->save_version(
			'post',
			$this->post_id,
			'post_title',
			'Old Title',
			'New Title',
			'openai'
		);

		// Rollback
		$result = $this->versioning->rollback( $version_id );
		$this->assertTrue( $result, 'Rollback should succeed' );

		// Verify post title is restored
		$post = get_post( $this->post_id );
		$this->assertEquals( 'Old Title', $post->post_title, 'Post title should be restored' );
	}

	/**
	 * Test rollback with invalid version.
	 */
	public function test_rollback_invalid_version() {
		$result = $this->versioning->rollback( 99999 );
		
		$this->assertInstanceOf( 'WP_Error', $result, 'Should return WP_Error for invalid version' );
		$this->assertEquals( 'invalid_version', $result->get_error_code() );
	}

	/**
	 * Test version cleanup.
	 */
	public function test_cleanup_old_versions() {
		global $wpdb;

		// Create old versions (manually set old timestamp)
		for ( $i = 0; $i < 10; $i++ ) {
			$wpdb->insert(
				$wpdb->prefix . 'fpml_translation_versions',
				array(
					'object_type'          => 'post',
					'object_id'            => $this->post_id,
					'field'                => 'post_title',
					'old_value'            => "Title $i",
					'new_value'            => 'Title ' . ( $i + 1 ),
					'translation_provider' => 'openai',
					'created_at'           => date( 'Y-m-d H:i:s', strtotime( '-100 days' ) ),
				)
			);
		}

		// Create recent versions
		for ( $i = 0; $i < 3; $i++ ) {
			$this->versioning->save_version(
				'post',
				$this->post_id,
				'post_title',
				"Recent $i",
				'Recent ' . ( $i + 1 ),
				'openai'
			);
		}

		// Cleanup (keep 5 versions minimum)
		$deleted = $this->versioning->cleanup_old_versions( 30, 5 );

		$this->assertGreaterThan( 0, $deleted, 'Should delete old versions' );

		// Verify we still have minimum versions
		$remaining = $this->versioning->get_versions( 'post', $this->post_id, 'post_title', 100 );
		$this->assertGreaterThanOrEqual( 3, count( $remaining ), 'Should keep recent versions' );
	}

	/**
	 * Test version statistics.
	 */
	public function test_get_stats() {
		// Create versions
		$this->versioning->save_version( 'post', $this->post_id, 'post_title', 'Old', 'New', 'openai' );
		$this->versioning->save_version( 'term', 123, 'name', 'Old Term', 'New Term', 'deepl' );

		$stats = $this->versioning->get_stats();

		$this->assertArrayHasKey( 'total_versions', $stats );
		$this->assertArrayHasKey( 'by_type', $stats );
		$this->assertEquals( 2, $stats['total_versions'], 'Should have 2 total versions' );
		$this->assertCount( 2, $stats['by_type'], 'Should have 2 object types' );
	}

	/**
	 * Test that identical values are not saved.
	 */
	public function test_skip_identical_values() {
		$version_id = $this->versioning->save_version(
			'post',
			$this->post_id,
			'post_title',
			'Same Title',
			'Same Title',
			'openai'
		);

		$this->assertFalse( $version_id, 'Should not save identical values' );
	}

	/**
	 * Test post meta rollback.
	 */
	public function test_rollback_post_meta() {
		$meta_key = '_test_meta';
		
		// Set initial meta
		update_post_meta( $this->post_id, $meta_key, 'Old Value' );
		
		// Save version and update meta
		$version_id = $this->versioning->save_version(
			'post',
			$this->post_id,
			$meta_key,
			'Old Value',
			'New Value',
			'openai'
		);
		
		update_post_meta( $this->post_id, $meta_key, 'New Value' );

		// Rollback
		$result = $this->versioning->rollback( $version_id );
		$this->assertTrue( $result );

		// Verify meta is restored
		$meta_value = get_post_meta( $this->post_id, $meta_key, true );
		$this->assertEquals( 'Old Value', $meta_value, 'Post meta should be restored' );
	}
}
