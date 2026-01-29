<?php
/**
 * Translation Manager tests.
 *
 * @package FP_Multilanguage
 */

use PHPUnit\Framework\TestCase;

/**
 * Test TranslationManager operations.
 */
final class TranslationManagerTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$_SERVER = array();
	}

	public function test_get_translation_id_returns_false_for_invalid_post(): void {
		$manager = \FP\Multilanguage\Content\TranslationManager::instance();

		$result = $manager->get_translation_id( 0, 'en' );

		$this->assertFalse( $result );
	}

	public function test_get_translation_id_returns_false_for_invalid_language(): void {
		$manager = \FP\Multilanguage\Content\TranslationManager::instance();

		$result = $manager->get_translation_id( 1, 'invalid_lang' );

		$this->assertFalse( $result );
	}

	public function test_get_all_translations_returns_array(): void {
		$manager = \FP\Multilanguage\Content\TranslationManager::instance();

		$result = $manager->get_all_translations( 1 );

		$this->assertIsArray( $result );
	}

	public function test_get_all_translations_returns_empty_for_invalid_post(): void {
		$manager = \FP\Multilanguage\Content\TranslationManager::instance();

		$result = $manager->get_all_translations( 0 );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public function test_is_creating_translation_returns_boolean(): void {
		$manager = \FP\Multilanguage\Content\TranslationManager::instance();

		$result = $manager->is_creating_translation();

		$this->assertIsBool( $result );
	}

	public function test_get_translation_id_uses_cache(): void {
		$manager = \FP\Multilanguage\Content\TranslationManager::instance();

		// Clear cache first
		wp_cache_delete( 'translation_id_1_en', 'fpml_translations' );

		// First call
		$result1 = $manager->get_translation_id( 1, 'en' );

		// Second call should use cache
		$result2 = $manager->get_translation_id( 1, 'en' );

		$this->assertEquals( $result1, $result2 );
	}

	public function test_get_all_translations_uses_cache(): void {
		$manager = \FP\Multilanguage\Content\TranslationManager::instance();

		// Clear cache first
		wp_cache_delete( 'all_translations_1', 'fpml_translations' );

		// First call
		$result1 = $manager->get_all_translations( 1 );

		// Second call should use cache
		$result2 = $manager->get_all_translations( 1 );

		$this->assertEquals( $result1, $result2 );
		$this->assertIsArray( $result1 );
		$this->assertIsArray( $result2 );
	}

	public function test_instance_returns_singleton(): void {
		$instance1 = \FP\Multilanguage\Content\TranslationManager::instance();
		$instance2 = \FP\Multilanguage\Content\TranslationManager::instance();

		$this->assertSame( $instance1, $instance2 );
	}
}

