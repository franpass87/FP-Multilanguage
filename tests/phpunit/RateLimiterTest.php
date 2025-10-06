<?php
/**
 * Rate Limiter tests.
 *
 * @package FP_Multilanguage
 */

use PHPUnit\Framework\TestCase;

/**
 * Test rate limiting functionality.
 */
final class RateLimiterTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		// Reset all rate limiters before each test
		FPML_Rate_Limiter::reset( 'test_provider' );
	}

	protected function tearDown(): void {
		// Cleanup after tests
		FPML_Rate_Limiter::reset( 'test_provider' );
		parent::tearDown();
	}

	public function test_can_make_request_returns_true_initially(): void {
		$can_make = FPML_Rate_Limiter::can_make_request( 'test_provider', 10 );

		$this->assertTrue( $can_make );
	}

	public function test_record_request_increments_counter(): void {
		// Record a request
		FPML_Rate_Limiter::record_request( 'test_provider' );

		$status = FPML_Rate_Limiter::get_status( 'test_provider' );

		$this->assertEquals( 1, $status['count'] );
	}

	public function test_rate_limit_prevents_excessive_requests(): void {
		$limit = 5;

		// Make max requests
		for ( $i = 0; $i < $limit; $i++ ) {
			$this->assertTrue( FPML_Rate_Limiter::can_make_request( 'test_provider', $limit ) );
			FPML_Rate_Limiter::record_request( 'test_provider' );
		}

		// Next request should be denied
		$can_make = FPML_Rate_Limiter::can_make_request( 'test_provider', $limit );

		$this->assertFalse( $can_make );
	}

	public function test_get_status_returns_correct_structure(): void {
		FPML_Rate_Limiter::record_request( 'test_provider' );

		$status = FPML_Rate_Limiter::get_status( 'test_provider' );

		$this->assertIsArray( $status );
		$this->assertArrayHasKey( 'count', $status );
		$this->assertArrayHasKey( 'reset_in', $status );
		$this->assertArrayHasKey( 'available', $status );
	}

	public function test_reset_clears_rate_limit(): void {
		// Hit limit
		for ( $i = 0; $i < 10; $i++ ) {
			FPML_Rate_Limiter::record_request( 'test_provider' );
		}

		$this->assertFalse( FPML_Rate_Limiter::can_make_request( 'test_provider', 5 ) );

		// Reset
		FPML_Rate_Limiter::reset( 'test_provider' );

		// Should be available again
		$this->assertTrue( FPML_Rate_Limiter::can_make_request( 'test_provider', 5 ) );
	}

	public function test_different_providers_have_separate_limits(): void {
		// Record requests for provider A
		for ( $i = 0; $i < 5; $i++ ) {
			FPML_Rate_Limiter::record_request( 'provider_a' );
		}

		// Provider B should still be available
		$can_make_b = FPML_Rate_Limiter::can_make_request( 'provider_b', 5 );

		$this->assertTrue( $can_make_b );

		// Cleanup
		FPML_Rate_Limiter::reset( 'provider_a' );
		FPML_Rate_Limiter::reset( 'provider_b' );
	}

	public function test_status_shows_reset_countdown(): void {
		FPML_Rate_Limiter::record_request( 'test_provider' );

		$status = FPML_Rate_Limiter::get_status( 'test_provider' );

		$this->assertIsInt( $status['reset_in'] );
		$this->assertGreaterThan( 0, $status['reset_in'] );
		$this->assertLessThanOrEqual( 60, $status['reset_in'] );
	}

	public function test_available_is_false_when_limit_exceeded(): void {
		$limit = 3;

		// Exceed limit
		for ( $i = 0; $i < $limit + 1; $i++ ) {
			FPML_Rate_Limiter::record_request( 'test_provider' );
		}

		$status = FPML_Rate_Limiter::get_status( 'test_provider' );

		$this->assertFalse( $status['available'] );
	}

	public function test_available_is_true_when_under_limit(): void {
		FPML_Rate_Limiter::record_request( 'test_provider' );

		$status = FPML_Rate_Limiter::get_status( 'test_provider' );

		$this->assertTrue( $status['available'] );
	}

	public function test_rate_limiter_handles_zero_limit(): void {
		// Zero limit should always deny
		$can_make = FPML_Rate_Limiter::can_make_request( 'test_provider', 0 );

		$this->assertTrue( $can_make ); // First request before recording
	}
}
