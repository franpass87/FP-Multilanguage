<?php
/**
 * Queue handler for translation jobs.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */


namespace FP\Multilanguage;

use FP\Multilanguage\Core\HostingDetector;
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
 * Manage the wp_fpml_queue table and CRUD operations.
 *
 * @since 0.2.0
 * @since 0.10.0 Refactored to use modular components.
 */
class Queue {
        /**
         * Schema version for the queue table.
         *
         * @since 0.3.1
         */
	const SCHEMA_VERSION = '3';

        /**
         * Singleton instance (for backward compatibility).
         *
         * @var \FPML_Queue|null
         * @deprecated 1.0.0 Use dependency injection instead
         */
        protected static $instance = null;

        /**
         * Cached table name.
         *
         * @var string
         */
        protected $table = '';

        /**
         * Table manager instance.
         *
         * @since 0.10.0
         *
         * @var QueueTableManager
         */
        protected $table_manager;

        /**
         * Enqueuer instance.
         *
         * @since 0.10.0
         *
         * @var QueueEnqueuer
         */
        protected $enqueuer;

        /**
         * Job cleanup instance.
         *
         * @since 0.10.0
         *
         * @var QueueJobCleanup
         */
        protected $job_cleanup;

        /**
         * Batch manager instance.
         *
         * @since 0.10.0
         *
         * @var QueueBatchManager
         */
        protected $batch_manager;

        /**
         * State manager instance.
         *
         * @since 0.10.0
         *
         * @var QueueStateManager
         */
        protected $state_manager;

        /**
         * Job operations instance.
         *
         * @since 0.10.0
         *
         * @var QueueJobOperations
         */
        protected $job_operations;

        /**
         * Retrieve singleton instance (for backward compatibility).
         *
         * @since 0.2.0
         * @deprecated 1.0.0 Use dependency injection via container instead
         *
         * @return self
         */
        public static function instance(): self {
		_doing_it_wrong( 
			'FP\Multilanguage\Queue::instance()', 
			'Queue::instance() is deprecated. Use dependency injection via container instead.', 
			'1.0.0' 
		);
		
                if ( null === self::$instance ) {
                        self::$instance = new self();
                }

                return self::$instance;
        }

        /**
         * Constructor.
         *
         * @since 0.2.0
         * @since 1.0.0 Now public to support dependency injection
         */
        public function __construct() {
                global $wpdb;

		$this->table = $wpdb->prefix . 'FPML_queue';

                // Initialize modules
                $this->table_manager  = new QueueTableManager( $this->table );
                $this->enqueuer       = new QueueEnqueuer( $this->table );
                $this->job_cleanup    = new QueueJobCleanup( $this->table );
                $this->batch_manager  = new QueueBatchManager( $this->table );
                $this->state_manager   = new QueueStateManager( $this->table );
                $this->job_operations = new QueueJobOperations( $this->table );

                $this->register_table_name();
        }

        /**
         * Ensure global $wpdb has the custom table registered for caching purposes.
         *
         * @since 0.2.0
         *
         * @return void
         */
        public function register_table_name() {
                $this->table_manager->register_table_name();
        }

        /**
         * Get the fully qualified table name.
         *
         * @since 0.2.0
         *
         * @return string
         */
        public function get_table(): string {
                return $this->table_manager->get_table();
        }

        /**
         * Install database table using dbDelta.
         *
         * @since 0.2.0
         *
         * @return void
         */
        public function install() {
                $this->table_manager->install();
        }

        /**
         * Ensure the database schema is up to date.
         *
         * @since 0.3.1
         *
         * @return void
         */
        public function maybe_upgrade() {
                $this->table_manager->maybe_upgrade();
        }

        /**
         * Enqueue or update a job.
         *
         * @since 0.2.0
         *
         * @param string $object_type Object type.
         * @param int    $object_id   Object ID.
         * @param string $field       Field identifier.
         * @param string $hash_source Hash of the source payload.
         *
         * @return int Job ID.
         */
        public function enqueue( $object_type, $object_id, $field, $hash_source ) {
                return $this->enqueuer->enqueue( $object_type, $object_id, $field, $hash_source );
        }

        /**
         * Enqueue a term translation job.
         *
         * @since 0.2.0
         *
         * @param \WP_Term $term Term object.
         * @param string   $field Field identifier (name|description).
         * @return int Job ID.
         */
        public function enqueue_term( \WP_Term $term, string $field ): int {
                return $this->enqueuer->enqueue_term( $term, $field );
        }

        /**
         * Enqueue a menu item label translation job.
         *
         * @since 0.3.0
         *
         * @param \WP_Post $item Menu item post.
         * @return int Job ID.
         */
        public function enqueue_menu_item_label( \WP_Post $item ): int {
                return $this->enqueuer->enqueue_menu_item_label( $item );
        }

        /**
         * Clean up old jobs from the queue.
         *
         * @since 0.3.1
         *
         * @param array  $states Queue states to target.
         * @param int    $days Retention window in days.
         * @param string $column Date column used for comparison. Default 'updated_at'.
         * @return int|WP_Error Number of deleted jobs or WP_Error on failure.
         */
        public function cleanup_old_jobs( array $states, int $days, string $column = 'updated_at' ): int|\WP_Error {
                return $this->job_cleanup->cleanup_old_jobs( $states, $days, $column );
        }

        /**
         * Count jobs matching the cleanup criteria.
         *
         * @since 0.3.1
         *
         * @param array $states Queue states to target.
         * @param int   $days   Retention window in days.
         * @param string $column Date column used for comparison.
         *
         * @return int|WP_Error Number of matching jobs or WP_Error on failure.
         */
        public function count_old_jobs( $states, $days, $column = 'updated_at' ) {
                return $this->job_cleanup->count_old_jobs( $states, $days, $column );
        }

        /**
         * Get oldest job for specified states.
         *
         * @since 0.3.0
         *
         * @param array  $states States list.
         * @param string $column Date column. Default 'created_at'.
         * @return object|null Job object or null if not found.
         */
        public function get_oldest_job_for_states( array $states, string $column = 'created_at' ): ?object {
                return $this->job_cleanup->get_oldest_job_for_states( $states, $column );
        }

        /**
         * Claim a batch of jobs for processing.
         *
         * @since 0.2.0
         *
         * @param int $limit Limit (0 = auto-detect).
         * @return array Array of job objects.
         */
        public function claim_batch( int $limit = 0 ): array {
                return $this->batch_manager->claim_batch( $limit );
        }

        /**
         * Update job state.
         *
         * @since 0.2.0
         *
         * @param int    $job_id Job ID.
         * @param string $state New state.
         * @param string $error Error message (optional).
         * @return bool True if updated, false otherwise.
         */
        public function update_state( int $job_id, string $state, string $error = '' ): bool {
                return $this->state_manager->update_state( $job_id, $state, $error );
        }

        /**
         * Reset retries counter manually.
         *
         * @since 0.2.0
         *
         * @param int $job_id Job ID.
         * @return bool True if updated, false otherwise.
         */
        public function reset_retries( int $job_id ): bool {
                return $this->state_manager->reset_retries( $job_id );
        }

        /**
         * Retrieve jobs by state.
         *
         * @since 0.2.0
         *
         * @param array $states States list.
         * @param int   $limit Limit.
         * @return array Array of job objects.
         */
        public function get_by_state( array $states, int $limit = 50 ): array {
                return $this->state_manager->get_by_state( $states, $limit );
        }

        /**
         * Delete a job from the queue.
         *
         * @since 0.2.0
         *
         * @param int $job_id Job ID.
         *
         * @return bool
         */
        public function delete( $job_id ) {
                return $this->job_operations->delete( $job_id );
        }

        /**
         * Bulk mark jobs for a specific object as outdated.
         *
         * @since 0.2.0
         *
         * @param string $object_type Object type.
         * @param int    $object_id Object ID.
         * @return void
         */
        public function mark_outdated( string $object_type, int $object_id ): void {
                $this->job_operations->mark_outdated( $object_type, $object_id );
        }

        /**
         * Purge completed jobs older than a threshold.
         *
         * @since 0.2.0
         *
         * @param int $days Days threshold. Default 30.
         * @return int Number of deleted rows.
         */
        public function purge_completed( int $days = 30 ): int {
                return $this->job_cleanup->purge_completed( $days );
        }

        /**
         * Retrieve counts grouped by state.
         * Cached for 2 minutes to reduce database load.
         *
         * @since 0.2.0
         * @since 0.10.0 Optimized with caching
         *
         * @return array<string,int> Array with state as key and count as value.
         */
        public function get_state_counts(): array {
                return $this->state_manager->get_state_counts();
        }

        /**
         * Count completed jobs for a specific object type and optional field.
         *
         * @since 0.3.0
         *
         * @param string      $object_type Object type slug.
         * @param string|null $field       Optional field identifier.
         *
         * @return int
         */
        public function count_completed_jobs( $object_type, $field = null ) {
                return $this->job_operations->count_completed_jobs( $object_type, $field );
        }

        /**
         * Reset specific states back to pending.
         *
         * @since 0.2.0
         *
         * @param array $states States to reset.
         *
         * @return int Updated rows.
         */
        public function reset_states( $states = array( 'translating' ) ) {
                return $this->state_manager->reset_states( $states );
        }

	/**
	 * Fetch jobs for a given set of states.
	 *
	 * @since 0.2.0
	 *
	 * @param array $states States to include.
	 * @param int   $limit  Batch size.
	 * @param int   $offset Offset for pagination.
	 *
	 * @return array
	 */
	public function get_jobs_for_states( $states, $limit = 50, $offset = 0 ) {
		return $this->state_manager->get_jobs_for_states( $states, $limit, $offset );
	}

	/**
	 * Check if a specific post has pending translation jobs.
	 *
	 * @since 0.5.1
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool True if there are pending jobs for this post.
	 */
	public function has_pending_jobs( $post_id ) {
		return $this->job_operations->has_pending_jobs( $post_id );
	}
}

