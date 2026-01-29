<?php
/**
 * Queue handler for translation jobs - Moved to Core\Queue namespace.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Core\Queue;

use FP\Multilanguage\Queue\QueueTableManager;
use FP\Multilanguage\Queue\QueueEnqueuer;
use FP\Multilanguage\Queue\QueueJobCleanup;
use FP\Multilanguage\Queue\QueueBatchManager;
use FP\Multilanguage\Queue\QueueStateManager;
use FP\Multilanguage\Queue\QueueJobOperations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queue implementation - Wrapper around existing Queue class.
 *
 * This is a transitional class that wraps the existing Queue implementation.
 * In Phase 2, the existing Queue class will be fully migrated here.
 *
 * @since 1.0.0
 */
class Queue implements QueueInterface {
	/**
	 * Schema version for the queue table.
	 */
	const SCHEMA_VERSION = '3';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Wrapped Queue instance.
	 *
	 * @var \FP\Multilanguage\Queue
	 */
	protected $wrapped_queue;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		// Use existing Queue class for now
		$this->wrapped_queue = ( function_exists( 'fpml_get_queue' ) ? fpml_get_queue() : \FP\Multilanguage\Queue::instance() );
	}

	/**
	 * Enqueue or update a job.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id   Object ID.
	 * @param string $field       Field identifier.
	 * @param string $hash_source Hash of the source payload.
	 * @return int Job ID.
	 */
	public function enqueue( string $object_type, int $object_id, string $field, string $hash_source ): int {
		return $this->wrapped_queue->enqueue( $object_type, $object_id, $field, $hash_source );
	}

	/**
	 * Get jobs by state.
	 *
	 * @param string $state Job state.
	 * @param int    $limit Maximum number of jobs.
	 * @return array Jobs.
	 */
	public function getByState( string $state, int $limit = 100 ): array {
		// Existing Queue uses array of states
		return $this->wrapped_queue->get_by_state( array( $state ), $limit );
	}

	/**
	 * Get state counts.
	 *
	 * @return array State => count.
	 */
	public function getStateCounts(): array {
		return $this->wrapped_queue->get_state_counts();
	}

	/**
	 * Process a batch of jobs.
	 *
	 * @param int $batch_size Batch size.
	 * @return array Results.
	 */
	public function processBatch( int $batch_size = 5 ): array {
		// Check if method exists (it might be in Processor or elsewhere)
		if ( method_exists( $this->wrapped_queue, 'process_batch' ) ) {
			return $this->wrapped_queue->process_batch( $batch_size );
		}
		// Fallback: claim batch and process manually
		$jobs = $this->wrapped_queue->claim_batch( $batch_size );
		return array( 'processed' => count( $jobs ), 'jobs' => $jobs );
	}

	/**
	 * Get job state.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id   Object ID.
	 * @param string $field       Field identifier.
	 * @return string|null State or null if not found.
	 */
	public function getJobState( string $object_type, int $object_id, string $field ): ?string {
		return $this->wrapped_queue->get_job_state( $object_type, $object_id, $field );
	}

	/**
	 * Get wrapped queue instance (for backward compatibility).
	 *
	 * @return \FP\Multilanguage\Queue
	 */
	public function getWrapped(): \FP\Multilanguage\Queue {
		return $this->wrapped_queue;
	}

	/**
	 * Delegate method calls to wrapped queue.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Arguments.
	 * @return mixed
	 */
	public function __call( string $method, array $args ) {
		if ( method_exists( $this->wrapped_queue, $method ) ) {
			return call_user_func_array( array( $this->wrapped_queue, $method ), $args );
		}
		return null;
	}
}

