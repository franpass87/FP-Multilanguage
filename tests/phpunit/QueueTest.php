<?php
/**
 * Queue tests.
 *
 * @package FP_Multilanguage
 */

use PHPUnit\Framework\TestCase;

/**
 * Test queue operations.
 */
final class QueueTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$_SERVER = array();
	}

	public function test_queue_enqueue_creates_job(): void {
		$queue = FPML_Queue::instance();

		// Mock data
		$result = $queue->enqueue( 'post', 123, 'post_title', md5( 'test content' ) );

		$this->assertTrue( is_int( $result ) && $result > 0, 'Enqueue should return a job ID' );
	}

	public function test_queue_get_next_jobs_returns_pending(): void {
		$queue = FPML_Queue::instance();

		// Enqueue a job
		$queue->enqueue( 'post', 456, 'post_content', md5( 'test' ) );

		$jobs = $queue->get_next_jobs( 10 );

		$this->assertIsArray( $jobs );
	}

	public function test_queue_count_by_state_returns_integer(): void {
		$queue = FPML_Queue::instance();

		$count = $queue->count_by_state( 'pending' );

		$this->assertIsInt( $count );
		$this->assertGreaterThanOrEqual( 0, $count );
	}

	public function test_queue_update_state_changes_job_status(): void {
		$queue = FPML_Queue::instance();

		// Create a job
		$job_id = $queue->enqueue( 'post', 789, 'post_title', md5( 'content' ) );

		// Update state
		$result = $queue->update_state( $job_id, 'done' );

		$this->assertTrue( $result, 'State update should succeed' );
	}

	public function test_queue_cleanup_removes_old_jobs(): void {
		$queue = FPML_Queue::instance();

		// Cleanup should not error
		$deleted = $queue->cleanup_old_jobs( array( 'done' ), 30, 'updated_at' );

		$this->assertIsInt( $deleted );
		$this->assertGreaterThanOrEqual( 0, $deleted );
	}

	public function test_queue_get_state_counts_returns_array(): void {
		$queue = FPML_Queue::instance();

		$counts = $queue->get_state_counts();

		$this->assertIsArray( $counts );
		$this->assertArrayHasKey( 'pending', $counts );
		$this->assertArrayHasKey( 'done', $counts );
	}

	public function test_queue_get_oldest_job_returns_object_or_null(): void {
		$queue = FPML_Queue::instance();

		$oldest = $queue->get_oldest_job_for_states( array( 'pending' ), 'created_at' );

		$this->assertTrue( is_object( $oldest ) || is_null( $oldest ) );
	}

	public function test_queue_count_completed_jobs_returns_integer(): void {
		$queue = FPML_Queue::instance();

		$count = $queue->count_completed_jobs( 'post' );

		$this->assertIsInt( $count );
		$this->assertGreaterThanOrEqual( 0, $count );
	}

	public function test_queue_enqueue_term_creates_job(): void {
		$queue = FPML_Queue::instance();

		// Mock term object
		$term = new stdClass();
		$term->term_id = 123;
		$term->taxonomy = 'category';
		$term->name = 'Test Category';

		$result = $queue->enqueue_term( $term, 'name' );

		$this->assertTrue( is_int( $result ) && $result > 0 );
	}

	public function test_queue_get_jobs_for_states_returns_array(): void {
		$queue = FPML_Queue::instance();

		$jobs = $queue->get_jobs_for_states( array( 'pending', 'outdated' ), 10, 0 );

		$this->assertIsArray( $jobs );
	}
}
