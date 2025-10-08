<?php
/**
 * Diagnostics - System health and metrics reporting.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides diagnostic snapshots and queue metrics.
 *
 * @since 0.4.0
 */
class FPML_Diagnostics {
	/**
	 * Singleton instance.
	 *
	 * @var FPML_Diagnostics|null
	 */
	protected static $instance = null;

	/**
	 * Queue instance.
	 *
	 * @var FPML_Queue
	 */
	protected $queue;

	/**
	 * Logger instance.
	 *
	 * @var FPML_Logger
	 */
	protected $logger;

	/**
	 * Settings instance.
	 *
	 * @var FPML_Settings
	 */
	protected $settings;

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$this->queue    = FPML_Container::get( 'queue' ) ?: FPML_Queue::instance();
		$this->logger   = FPML_Container::get( 'logger' ) ?: FPML_Logger::instance();
		$this->settings = FPML_Container::get( 'settings' ) ?: FPML_Settings::instance();
	}

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.0
	 *
	 * @return FPML_Diagnostics
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Build a diagnostics snapshot for the admin dashboard.
	 *
	 * @since 0.4.0
	 *
	 * @param bool $assisted_mode   Whether plugin is in assisted mode.
	 * @param string $assisted_reason Reason for assisted mode.
	 *
	 * @return array<string,mixed>
	 */
	public function get_snapshot( $assisted_mode = false, $assisted_reason = '' ) {
		if ( $assisted_mode ) {
			return array(
				'assisted_mode'   => true,
				'assisted_reason' => $assisted_reason,
				'message'         => __( 'WPML o Polylang sono attivi: la gestione della coda è disabilitata e le metriche non sono disponibili.', 'fp-multilanguage' ),
			);
		}

		$processor = FPML_Processor::instance();
		$counts    = $this->queue->get_state_counts();
		$terms_done = $this->queue->count_completed_jobs( 'term' );
		$menu_done  = $this->queue->count_completed_jobs( 'menu', 'title' );
		
		$events = array(
			'fpml_run_queue'       => wp_next_scheduled( 'fpml_run_queue' ),
			'fpml_retry_failed'    => wp_next_scheduled( 'fpml_retry_failed' ),
			'fpml_resync_outdated' => wp_next_scheduled( 'fpml_resync_outdated' ),
			'fpml_cleanup_queue'   => wp_next_scheduled( 'fpml_cleanup_queue' ),
		);

		$logs      = $this->logger->get_logs( 25 );
		$log_stats = $this->logger->get_stats();

		$cost_estimator = FPML_Container::get( 'cost_estimator' );
		$estimate       = $cost_estimator ? $cost_estimator->estimate() : array(
			'characters'     => 0,
			'estimated_cost' => 0.0,
			'jobs_scanned'   => 0,
			'word_count'     => 0,
		);
		$estimate_error = '';

		if ( is_wp_error( $estimate ) ) {
			$estimate_error = $estimate->get_error_message();
			$estimate       = array(
				'characters'     => 0,
				'estimated_cost' => 0.0,
				'jobs_scanned'   => 0,
				'word_count'     => 0,
			);
		}

		$translator = $processor->get_translator_instance();
		$translator_status = array(
			'provider'   => $this->settings ? $this->settings->get( 'provider', '' ) : '',
			'configured' => ! is_wp_error( $translator ) && $translator instanceof FPML_TranslatorInterface,
			'error'      => is_wp_error( $translator ) ? $translator->get_error_message() : '',
		);

		$batch_metrics   = $this->calculate_batch_metrics( $logs );
		$queue_age       = $this->get_queue_age_summary();
		$recent_errors   = $this->extract_recent_errors( $logs );

		return array(
			'queue_counts'      => $counts,
			'kpi'               => array(
				'terms_translated'       => $terms_done,
				'menu_labels_translated' => $menu_done,
			),
			'events'            => $events,
			'lock_active'       => $processor->is_locked(),
			'cron_disabled'     => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'logs'              => $logs,
			'log_stats'         => $log_stats,
			'estimate'          => $estimate,
			'estimate_error'    => $estimate_error,
			'translator_status' => $translator_status,
			'batch_average'     => $batch_metrics,
			'recent_errors'     => $recent_errors,
			'queue_age'         => $queue_age,
		);
	}

	/**
	 * Calculate batch processing metrics from logs.
	 *
	 * @since 0.4.0
	 *
	 * @param array $logs Log entries.
	 *
	 * @return array
	 */
	protected function calculate_batch_metrics( $logs ) {
		$batch_durations = array();
		$batch_jobs      = array();

		foreach ( $logs as $entry ) {
			if ( empty( $entry['message'] ) ) {
				continue;
			}

			if ( false !== strpos( $entry['message'], 'Batch coda completato' ) ) {
				if ( preg_match( '/([0-9]+(?:\.[0-9]+)?)s/', $entry['message'], $matches ) ) {
					$batch_durations[] = (float) $matches[1];
				}

				if ( isset( $entry['context']['jobs'] ) ) {
					$batch_jobs[] = (float) $entry['context']['jobs'];
				}
			}
		}

		$average_duration = ! empty( $batch_durations ) ? array_sum( $batch_durations ) / count( $batch_durations ) : 0.0;
		$average_jobs     = ! empty( $batch_jobs ) ? array_sum( $batch_jobs ) / count( $batch_jobs ) : 0.0;

		return array(
			'duration' => $average_duration,
			'jobs'     => $average_jobs,
		);
	}

	/**
	 * Extract recent error entries from logs.
	 *
	 * @since 0.4.0
	 *
	 * @param array $logs Log entries.
	 * @param int   $limit Maximum errors to return.
	 *
	 * @return array
	 */
	protected function extract_recent_errors( $logs, $limit = 5 ) {
		$errors = array();

		foreach ( $logs as $entry ) {
			if ( isset( $entry['level'] ) && 'error' === $entry['level'] ) {
				$errors[] = $entry;
			}
		}

		return array_slice( $errors, 0, $limit );
	}

	/**
	 * Build queue age metrics useful for diagnostics.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public function get_queue_age_summary() {
		$now            = current_time( 'timestamp', true );
		$date_format    = get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' );
		$pending_states = array( 'pending', 'outdated', 'translating' );
		$cleanup_states = $this->get_queue_cleanup_states();
		$retention      = $this->settings ? (int) $this->settings->get( 'queue_retention_days', 0 ) : 0;

		$oldest_pending   = $this->queue->get_oldest_job_for_states( $pending_states, 'created_at' );
		$oldest_completed = $this->queue->get_oldest_job_for_states( $cleanup_states, 'updated_at' );

		return array(
			'retention_days' => $retention,
			'cleanup_states' => $cleanup_states,
			'pending'        => $this->format_queue_age_entry( $oldest_pending, 'created_at', $now, $date_format ),
			'completed'      => $this->format_queue_age_entry( $oldest_completed, 'updated_at', $now, $date_format ),
		);
	}

	/**
	 * Retrieve the sanitized list of states targeted by queue cleanup.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	protected function get_queue_cleanup_states() {
		$states = apply_filters( 'fpml_queue_cleanup_states', array( 'done', 'skipped', 'error' ) );
		$states = array_filter( array_map( 'sanitize_key', (array) $states ) );

		return array_values( array_unique( $states ) );
	}

	/**
	 * Format queue age details for a specific job.
	 *
	 * @since 0.4.0
	 *
	 * @param object|null $job         Queue job instance.
	 * @param string      $column      Date column to read.
	 * @param int         $now         Current timestamp.
	 * @param string      $date_format Date format for local output.
	 *
	 * @return array
	 */
	protected function format_queue_age_entry( $job, $column, $now, $date_format ) {
		if ( ! $job || empty( $job->{$column} ) ) {
			return array();
		}

		$timestamp = mysql2date( 'U', $job->{$column}, false );

		if ( ! $timestamp ) {
			return array();
		}

		$local_datetime = function_exists( 'get_date_from_gmt' ) ? get_date_from_gmt( $job->{$column}, $date_format ) : $job->{$column};

		return array(
			'job_id'         => isset( $job->id ) ? (int) $job->id : 0,
			'state'          => isset( $job->state ) ? $job->state : '',
			'timestamp'      => (int) $timestamp,
			'age'            => human_time_diff( $timestamp, $now ),
			'datetime_gmt'   => $job->{$column},
			'datetime_local' => $local_datetime,
		);
	}
}
