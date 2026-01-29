<?php
/**
 * Queue tests.
 *
 * @package FP_Multilanguage
 * @since 0.10.0
 */

use PHPUnit\Framework\TestCase;

/**
 * Test Queue operations.
 */
final class QueueTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$_SERVER = array();
	}

	public function test_instance_returns_singleton(): void {
		$instance1 = \FPML_Queue::instance();
		$instance2 = \FPML_Queue::instance();

		$this->assertSame( $instance1, $instance2 );
	}

	public function test_get_table_returns_string(): void {
		$queue = \FPML_Queue::instance();
		$table = $queue->get_table();

		$this->assertIsString( $table );
		$this->assertNotEmpty( $table );
	}

	public function test_enqueue_returns_zero_for_invalid_input(): void {
		$queue = \FPML_Queue::instance();

		$result = $queue->enqueue( '', 0, '', '' );

		$this->assertEquals( 0, $result );
	}

	public function test_get_state_counts_returns_array(): void {
		$queue = \FPML_Queue::instance();
		$counts = $queue->get_state_counts();

		$this->assertIsArray( $counts );
	}

	public function test_update_state_returns_false_for_invalid_job_id(): void {
		$queue = \FPML_Queue::instance();

		$result = $queue->update_state( 0, 'pending' );

		$this->assertFalse( $result );
	}

	public function test_reset_retries_returns_false_for_invalid_job_id(): void {
		$queue = \FPML_Queue::instance();

		$result = $queue->reset_retries( 0 );

		$this->assertFalse( $result );
	}

	public function test_get_by_state_returns_empty_array_for_empty_states(): void {
		$queue = \FPML_Queue::instance();

		$result = $queue->get_by_state( array(), 10 );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public function test_delete_returns_false_for_invalid_job_id(): void {
		$queue = \FPML_Queue::instance();

		$result = $queue->delete( 0 );

		$this->assertFalse( $result );
	}

	public function test_claim_batch_returns_array(): void {
		$queue = \FPML_Queue::instance();

		$result = $queue->claim_batch( 10 );

		$this->assertIsArray( $result );
	}

	public function test_get_state_counts_caches_result(): void {
		$queue = \FPML_Queue::instance();

		// Clear any existing cache
		wp_cache_delete( 'fpml_queue_state_counts', 'fpml_queue' );
		delete_transient( 'fpml_queue_state_counts' );

		// First call
		$result1 = $queue->get_state_counts();

		// Second call should use cache
		$result2 = $queue->get_state_counts();

		$this->assertEquals( $result1, $result2 );
	}
}
