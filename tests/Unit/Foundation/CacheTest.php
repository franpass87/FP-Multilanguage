<?php
/**
 * Cache Unit Tests.
 *
 * @package FP_Multilanguage
 * @since 1.0.0
 */

namespace FP\Multilanguage\Tests\Unit\Foundation;

use PHPUnit\Framework\TestCase;
use FP\Multilanguage\Foundation\Cache\TransientCache;
use FP\Multilanguage\Foundation\Cache\CacheInterface;

/**
 * Cache test case.
 *
 * @since 1.0.0
 */
class CacheTest extends TestCase {
	/**
	 * Test cache implements interface.
	 *
	 * @return void
	 */
	public function testCacheImplementsInterface(): void {
		$cache = new TransientCache( 'test_' );
		$this->assertInstanceOf( CacheInterface::class, $cache );
	}

	/**
	 * Test cache set and get.
	 *
	 * @return void
	 */
	public function testCacheSetAndGet(): void {
		$cache = new TransientCache( 'test_' );
		$key = 'test_key_' . time();
		$value = 'test_value';

		$result = $cache->set( $key, $value, 60 );
		$this->assertTrue( $result );

		$retrieved = $cache->get( $key );
		$this->assertEquals( $value, $retrieved );
	}

	/**
	 * Test cache delete.
	 *
	 * @return void
	 */
	public function testCacheDelete(): void {
		$cache = new TransientCache( 'test_' );
		$key = 'test_key_' . time();
		$value = 'test_value';

		$cache->set( $key, $value, 60 );
		$result = $cache->delete( $key );
		$this->assertTrue( $result );

		$retrieved = $cache->get( $key, 'default' );
		$this->assertEquals( 'default', $retrieved );
	}

	/**
	 * Test cache has.
	 *
	 * @return void
	 */
	public function testCacheHas(): void {
		$cache = new TransientCache( 'test_' );
		$key = 'test_key_' . time();
		$value = 'test_value';

		$this->assertFalse( $cache->has( $key ) );

		$cache->set( $key, $value, 60 );
		$this->assertTrue( $cache->has( $key ) );
	}
}
