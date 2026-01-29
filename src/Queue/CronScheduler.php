<?php
/**
 * Cron scheduler - Handles scheduling of cron events for queue processing.
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
 * Manages cron scheduling for queue processing.
 *
 * @since 0.10.0
 */
class CronScheduler {
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
     * @param \FPML_Settings $settings      Settings instance.
     * @param bool           $assisted_mode Assisted mode flag.
     */
    public function __construct( $settings, $assisted_mode = false ) {
        $this->settings = $settings;
        $this->assisted_mode = $assisted_mode;
    }

    /**
     * Register custom cron intervals.
     *
     * @since 0.2.0
     *
     * @param array<string, array{interval: int, display: string}> $schedules WP cron schedules.
     *
     * @return array<string, array{interval: int, display: string}>
     */
    public function register_schedules( array $schedules ): array {
        $schedules['\FPML_five_minutes'] = array(
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => __( 'Ogni 5 minuti (FP Multilanguage)', 'fp-multilanguage' ),
        );

        $schedules['\FPML_fifteen_minutes'] = array(
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display'  => __( 'Ogni 15 minuti (FP Multilanguage)', 'fp-multilanguage' ),
        );

        return $schedules;
    }

    /**
     * Determine schedule slug based on saved frequency.
     *
     * @since 0.2.0
     *
     * @return string
     */
    protected function get_schedule_from_settings() {
        $frequency = $this->settings ? $this->settings->get( 'cron_frequency', '15min' ) : '15min';

        if ( '5min' === $frequency ) {
            return '\FPML_five_minutes';
        }

        if ( 'hourly' === $frequency ) {
            return 'hourly';
        }

        return '\FPML_fifteen_minutes';
    }

    /**
     * Register cron hooks if they are not scheduled yet.
     *
     * @since 0.2.0
     *
     * @return void
     */
    public function maybe_schedule_events(): void {
        if ( $this->assisted_mode ) {
            return;
        }

        if ( $this->settings && $this->settings->get( 'manual_translation_mode', false ) ) {
            $this->clear_scheduled_event( '\FPML_run_queue' );
            return;
        }

        if ( ! wp_next_scheduled( '\FPML_run_queue' ) ) {
            wp_schedule_event( time() + MINUTE_IN_SECONDS, $this->get_schedule_from_settings(), '\FPML_run_queue' );
        }

        if ( ! wp_next_scheduled( '\FPML_retry_failed' ) ) {
            wp_schedule_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'hourly', '\FPML_retry_failed' );
        }

        if ( ! wp_next_scheduled( '\FPML_resync_outdated' ) ) {
            wp_schedule_event( time() + ( 10 * MINUTE_IN_SECONDS ), 'hourly', '\FPML_resync_outdated' );
        }

        $retention = $this->settings ? (int) $this->settings->get( 'queue_retention_days', 0 ) : 0;

        if ( $retention > 0 ) {
            if ( ! wp_next_scheduled( '\FPML_cleanup_queue' ) ) {
                wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', '\FPML_cleanup_queue' );
            }
        } else {
            $this->clear_scheduled_event( '\FPML_cleanup_queue' );
        }
    }

    /**
     * Reschedule cron events when settings change.
     *
     * @since 0.2.0
     *
     * @param mixed $old_value Previous settings.
     * @param mixed $value     New settings.
     *
     * @return void
     */
    public function reschedule_events( $old_value, $value ) {
        if ( $this->assisted_mode ) {
            return;
        }

        $this->clear_scheduled_event( '\FPML_run_queue' );
        $this->clear_scheduled_event( '\FPML_cleanup_queue' );
        
        $this->maybe_schedule_events();
    }

    /**
     * Clear a scheduled event.
     *
     * @since 0.2.0
     *
     * @param string $hook Event hook name.
     *
     * @return void
     */
    public function clear_scheduled_event( $hook ) {
        $timestamp = wp_next_scheduled( $hook );

        while ( false !== $timestamp ) {
            wp_unschedule_event( $timestamp, $hook );
            $timestamp = wp_next_scheduled( $hook );
        }
    }
}

