<?php
/**
 * Cleanup manager - Handles cleanup of old queue jobs.
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
 * Manages cleanup of old translation queue jobs.
 *
 * @since 0.10.0
 */
class CleanupManager {
    /**
     * Cached queue handler.
     *
     * @var \FPML_Queue
     */
    protected $queue;

    /**
     * Cached settings instance.
     *
     * @var \FPML_Settings
     */
    protected $settings;

    /**
     * Whether the processor is running in assisted mode.
     *
     * @var bool
     */
    protected $assisted_mode = false;

    /**
     * Constructor.
     *
     * @param \FPML_Queue    $queue         Queue instance.
     * @param \FPML_Settings $settings      Settings instance.
     * @param bool           $assisted_mode Assisted mode flag.
     */
    public function __construct( $queue, $settings, $assisted_mode = false ) {
        $this->queue = $queue;
        $this->settings = $settings;
        $this->assisted_mode = $assisted_mode;
    }

    /**
     * Perform queue cleanup when retention is configured.
     *
     * @since 0.3.1
     *
     * @return void
     */
    public function maybe_cleanup_queue() {
        if ( $this->assisted_mode || ! $this->settings ) {
            return;
        }

        $retention = (int) $this->settings->get( 'queue_retention_days', 0 );

        if ( $retention <= 0 ) {
            return;
        }

        $cutoff = time() - ( $retention * DAY_IN_SECONDS );
        $this->queue->delete_older_than( $cutoff, array( 'done', 'skipped' ) );
    }

    /**
     * Handle scheduled cleanup.
     *
     * @since 0.3.1
     *
     * @return void
     */
    public function handle_scheduled_cleanup() {
        $this->maybe_cleanup_queue();
    }

    /**
     * Move outdated jobs back to pending.
     *
     * @since 0.2.0
     *
     * @return int Number of jobs rescheduled.
     */
    public function resync_outdated_jobs(): int {
        if ( $this->assisted_mode ) {
            return 0;
        }

        $updated = 0;
        $batch   = 200;

        do {
            $jobs = $this->queue->get_by_state( array( 'outdated' ), $batch );

            if ( empty( $jobs ) ) {
                break;
            }

            foreach ( $jobs as $job ) {
                if ( $this->queue->update_state( $job->id, 'pending' ) ) {
                    $updated++;
                }
            }
        } while ( count( $jobs ) === $batch );

        return $updated;
    }
}
















