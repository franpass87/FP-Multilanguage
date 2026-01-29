<?php
/**
 * Health Check Job Checker - Checks stuck and failed jobs.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\HealthCheck;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks stuck and failed jobs.
 *
 * @since 0.10.0
 */
class JobChecker {
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
	 * Soglia per considerare un job bloccato (in secondi).
	 */
	const STUCK_JOB_THRESHOLD = 7200; // 2 ore

	/**
	 * Numero massimo di retry prima di considerare un job fallito.
	 */
	const MAX_RETRY_THRESHOLD = 5;

	/**
	 * Constructor.
	 *
	 * @param \FPML_Queue  $queue  Queue instance.
	 * @param \FPML_Logger $logger Logger instance.
	 */
	public function __construct( $queue, $logger ) {
		$this->queue = $queue;
		$this->logger = $logger;
	}

	/**
	 * Controlla job bloccati in stato "translating" da troppo tempo.
	 *
	 * @since 0.10.0
	 *
	 * @param array $report Report array to update.
	 * @param bool  $apply_recovery Applica auto-recovery.
	 *
	 * @return void
	 */
	public function check_stuck_jobs( array &$report, bool $apply_recovery ): void {
		global $wpdb;

		$table     = $this->queue->get_table();
		$threshold = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - self::STUCK_JOB_THRESHOLD );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stuck_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE state = %s AND updated_at < %s",
				'translating',
				$threshold
			)
		);

		$report['checks']['stuck_jobs'] = array(
			'label'  => 'Job bloccati',
			'value'  => (int) $stuck_count,
			'status' => 0 === (int) $stuck_count ? 'ok' : 'warning',
		);

		if ( $stuck_count > 0 ) {
			$report['issues'][] = array(
				'code'        => 'stuck_jobs',
				'severity'    => 'warning',
				'message'     => sprintf(
					/* translators: %d: numero di job bloccati */
					__( '%d job sono bloccati in stato "translating" da più di 2 ore.', 'fp-multilanguage' ),
					$stuck_count
				),
				'auto_fixable' => true,
			);

			if ( $apply_recovery ) {
				// Reset job bloccati a "pending".
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$reset = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$table} SET state = %s, retries = 0, last_error = %s, updated_at = %s WHERE state = %s AND updated_at < %s",
						'pending',
						'Auto-recovery: job bloccato reimpostato',
						current_time( 'mysql', true ),
						'translating',
						$threshold
					)
				);

				if ( $reset > 0 ) {
					$report['actions_taken'][] = array(
						'action'  => 'reset_stuck_jobs',
						'message' => sprintf(
							/* translators: %d: numero di job reimpostati */
							__( '%d job bloccati sono stati reimpostati a "pending".', 'fp-multilanguage' ),
							$reset
						),
					);

					$this->logger->log(
						'info',
						sprintf( 'Auto-recovery: %d job bloccati reimpostati', $reset ),
						array( 'count' => $reset )
					);
				}
			}
		}
	}

	/**
	 * Controlla job con troppi tentativi falliti.
	 *
	 * @since 0.10.0
	 *
	 * @param array $report Report array to update.
	 * @param bool  $apply_recovery Applica auto-recovery.
	 *
	 * @return void
	 */
	public function check_failed_jobs( array &$report, bool $apply_recovery ): void {
		global $wpdb;

		$table = $this->queue->get_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$failed_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE state = %s AND retries >= %d",
				'error',
				self::MAX_RETRY_THRESHOLD
			)
		);

		$report['checks']['failed_jobs'] = array(
			'label'  => 'Job falliti permanentemente',
			'value'  => (int) $failed_count,
			'status' => 0 === (int) $failed_count ? 'ok' : 'warning',
		);

		if ( $failed_count > 0 ) {
			$report['issues'][] = array(
				'code'        => 'failed_jobs',
				'severity'    => 'warning',
				'message'     => sprintf(
					/* translators: %d: numero di job falliti */
					__( '%d job hanno superato il numero massimo di tentativi e sono in errore permanente.', 'fp-multilanguage' ),
					$failed_count
				),
				'auto_fixable' => true,
			);

			if ( $apply_recovery ) {
				// Marca come "skipped" i job con troppi errori.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$skipped = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$table} SET state = %s, updated_at = %s WHERE state = %s AND retries >= %d",
						'skipped',
						current_time( 'mysql', true ),
						'error',
						self::MAX_RETRY_THRESHOLD
					)
				);

				if ( $skipped > 0 ) {
					$report['actions_taken'][] = array(
						'action'  => 'skip_failed_jobs',
						'message' => sprintf(
							/* translators: %d: numero di job saltati */
							__( '%d job falliti sono stati marcati come "skipped".', 'fp-multilanguage' ),
							$skipped
						),
					);

					$this->logger->log(
						'info',
						sprintf( 'Auto-recovery: %d job falliti marcati come skipped', $skipped ),
						array( 'count' => $skipped )
					);
				}
			}
		}
	}
}
















