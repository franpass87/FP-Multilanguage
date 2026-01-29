<?php
/**
 * Diagnostics - System health and metrics reporting.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 */


namespace FP\Multilanguage\Diagnostics;

use FP\Multilanguage\Core\Container;
use FP\Multilanguage\Core\ContainerAwareTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides diagnostic snapshots and queue metrics.
 *
 * @since 0.4.0
 */
class Diagnostics {
	use ContainerAwareTrait;
	/**
	 * Singleton instance.
	 *
	 * @var \FPML_Diagnostics|null
	 */
	protected static $instance = null;

	/**
	 * Queue instance.
	 *
	 * @var \FPML_Queue
	 */
	protected $queue;

	/**
	 * Logger instance.
	 *
	 * @var \FPML_Logger
	 */
	protected $logger;

	/**
	 * Settings instance.
	 *
	 * @var \FPML_Settings
	 */
	protected $settings;

	/**
	 * Constructor.
	 */
	protected function __construct() {
		// Inizializza queue con fallback
		$queue = Container::get( 'queue' );
		if ( ! $queue && class_exists( '\FPML_Queue' ) ) {
			$queue = fpml_get_queue();
		}
		if ( ! $queue ) {
			throw new \RuntimeException( 'Unable to initialize Queue instance for Diagnostics' );
		}
		$this->queue = $queue;

		// Inizializza logger con fallback
		$container = $this->getContainer();
		$logger = null;
		if ( $container && $container->has( 'logger' ) ) {
			$logger = $container->get( 'logger' );
		}
		if ( ! $logger ) {
			$logger = Container::get( 'logger' );
		}
		if ( ! $logger && class_exists( '\FPML_Logger' ) ) {
			$logger = fpml_get_logger();
		}
		if ( ! $logger ) {
			throw new \RuntimeException( 'Unable to initialize Logger instance for Diagnostics' );
		}
		$this->logger = $logger;

		// Inizializza settings con fallback
		$settings = null;
		if ( $container && $container->has( 'options' ) ) {
			$settings = $container->get( 'options' );
		}
		if ( ! $settings ) {
			$settings = Container::get( 'settings' );
		}
		if ( ! $settings && class_exists( '\FPML_Settings' ) ) {
			$settings = function_exists( 'fpml_get_options' ) ? fpml_get_options() : \FPML_Settings::instance();
		}
		if ( ! $settings ) {
			throw new \RuntimeException( 'Unable to initialize Settings instance for Diagnostics' );
		}
		$this->settings = $settings;
	}

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.0
	 *
	 * @return \FPML_Diagnostics
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
		// #region agent log
		$log_file = 'c:\\Users\\franc\\Local Sites\\fp-development\\app\\public\\.cursor\\debug.log';
		$log_entry = json_encode([
			'sessionId' => 'debug-session',
			'runId' => 'run1',
			'hypothesisId' => 'C',
			'location' => 'Diagnostics.php:129',
			'message' => 'get_snapshot called',
			'data' => [
				'assisted_mode' => $assisted_mode,
				'assisted_reason' => $assisted_reason,
			],
			'timestamp' => time() * 1000
		]) . "\n";
		file_put_contents($log_file, $log_entry, FILE_APPEND);
		// #endregion
		if ( $assisted_mode ) {
			return array(
				'assisted_mode'   => true,
				'assisted_reason' => $assisted_reason,
				'message'         => __( 'WPML o Polylang sono attivi: la gestione della coda è disabilitata e le metriche non sono disponibili.', 'fp-multilanguage' ),
			);
		}

		// Verifica che le dipendenze siano inizializzate
		// #region agent log
		$log_entry = json_encode([
			'sessionId' => 'debug-session',
			'runId' => 'run1',
			'hypothesisId' => 'C',
			'location' => 'Diagnostics.php:139',
			'message' => 'Checking dependencies',
			'data' => [
				'queue_not_null' => $this->queue !== null,
				'logger_not_null' => $this->logger !== null,
				'settings_not_null' => $this->settings !== null,
			],
			'timestamp' => time() * 1000
		]) . "\n";
		file_put_contents($log_file, $log_entry, FILE_APPEND);
		// #endregion
		if ( ! $this->queue ) {
			// #region agent log
			$log_entry = json_encode([
				'sessionId' => 'debug-session',
				'runId' => 'run1',
				'hypothesisId' => 'C',
				'location' => 'Diagnostics.php:140',
				'message' => 'Queue not initialized - throwing exception',
				'data' => [],
				'timestamp' => time() * 1000
			]) . "\n";
			file_put_contents($log_file, $log_entry, FILE_APPEND);
			// #endregion
			throw new \RuntimeException( 'Queue instance not initialized in Diagnostics' );
		}
		if ( ! $this->logger ) {
			// #region agent log
			$log_entry = json_encode([
				'sessionId' => 'debug-session',
				'runId' => 'run1',
				'hypothesisId' => 'C',
				'location' => 'Diagnostics.php:143',
				'message' => 'Logger not initialized - throwing exception',
				'data' => [],
				'timestamp' => time() * 1000
			]) . "\n";
			file_put_contents($log_file, $log_entry, FILE_APPEND);
			// #endregion
			throw new \RuntimeException( 'Logger instance not initialized in Diagnostics' );
		}
		if ( ! $this->settings ) {
			// #region agent log
			$log_entry = json_encode([
				'sessionId' => 'debug-session',
				'runId' => 'run1',
				'hypothesisId' => 'C',
				'location' => 'Diagnostics.php:146',
				'message' => 'Settings not initialized - throwing exception',
				'data' => [],
				'timestamp' => time() * 1000
			]) . "\n";
			file_put_contents($log_file, $log_entry, FILE_APPEND);
			// #endregion
			throw new \RuntimeException( 'Settings instance not initialized in Diagnostics' );
		}

		// Limita il tempo di esecuzione per evitare timeout
		$old_time_limit = ini_get( 'max_execution_time' );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 30 );
		}

		try {
			// #region agent log
			$log_entry = json_encode([
				'sessionId' => 'debug-session',
				'runId' => 'run1',
				'hypothesisId' => 'D',
				'location' => 'Diagnostics.php:155',
				'message' => 'Starting snapshot generation',
				'data' => [],
				'timestamp' => time() * 1000
			]) . "\n";
			file_put_contents($log_file, $log_entry, FILE_APPEND);
			// #endregion
			// Valori di default
			$counts = array();
			$terms_done = 0;
			$menu_done = 0;
			$events = array();
			$logs = array();
			$log_stats = array();
			$estimate = array(
				'characters'     => 0,
				'estimated_cost' => 0.0,
				'jobs_scanned'   => 0,
				'word_count'     => 0,
			);
			$estimate_error = '';
			$translator_status = array(
				'provider'   => '',
				'configured' => false,
				'error'      => '',
			);
			$batch_metrics = array();
			$queue_age = array();
			$recent_errors = array();
			$lock_active = false;
			$cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

			// Carica processor
			$processor = null;
			if ( class_exists( '\FPML_Processor' ) ) {
				$processor = \FPML_fpml_get_processor();
			}

			// Carica queue counts
			if ( method_exists( $this->queue, 'get_state_counts' ) ) {
				$counts = $this->queue->get_state_counts();
			}
			if ( method_exists( $this->queue, 'count_completed_jobs' ) ) {
				$terms_done = $this->queue->count_completed_jobs( 'term' );
				$menu_done  = $this->queue->count_completed_jobs( 'menu', 'title' );
			}

			// Carica events
			$events = array(
				'\FPML_run_queue'       => wp_next_scheduled( '\FPML_run_queue' ),
				'\FPML_retry_failed'    => wp_next_scheduled( '\FPML_retry_failed' ),
				'\FPML_resync_outdated' => wp_next_scheduled( '\FPML_resync_outdated' ),
				'\FPML_cleanup_queue'   => wp_next_scheduled( '\FPML_cleanup_queue' ),
			);

			// Carica logs
			if ( method_exists( $this->logger, 'get_logs' ) ) {
				$logs = $this->logger->get_logs( 25 );
			}
			if ( method_exists( $this->logger, 'get_stats' ) ) {
				$log_stats = $this->logger->get_stats();
			}

			// Carica cost estimate
			$cost_estimator = Container::get( 'cost_estimator' );
			if ( $cost_estimator && method_exists( $cost_estimator, 'estimate' ) ) {
				$estimate = $cost_estimator->estimate();
				if ( is_wp_error( $estimate ) ) {
					$estimate_error = $estimate->get_error_message();
					$estimate = array(
						'characters'     => 0,
						'estimated_cost' => 0.0,
						'jobs_scanned'   => 0,
						'word_count'     => 0,
					);
				}
			}

			// Carica translator status
			if ( $processor && method_exists( $processor, 'get_translator_instance' ) ) {
				$translator = $processor->get_translator_instance();
				$translator_status = array(
					'provider'   => method_exists( $this->settings, 'get' ) ? $this->settings->get( 'provider', '' ) : '',
					'configured' => ! is_wp_error( $translator ) && $translator instanceof \FPML_TranslatorInterface,
					'error'      => is_wp_error( $translator ) ? $translator->get_error_message() : '',
				);
			}

			// Carica batch metrics
			$batch_metrics = $this->calculate_batch_metrics( $logs );

			// Carica queue age
			$queue_age = $this->get_queue_age_summary();

			// Carica recent errors
			$recent_errors = $this->extract_recent_errors( $logs );

			// Carica lock status
			if ( $processor && method_exists( $processor, 'is_locked' ) ) {
				$lock_active = $processor->is_locked();
			}

			// Ripristina time limit
			if ( function_exists( 'set_time_limit' ) && $old_time_limit ) {
				@set_time_limit( $old_time_limit );
			}

			// #region agent log
			$log_entry = json_encode([
				'sessionId' => 'debug-session',
				'runId' => 'run1',
				'hypothesisId' => 'D',
				'location' => 'Diagnostics.php:256',
				'message' => 'Snapshot generation completed successfully',
				'data' => [
					'has_queue_counts' => !empty($counts),
					'has_events' => !empty($events),
					'has_logs' => !empty($logs),
				],
				'timestamp' => time() * 1000
			]) . "\n";
			file_put_contents($log_file, $log_entry, FILE_APPEND);
			// #endregion

			return array(
				'queue_counts'      => $counts,
				'kpi'               => array(
					'terms_translated'       => $terms_done,
					'menu_labels_translated' => $menu_done,
				),
				'events'            => $events,
				'lock_active'       => $lock_active,
				'cron_disabled'     => $cron_disabled,
				'logs'              => $logs,
				'log_stats'         => $log_stats,
				'estimate'          => $estimate,
				'estimate_error'    => $estimate_error,
				'translator_status' => $translator_status,
				'batch_average'     => $batch_metrics,
				'recent_errors'     => $recent_errors,
				'queue_age'         => $queue_age,
			);

		} catch ( \Throwable $e ) {
			// Ripristina time limit anche in caso di errore
			if ( function_exists( 'set_time_limit' ) && isset( $old_time_limit ) && $old_time_limit ) {
				@set_time_limit( $old_time_limit );
			}
			// Rilancia l'eccezione invece di nasconderla
			error_log( 'FPML Diagnostics: Error in get_snapshot - ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			// #region agent log
			$log_entry = json_encode([
				'sessionId' => 'debug-session',
				'runId' => 'run1',
				'hypothesisId' => 'D',
				'location' => 'Diagnostics.php:275',
				'message' => 'Exception in get_snapshot',
				'data' => [
					'error' => $e->getMessage(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
					'trace' => $e->getTraceAsString(),
				],
				'timestamp' => time() * 1000
			]) . "\n";
			file_put_contents($log_file, $log_entry, FILE_APPEND);
			// #endregion
			throw $e;
		}
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
		if ( ! is_array( $logs ) ) {
			return array(
				'duration' => 0.0,
				'jobs'     => 0.0,
			);
		}

		$batch_durations = array();
		$batch_jobs      = array();

		foreach ( $logs as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['message'] ) ) {
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
		if ( ! is_array( $logs ) ) {
			return array();
		}

		$errors = array();

		foreach ( $logs as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
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
		if ( ! $this->queue || ! method_exists( $this->queue, 'get_oldest_job_for_states' ) ) {
			return array(
				'retention_days' => 0,
				'cleanup_states' => array(),
				'pending'        => array(),
				'completed'      => array(),
			);
		}

		$now            = current_time( 'timestamp', true );
		$date_format    = get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' );
		$pending_states = array( 'pending', 'outdated', 'translating' );
		$cleanup_states = $this->get_queue_cleanup_states();
		$retention      = $this->settings && method_exists( $this->settings, 'get' ) ? (int) $this->settings->get( 'queue_retention_days', 0 ) : 0;

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
		$states = apply_filters( '\FPML_queue_cleanup_states', array( 'done', 'skipped', 'error' ) );
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



