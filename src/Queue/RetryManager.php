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
            if ( isset( $job->retries ) && (int) $job->retries >= 5 ) {
                continue;
            }

            $this->queue->update_state( $job->id, 'pending' );
        }
    }
}
















