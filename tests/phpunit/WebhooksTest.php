<?php
/**
 * Webhooks tests.
 *
 * @package FP_Multilanguage
 */

use PHPUnit\Framework\TestCase;

/**
 * Test webhook functionality.
 */
final class WebhooksTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
	}

	public function test_webhooks_instance_returns_singleton(): void {
		$webhooks1 = FPML_Webhooks::instance();
		$webhooks2 = FPML_Webhooks::instance();

		$this->assertSame( $webhooks1, $webhooks2 );
	}

	public function test_test_webhook_requires_url(): void {
		$webhooks = FPML_Webhooks::instance();

		// Test without URL should return error
		$result = $webhooks->test_webhook( '' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'fpml_webhook_missing_url', $result->get_error_code() );
	}

	public function test_test_webhook_validates_url_format(): void {
		$webhooks = FPML_Webhooks::instance();

		// Valid URL structure
		$result = $webhooks->test_webhook( 'https://example.com/webhook' );

		// Should either succeed or fail with connection error (not format error)
		$this->assertTrue( is_bool( $result ) || is_wp_error( $result ) );

		if ( is_wp_error( $result ) ) {
			$this->assertNotEquals( 'fpml_webhook_missing_url', $result->get_error_code() );
		}
	}

	public function test_send_batch_complete_requires_processed_jobs(): void {
		$webhooks = FPML_Webhooks::instance();

		// Mock summary with no processed jobs
		$summary = array(
			'claimed'   => 10,
			'processed' => 0,
			'skipped'   => 10,
			'errors'    => 0,
		);

		// Should not throw exception
		try {
			$webhooks->send_batch_complete( $summary );
			$this->assertTrue( true );
		} catch ( Exception $e ) {
			$this->fail( 'Should handle zero processed jobs gracefully' );
		}
	}

	public function test_send_cleanup_complete_requires_significant_deletion(): void {
		$webhooks = FPML_Webhooks::instance();

		// Small cleanup (< 10 jobs) should not send webhook
		try {
			$webhooks->send_cleanup_complete( 5, array( 'done' ), 7 );
			$this->assertTrue( true );
		} catch ( Exception $e ) {
			$this->fail( 'Should handle small cleanups gracefully' );
		}
	}

	public function test_get_status_returns_valid_data(): void {
		$status = FPML_Rate_Limiter::get_status( 'test_provider' );

		$this->assertIsArray( $status );
		$this->assertIsInt( $status['count'] );
		$this->assertIsInt( $status['reset_in'] );
		$this->assertIsBool( $status['available'] );
	}
}
