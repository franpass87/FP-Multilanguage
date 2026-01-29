<?php
/**
 * Sanitizer Unit Tests.
 *
 * @package FP_Multilanguage
 * @since 1.0.0
 */

namespace FP\Multilanguage\Tests\Unit\Foundation;

use PHPUnit\Framework\TestCase;
use FP\Multilanguage\Foundation\Sanitization\Sanitizer;
use FP\Multilanguage\Foundation\Sanitization\SanitizerInterface;

/**
 * Sanitizer test case.
 *
 * @since 1.0.0
 */
class SanitizerTest extends TestCase {
	/**
	 * Test sanitizer implements interface.
	 *
	 * @return void
	 */
	public function testSanitizerImplementsInterface(): void {
		$sanitizer = new Sanitizer();
		$this->assertInstanceOf( SanitizerInterface::class, $sanitizer );
	}

	/**
	 * Test text sanitization.
	 *
	 * @return void
	 */
	public function testTextSanitization(): void {
		$sanitizer = new Sanitizer();

		$dirty = '<script>alert("xss")</script>Hello';
		$clean = $sanitizer->sanitize( $dirty, 'text' );

		$this->assertStringNotContainsString( '<script>', $clean );
		$this->assertStringContainsString( 'Hello', $clean );
	}

	/**
	 * Test email sanitization.
	 *
	 * @return void
	 */
	public function testEmailSanitization(): void {
		$sanitizer = new Sanitizer();

		$dirty = 'test@example.com<script>';
		$clean = $sanitizer->sanitize( $dirty, 'email' );

		$this->assertEquals( 'test@example.com', $clean );
	}

	/**
	 * Test integer sanitization.
	 *
	 * @return void
	 */
	public function testIntegerSanitization(): void {
		$sanitizer = new Sanitizer();

		$dirty = '-123abc';
		$clean = $sanitizer->sanitize( $dirty, 'integer' );

		$this->assertEquals( 123, $clean );
		$this->assertIsInt( $clean );
	}

	/**
	 * Test sanitize all.
	 *
	 * @return void
	 */
	public function testSanitizeAll(): void {
		$sanitizer = new Sanitizer();

		$data = array(
			'name'  => '<script>Test</script>',
			'email' => 'test@example.com<script>',
		);

		$rules = array(
			'name'  => 'text',
			'email' => 'email',
		);

		$sanitized = $sanitizer->sanitizeAll( $data, $rules );

		$this->assertStringNotContainsString( '<script>', $sanitized['name'] );
		$this->assertEquals( 'test@example.com', $sanitized['email'] );
	}
}









