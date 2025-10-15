<?php
/**
 * Translation providers tests.
 *
 * @package FP_Multilanguage
 */

use PHPUnit\Framework\TestCase;

/**
 * Test translation provider implementations.
 */
final class ProvidersTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
	}

	public function test_openai_provider_returns_correct_slug(): void {
		$provider = new FPML_Provider_OpenAI();

		$this->assertEquals( 'openai', $provider->get_slug() );
	}

	public function test_google_provider_returns_correct_slug(): void {
		$provider = new FPML_Provider_Google();

		$this->assertEquals( 'google', $provider->get_slug() );
	}

	public function test_providers_implement_interface(): void {
		$providers = array(
			new FPML_Provider_OpenAI(),
			new FPML_Provider_Google(),
		);

		foreach ( $providers as $provider ) {
			$this->assertInstanceOf( FPML_TranslatorInterface::class, $provider );
		}
	}

	public function test_providers_have_required_methods(): void {
		$provider = new FPML_Provider_OpenAI();

		$this->assertTrue( method_exists( $provider, 'get_slug' ) );
		$this->assertTrue( method_exists( $provider, 'translate' ) );
		$this->assertTrue( method_exists( $provider, 'is_configured' ) );
		$this->assertTrue( method_exists( $provider, 'estimate_cost' ) );
	}

	public function test_estimate_cost_returns_float(): void {
		$provider = new FPML_Provider_OpenAI();

		$cost = $provider->estimate_cost( 'Test text' );

		$this->assertIsFloat( $cost );
		$this->assertGreaterThanOrEqual( 0.0, $cost );
	}

	public function test_estimate_cost_scales_with_text_length(): void {
		$provider = new FPML_Provider_OpenAI();

		$short_text = 'Short';
		$long_text = str_repeat( 'Long text ', 100 );

		$short_cost = $provider->estimate_cost( $short_text );
		$long_cost = $provider->estimate_cost( $long_text );

		// Longer text should cost more or equal
		$this->assertGreaterThanOrEqual( $short_cost, $long_cost );
	}

	public function test_is_configured_returns_boolean(): void {
		$providers = array(
			new FPML_Provider_OpenAI(),
			new FPML_Provider_Google(),
		);

		foreach ( $providers as $provider ) {
			$this->assertIsBool( $provider->is_configured() );
		}
	}

	public function test_translate_returns_string_or_error(): void {
		$provider = new FPML_Provider_OpenAI();

		// Without configuration should return error
		$result = $provider->translate( 'Test text' );

		$this->assertTrue( is_string( $result ) || is_wp_error( $result ) );
	}

	public function test_translate_handles_empty_text(): void {
		$provider = new FPML_Provider_OpenAI();

		$result = $provider->translate( '' );

		// Empty text should return empty string
		$this->assertEquals( '', $result );
	}

	public function test_chunk_text_preserves_content(): void {
		$provider = new FPML_Provider_OpenAI();

		// Use reflection to access protected method
		$reflection = new ReflectionClass( $provider );
		$method = $reflection->getMethod( 'chunk_text' );
		$method->setAccessible( true );

		$text = str_repeat( 'Test ', 1000 );
		$chunks = $method->invoke( $provider, $text, 500 );

		$this->assertIsArray( $chunks );
		$this->assertGreaterThan( 1, count( $chunks ) );

		// Recombined chunks should equal original
		$recombined = implode( '', $chunks );
		$this->assertEquals( $text, $recombined );
	}

	public function test_backoff_executes_without_error(): void {
		$provider = new FPML_Provider_OpenAI();

		// Use reflection to access protected method
		$reflection = new ReflectionClass( $provider );
		$method = $reflection->getMethod( 'backoff' );
		$method->setAccessible( true );

		// Should not throw exception (we can't test timing easily)
		try {
			$method->invoke( $provider, 1 );
			$this->assertTrue( true );
		} catch ( Exception $e ) {
			$this->fail( 'Backoff should not throw exception' );
		}
	}
}
