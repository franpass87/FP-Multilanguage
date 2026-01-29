<?php
/**
 * Backward Compatibility Tests.
 *
 * @package FP_Multilanguage
 * @since 1.0.0
 */

namespace FP\Multilanguage\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Backward compatibility test case.
 *
 * @since 1.0.0
 */
class BackwardCompatibilityTest extends TestCase {
	/**
	 * Test old Settings class alias works.
	 *
	 * @return void
	 */
	public function testSettingsAlias(): void {
		// Check that old class name still works
		if ( class_exists( '\FPML_Settings' ) ) {
			$settings = \FPML_Settings::instance();
			$this->assertNotNull( $settings );
			$this->assertTrue( method_exists( $settings, 'get' ) );
			$this->assertTrue( method_exists( $settings, 'all' ) );
		} else {
			$this->markTestSkipped( 'Settings alias not available' );
		}
	}

	/**
	 * Test old Logger class alias works.
	 *
	 * @return void
	 */
	public function testLoggerAlias(): void {
		// Check that old class name still works
		if ( class_exists( '\FPML_Logger' ) ) {
			$logger = \FPML_Logger::instance();
			$this->assertNotNull( $logger );
			$this->assertTrue( method_exists( $logger, 'debug' ) || method_exists( $logger, 'log' ) );
		} else {
			$this->markTestSkipped( 'Logger alias not available' );
		}
	}

	/**
	 * Test old Queue class alias works.
	 *
	 * @return void
	 */
	public function testQueueAlias(): void {
		// Check that old class name still works
		if ( class_exists( '\FPML_Queue' ) ) {
			$queue = \FPML_Queue::instance();
			$this->assertNotNull( $queue );
			$this->assertTrue( method_exists( $queue, 'enqueue' ) || method_exists( $queue, 'get_by_state' ) );
		} else {
			$this->markTestSkipped( 'Queue alias not available' );
		}
	}

	/**
	 * Test old Container::get() still works.
	 *
	 * @return void
	 */
	public function testContainerGet(): void {
		// Check that old Container::get() method still works
		if ( class_exists( '\FP\Multilanguage\Core\Container' ) ) {
			// This should not throw
			$result = \FP\Multilanguage\Core\Container::get( 'test_service' );
			// Result can be null if service doesn't exist, that's fine
			$this->assertTrue( true );
		} else {
			$this->markTestSkipped( 'Old Container class not available' );
		}
	}

	/**
	 * Test SettingsAdapter maintains Settings interface.
	 *
	 * @return void
	 */
	public function testSettingsAdapterInterface(): void {
		if ( class_exists( '\FP\Multilanguage\Foundation\Options\SettingsAdapter' ) ) {
			$adapter = \FP\Multilanguage\Foundation\Options\SettingsAdapter::instance();
			
			// Test that adapter has all expected methods
			$this->assertTrue( method_exists( $adapter, 'get' ) );
			$this->assertTrue( method_exists( $adapter, 'all' ) );
			$this->assertTrue( method_exists( $adapter, 'get_defaults' ) );
			
			// Test that get() works
			$value = $adapter->get( 'provider', 'default' );
			$this->assertNotNull( $value );
		} else {
			$this->markTestSkipped( 'SettingsAdapter not available' );
		}
	}

	/**
	 * Test LoggerAdapter maintains Logger interface.
	 *
	 * @return void
	 */
	public function testLoggerAdapterInterface(): void {
		if ( class_exists( '\FP\Multilanguage\Foundation\Logger\LoggerAdapter' ) ) {
			// Test static methods
			$this->assertTrue( method_exists( '\FP\Multilanguage\Foundation\Logger\LoggerAdapter', 'debug' ) );
			$this->assertTrue( method_exists( '\FP\Multilanguage\Foundation\Logger\LoggerAdapter', 'info' ) );
			$this->assertTrue( method_exists( '\FP\Multilanguage\Foundation\Logger\LoggerAdapter', 'warning' ) );
			$this->assertTrue( method_exists( '\FP\Multilanguage\Foundation\Logger\LoggerAdapter', 'error' ) );
			
			// Test that static methods don't throw
			\FP\Multilanguage\Foundation\Logger\LoggerAdapter::debug( 'Test message' );
			$this->assertTrue( true );
		} else {
			$this->markTestSkipped( 'LoggerAdapter not available' );
		}
	}
}









