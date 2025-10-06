<?php
/**
 * Processor tests.
 *
 * @package FP_Multilanguage
 */

use PHPUnit\Framework\TestCase;

/**
 * Test processor functionality.
 */
final class ProcessorTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$_SERVER = array();
	}

	public function test_processor_instance_returns_singleton(): void {
		$processor1 = FPML_Processor::instance();
		$processor2 = FPML_Processor::instance();

		$this->assertSame( $processor1, $processor2 );
	}

	public function test_processor_lock_mechanism_prevents_concurrent_runs(): void {
		$processor = FPML_Processor::instance();

		// Should not be locked initially
		$this->assertFalse( $processor->is_locked() );

		// Acquire lock
		$locked = $processor->acquire_lock();
		$this->assertTrue( $locked );

		// Should be locked now
		$this->assertTrue( $processor->is_locked() );

		// Release lock
		$processor->release_lock();
		$this->assertFalse( $processor->is_locked() );
	}

	public function test_processor_get_translator_instance_returns_provider_or_error(): void {
		$processor = FPML_Processor::instance();

		$translator = $processor->get_translator_instance();

		// Should either be a translator object or WP_Error
		$this->assertTrue(
			( $translator instanceof FPML_TranslatorInterface ) || is_wp_error( $translator )
		);
	}

	public function test_processor_run_batch_returns_summary(): void {
		$processor = FPML_Processor::instance();

		// Run with batch size of 0 should return summary
		$summary = $processor->run_batch( 0 );

		$this->assertIsArray( $summary );
		$this->assertArrayHasKey( 'claimed', $summary );
		$this->assertArrayHasKey( 'processed', $summary );
		$this->assertArrayHasKey( 'skipped', $summary );
		$this->assertArrayHasKey( 'errors', $summary );
	}

	public function test_processor_lock_expires_after_timeout(): void {
		$processor = FPML_Processor::instance();

		// Manually set an expired lock
		update_option( 'fpml_queue_lock', time() - 3600 );

		// Should not be considered locked
		$this->assertFalse( $processor->is_locked() );
	}

	public function test_processor_respects_lock_when_active(): void {
		$processor = FPML_Processor::instance();

		// Acquire lock
		$processor->acquire_lock();

		// Second acquisition should fail
		$second_lock = $processor->acquire_lock();
		$this->assertFalse( $second_lock );

		// Cleanup
		$processor->release_lock();
	}

	public function test_processor_process_job_handles_missing_translator(): void {
		$processor = FPML_Processor::instance();

		// Mock job with invalid data
		$job = new stdClass();
		$job->id = 9999;
		$job->object_type = 'post';
		$job->object_id = 0;
		$job->field = 'post_title';

		// Should handle gracefully (not throw exception)
		try {
			$result = $processor->process_job( $job );
			$this->assertTrue( true );
		} catch ( Exception $e ) {
			$this->fail( 'Processor should handle missing translator gracefully' );
		}
	}
}
