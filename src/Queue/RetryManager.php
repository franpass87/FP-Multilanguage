<?php
/**
 * Retry manager - Handles retry logic for failed jobs.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Queue;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages retry logic for failed translation jobs.
 *
 * @since 0.10.0
 */
class RetryManager {
    /**
     * Cached queue handler.
     *
     * @var \FPML_Queue
     */
    protected $queue;

    /**
     * Whether the processor is running in assisted mode.
     *
     * @var bool
     */
    protected $assisted_mode = false;

    /**
     * Constructor.
     *
     * @param \FPML_Queue $queue         Queue instance.
     * @param bool        $assisted_mode Assisted mode flag.
     */
    public function __construct( $queue, $assisted_mode = false ) {
        $this->queue = $queue;
        $this->assisted_mode = $assisted_mode;
    }

    /**
     * Retry jobs previously marked as error.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function retry_failed_jobs() {
        if ( $this->assisted_mode ) {
            return;
        }

        $jobs = $this->queue->get_by_state( array( 'error' ), 50 );

        foreach ( $jobs as $job ) {
            if ( ! isset( $job->id ) ) {
                continue;
            }

            $retries = isset( $job->retries ) ? (int) $job->retries : 0;

            if ( $retries >= 5 ) {
                continue;
            }

            $this->retry_job( (int) $job->id );
        }
    }

    /**
     * Set a job back to pending while preserving the retry counter.
     *
     * @since 0.10.0
     *
     * @param int $job_id Job ID.
     *
     * @return void
     */
    protected function retry_job( int $job_id ): void {
        global $wpdb;

        $table = $this->queue->get_table();
        $now   = current_time( 'mysql', true );

        if ( version_compare( $GLOBALS['wp_version'] ?? '0', '6.2', '>=' ) ) {
            $wpdb->query(
                $wpdb->prepare(
                    'UPDATE %i SET state = %s, last_error = %s, updated_at = %s WHERE id = %d',
                    $table,
                    'pending',
                    '',
                    $now,
                    $job_id
                )
            );
        } else {
            $table_escaped = esc_sql( $table );
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table_escaped} SET state = 'pending', last_error = '', updated_at = %s WHERE id = %d",
                    $now,
                    $job_id
                )
            );
        }

        // Invalidate the state-counts cache so the dashboard reflects the new state immediately.
        wp_cache_delete( 'fpml_queue_state_counts', 'fpml_queue' );
    }
}
















