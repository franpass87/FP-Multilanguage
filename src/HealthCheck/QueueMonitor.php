<?php
/**
 * Health Check Queue Monitor - Monitors queue growth.
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
 * Monitors queue growth.
 *
 * @since 0.10.0
 */
class QueueMonitor {
	/**
	 * Queue instance.
	 *
	 * @var \FPML_Queue
	 */
	protected $queue;

	/**
	 * Constructor.
	 *
	 * @param \FPML_Queue $queue Queue instance.
	 */
	public function __construct( $queue ) {
		$this->queue = $queue;
	}

	/**
	 * Controlla la crescita della coda.
	 *
	 * @since 0.10.0
	 *
	 * @param array $report Report array to update.
	 *
	 * @return void
	 */
	public function check_queue_growth( array &$report ): void {
		$counts        = $this->queue->get_state_counts();
		$pending_count = isset( $counts['pending'] ) ? $counts['pending'] : 0;
		$pending_count += isset( $counts['outdated'] ) ? $counts['outdated'] : 0;

		$status = 'ok';
		if ( $pending_count > 1000 ) {
			$status = 'critical';
		} elseif ( $pending_count > 500 ) {
			$status = 'warning';
		}

		$report['checks']['queue_size'] = array(
			'label'  => 'Dimensione coda',
			'value'  => $pending_count,
			'status' => $status,
		);

		if ( 'ok' !== $status ) {
			$report['issues'][] = array(
				'code'     => 'large_queue',
				'severity' => 'warning',
				'message'  => sprintf(
					/* translators: %d: numero di job in coda */
					__( 'La coda contiene %d job in attesa. Considera di aumentare la frequenza del cron o il batch size.', 'fp-multilanguage' ),
					$pending_count
				),
				'auto_fixable' => false,
			);
		}
	}
}
















