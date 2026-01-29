<?php
/**
 * Rewrites tests.
 *
 * @package FP_Multilanguage
 */

use PHPUnit\Framework\TestCase;

/**
 * Test Rewrites operations.
 */
final class RewritesTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$_SERVER = array();
	}

	public function test_get_current_language_from_path_returns_false_when_empty(): void {
		// Mock empty REQUEST_URI
		$_SERVER['REQUEST_URI'] = '';

		$rewrites = \FP\Multilanguage\Rewrites::instance();
		
		// Use reflection to access protected method
		$reflection = new ReflectionClass( $rewrites );
		$method = $reflection->getMethod( 'get_current_language_from_path' );
		$method->setAccessible( true );

		$result = $method->invoke( $rewrites );

		$this->assertFalse( $result );
	}

	public function test_is_target_language_path_returns_boolean(): void {
		$rewrites = \FP\Multilanguage\Rewrites::instance();

		// Use reflection to access protected method
		$reflection = new ReflectionClass( $rewrites );
		$method = $reflection->getMethod( 'is_target_language_path' );
		$method->setAccessible( true );

		$result = $method->invoke( $rewrites, null );

		$this->assertIsBool( $result );
	}

	public function test_register_rewrites_does_not_error(): void {
		$rewrites = \FP\Multilanguage\Rewrites::instance();

		// This should not throw exceptions
		$rewrites->register_rewrites();

		$this->assertTrue( true, 'register_rewrites should complete without errors' );
	}

	public function test_register_query_vars_returns_array(): void {
		$rewrites = \FP\Multilanguage\Rewrites::instance();

		$result = $rewrites->register_query_vars( array() );

		$this->assertIsArray( $result );
	}
}







