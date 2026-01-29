<?php
/**
 * Batch manager - Handles batch processing and character limits.
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
 * Manages batch processing and character limits for queue processing.
 *
 * @since 0.10.0
 */
class BatchManager {
    /**
     * Lock transient key.
     *
     * @var string
     */
    protected $lock_key = '\FPML_processor_lock';

    /**
     * Lock time to live in seconds.
     *
     * @var int
     */
    protected $lock_ttl = 120;

    /**
     * Cached settings instance.
     *
     * @var \FPML_Settings
     */
    protected $settings;

    /**
     * Characters processed in the current batch.
     *
     * @var int
     */
    protected $current_batch_characters = 0;

    /**
     * Characters processed while handling the current job.
     *
     * @var int
     */
    protected $current_job_characters = 0;

    /**
     * Constructor.
     *
     * @param \FPML_Settings $settings Settings instance.
     */
    public function __construct( $settings ) {
        $this->settings = $settings;
    }

    /**
     * Acquire processing lock.
     *
     * @since 0.2.0
     *
     * @return bool
     */
    public function acquire_lock() {
        $existing = get_transient( $this->lock_key );

        if ( false !== $existing ) {
            return false;
        }

        set_transient( $this->lock_key, time(), $this->lock_ttl );

        return true;
    }

    /**
     * Release processing lock.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function release_lock() {
        delete_transient( $this->lock_key );
    }

    /**
     * Check if processor is locked.
     *
     * @since 0.2.0
     *
     * @return bool
     */
    public function is_locked(): bool {
        return false !== get_transient( $this->lock_key );
    }

    /**
     * Force release lock (for admin use).
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function force_release_lock(): void {
        $this->release_lock();
    }

    /**
     * Reset batch character counter.
     *
     * @since 0.10.0
     * @return void
     */
    public function reset_batch_characters() {
        $this->current_batch_characters = 0;
    }

    /**
     * Reset job character counter.
     *
     * @since 0.10.0
     * @return void
     */
    public function reset_job_characters() {
        $this->current_job_characters = 0;
    }

    /**
     * Add characters to batch counter.
     *
     * @since 0.10.0
     *
     * @param int $chars Number of characters.
     * @return void
     */
    public function add_job_characters( $chars ) {
        $this->current_batch_characters += (int) $chars;
    }

    /**
     * Get current job characters.
     *
     * @since 0.10.0
     * @return int
     */
    public function get_current_job_characters() {
        return $this->current_job_characters;
    }

    /**
     * Set current job characters.
     *
     * @since 0.10.0
     *
     * @param int $chars Number of characters.
     * @return void
     */
    public function set_current_job_characters( $chars ) {
        $this->current_job_characters = (int) $chars;
    }

    /**
     * Check if should skip job due to character limit.
     *
     * @since 0.10.0
     * @return bool
     */
    public function should_skip_job_due_to_limit() {
        $max_chars_per_batch = $this->settings ? (int) $this->settings->get( 'max_chars_per_batch', 20000 ) : 20000;
        $max_chars_per_batch = max( 0, min( 1000000, $max_chars_per_batch ) );

        if ( $max_chars_per_batch > 0 && $this->current_batch_characters >= $max_chars_per_batch ) {
            return true;
        }

        return false;
    }
}
















