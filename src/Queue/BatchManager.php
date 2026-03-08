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
    protected $lock_key = 'fpml_processor_lock';

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
        // Register the cleanup hook so scheduled events actually execute.
        add_action( 'fpml_cleanup_lock', array( $this, 'cleanup_lock_option' ) );
    }

    /**
     * Delete a lock option created by acquire_lock().
     * Hooked to the `fpml_cleanup_lock` WP-Cron event.
     *
     * @since 0.10.0
     *
     * @param string $option_key The option key to delete.
     * @return void
     */
    public function cleanup_lock_option( string $option_key ): void {
        delete_option( $option_key );
    }

    /**
     * Acquire processing lock.
     *
     * @since 0.2.0
     *
     * @return bool
     */
    public function acquire_lock() {
        // Use add_option for atomic lock acquisition (INSERT IGNORE on MySQL).
        // add_option returns false if the option already exists, true if inserted.
        $option_key = 'fpml_lock_' . $this->lock_key;
        $acquired   = add_option( $option_key, time(), '', 'no' );

        if ( ! $acquired ) {
            // Check if the existing lock has expired.
            $existing = get_option( $option_key );
            if ( false !== $existing && ( time() - (int) $existing ) < $this->lock_ttl ) {
                return false;
            }
            // Expired lock: overwrite and claim it.
            update_option( $option_key, time(), 'no' );
        }

        // Schedule automatic cleanup of the option lock.
        wp_schedule_single_event( time() + $this->lock_ttl, 'fpml_cleanup_lock', array( $option_key ) );

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
        delete_option( 'fpml_lock_' . $this->lock_key );
    }

    /**
     * Check if processor is locked.
     *
     * @since 0.2.0
     *
     * @return bool
     */
    public function is_locked(): bool {
        $option_key = 'fpml_lock_' . $this->lock_key;
        $existing   = get_option( $option_key );
        if ( false === $existing ) {
            return false;
        }
        return ( time() - (int) $existing ) < $this->lock_ttl;
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
        $this->current_job_characters   += (int) $chars;
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
















