<?php
/**
 * Queue Interface.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Core\Queue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queue interface for translation job management.
 *
 * @since 1.0.0
 */
interface QueueInterface {
	/**
	 * Enqueue or update a job.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id   Object ID.
	 * @param string $field       Field identifier.
	 * @param string $hash_source Hash of the source payload.
	 * @return int Job ID.
	 */
	public function enqueue( string $object_type, int $object_id, string $field, string $hash_source ): int;

	/**
	 * Get jobs by state.
	 *
	 * @param string $state Job state.
	 * @param int    $limit Maximum number of jobs.
	 * @return array Jobs.
	 */
	public function getByState( string $state, int $limit = 100 ): array;

	/**
	 * Get state counts.
	 *
	 * @return array State => count.
	 */
	public function getStateCounts(): array;

	/**
	 * Process a batch of jobs.
	 *
	 * @param int $batch_size Batch size.
	 * @return array Results.
	 */
	public function processBatch( int $batch_size = 5 ): array;

	/**
	 * Get job state.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id   Object ID.
	 * @param string $field       Field identifier.
	 * @return string|null State or null if not found.
	 */
	public function getJobState( string $object_type, int $object_id, string $field ): ?string;
}









