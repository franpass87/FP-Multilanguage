<?php
/**
 * Options Unit Tests.
 *
 * @package FP_Multilanguage
 * @since 1.0.0
 */

namespace FP\Multilanguage\Tests\Unit\Foundation;

use PHPUnit\Framework\TestCase;
use FP\Multilanguage\Foundation\Options\Options;
use FP\Multilanguage\Foundation\Options\OptionsInterface;

/**
 * Options test case.
 *
 * @since 1.0.0
 */
class OptionsTest extends TestCase {
	/**
	 * Test options implements interface.
	 *
	 * @return void
	 */
	public function testOptionsImplementsInterface(): void {
		$options = new Options( 'test_option', array() );
		$this->assertInstanceOf( OptionsInterface::class, $options );
	}

	/**
	 * Test options get and set.
	 *
	 * @return void
	 */
	public function testOptionsGetAndSet(): void {
		$option_key = 'test_option_' . time();
		$options = new Options( $option_key, array( 'default' => 'value' ) );

		$value = $options->get( 'default' );
		$this->assertEquals( 'value', $value );

		$options->set( 'test_key', 'test_value' );
		$retrieved = $options->get( 'test_key' );
		$this->assertEquals( 'test_value', $retrieved );

		// Cleanup
		delete_option( $option_key );
	}

	/**
	 * Test options delete.
	 *
	 * @return void
	 */
	public function testOptionsDelete(): void {
		$option_key = 'test_option_' . time();
		$options = new Options( $option_key, array() );

		$options->set( 'test_key', 'test_value' );
		$options->delete( 'test_key' );

		$this->assertFalse( $options->has( 'test_key' ) );

		// Cleanup
		delete_option( $option_key );
	}

	/**
	 * Test options all.
	 *
	 * @return void
	 */
	public function testOptionsAll(): void {
		$option_key = 'test_option_' . time();
		$defaults = array( 'key1' => 'value1', 'key2' => 'value2' );
		$options = new Options( $option_key, $defaults );

		$all = $options->all();
		$this->assertIsArray( $all );
		$this->assertArrayHasKey( 'key1', $all );

		// Cleanup
		delete_option( $option_key );
	}

	/**
	 * Test nested options.
	 *
	 * @return void
	 */
	public function testNestedOptions(): void {
		$option_key = 'test_option_' . time();
		$options = new Options( $option_key, array() );

		$options->set( 'nested.key', 'value' );
		$value = $options->get( 'nested.key' );
		$this->assertEquals( 'value', $value );

		// Cleanup
		delete_option( $option_key );
	}
}
