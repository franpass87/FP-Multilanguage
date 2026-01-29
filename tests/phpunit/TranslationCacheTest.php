<?php
/**
 * Translation Cache tests.
 *
 * @package FP_Multilanguage
 * @since 0.10.0
 */

use PHPUnit\Framework\TestCase;

/**
 * Test TranslationCache operations.
 */
final class TranslationCacheTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$_SERVER = array();
		// Clear cache before each test
		$cache = \FP\Multilanguage\Core\TranslationCache::instance();
		$cache->clear();
	}

	protected function tearDown(): void {
		// Clear cache after each test
		$cache = \FP\Multilanguage\Core\TranslationCache::instance();
		$cache->clear();
		parent::tearDown();
	}

	public function test_instance_returns_singleton(): void {
		$instance1 = \FP\Multilanguage\Core\TranslationCache::instance();
		$instance2 = \FP\Multilanguage\Core\TranslationCache::instance();

		$this->assertSame( $instance1, $instance2 );
	}

	public function test_get_returns_false_when_not_cached(): void {
		$cache = \FP\Multilanguage\Core\TranslationCache::instance();

		$result = $cache->get( 'Hello', 'openai', 'en', 'it' );

		$this->assertFalse( $result );
	}

	public function test_set_and_get_work_correctly(): void {
		$cache = \FP\Multilanguage\Core\TranslationCache::instance();

		$text = 'Hello';
		$translated = 'Ciao';
		$provider = 'openai';
		$source = 'en';
		$target = 'it';

		// Set cache
		$set_result = $cache->set( $text, $provider, $translated, $source, $target );
		$this->assertTrue( $set_result );

		// Get from cache
		$cached = $cache->get( $text, $provider, $source, $target );
		$this->assertEquals( $translated, $cached );
	}

	public function test_set_returns_false_for_empty_translation(): void {
		$cache = \FP\Multilanguage\Core\TranslationCache::instance();

		$result = $cache->set( 'Hello', 'openai', '', 'en', 'it' );

		$this->assertFalse( $result );
	}

	public function test_clear_removes_all_cached_translations(): void {
		$cache = \FP\Multilanguage\Core\TranslationCache::instance();

		// Set some translations
		$cache->set( 'Hello', 'openai', 'Ciao', 'en', 'it' );
		$cache->set( 'World', 'openai', 'Mondo', 'en', 'it' );

		// Verify they're cached
		$this->assertEquals( 'Ciao', $cache->get( 'Hello', 'openai', 'en', 'it' ) );
		$this->assertEquals( 'Mondo', $cache->get( 'World', 'openai', 'en', 'it' ) );

		// Clear cache
		$result = $cache->clear();
		$this->assertTrue( $result );

		// Verify they're gone
		$this->assertFalse( $cache->get( 'Hello', 'openai', 'en', 'it' ) );
		$this->assertFalse( $cache->get( 'World', 'openai', 'en', 'it' ) );
	}

	public function test_get_stats_returns_array(): void {
		$cache = \FP\Multilanguage\Core\TranslationCache::instance();

		$stats = $cache->get_stats();

		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'hits', $stats );
		$this->assertArrayHasKey( 'misses', $stats );
		$this->assertArrayHasKey( 'total', $stats );
		$this->assertArrayHasKey( 'hit_rate', $stats );
	}

	public function test_get_stats_tracks_hits_and_misses(): void {
		$cache = \FP\Multilanguage\Core\TranslationCache::instance();
		$cache->reset_stats();

		// Miss
		$cache->get( 'Hello', 'openai', 'en', 'it' );

		// Set and get (hit)
		$cache->set( 'Hello', 'openai', 'Ciao', 'en', 'it' );
		$cache->get( 'Hello', 'openai', 'en', 'it' );

		$stats = $cache->get_stats();

		$this->assertGreaterThanOrEqual( 1, $stats['hits'] );
		$this->assertGreaterThanOrEqual( 1, $stats['misses'] );
	}

	public function test_reset_stats_clears_statistics(): void {
		$cache = \FP\Multilanguage\Core\TranslationCache::instance();

		// Generate some stats
		$cache->get( 'Hello', 'openai', 'en', 'it' );
		$cache->set( 'Hello', 'openai', 'Ciao', 'en', 'it' );
		$cache->get( 'Hello', 'openai', 'en', 'it' );

		$stats_before = $cache->get_stats();

		// Reset stats
		$cache->reset_stats();

		$stats_after = $cache->get_stats();

		$this->assertEquals( 0, $stats_after['hits'] );
		$this->assertEquals( 0, $stats_after['misses'] );
	}

	public function test_invalidate_post_translations_works(): void {
		$cache = \FP\Multilanguage\Core\TranslationCache::instance();

		// This should not throw an error even with invalid post ID
		$cache->invalidate_post_translations( 0 );

		$this->assertTrue( true ); // If we get here, no error was thrown
	}
}







