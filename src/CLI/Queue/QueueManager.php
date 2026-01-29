<?php
/**
 * CLI Queue Manager - Handles queue reset, resync, and cleanup operations.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\CLI\Queue;

use FP\Multilanguage\Core\ContainerAwareTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles queue reset, resync, and cleanup operations.
 *
 * @since 0.10.0
 */
class QueueManager {
	use ContainerAwareTrait;
	/**
	 * Reset stuck jobs and release the processor lock.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function reset(): void {
		$processor = \FPML_fpml_get_processor();
		$queue     = fpml_get_queue();

		$processor->force_release_lock();
		$reset = $queue->reset_states( array( 'translating' ) );

		\WP_CLI::success(
			sprintf(
				/* translators: %d: number of jobs reset */
				__( 'Lock rilasciato. Job ripristinati in pending: %d', 'fp-multilanguage' ),
				(int) $reset
			)
		);
	}

	/**
	 * Reschedule outdated jobs.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function resync(): void {
		$processor = \FPML_fpml_get_processor();
		$updated   = $processor->resync_outdated_jobs();

		\WP_CLI::success(
			sprintf(
				/* translators: %d: number of jobs */
				__( 'Job outdated riportati in pending: %d', 'fp-multilanguage' ),
				(int) $updated
			)
		);
	}

	/**
	 * Purge completed jobs older than the retention window.
	 *
	 * @since 0.10.0
	 *
	 * @param int|null   $days    Number of days to retain. Default: plugin setting.
	 * @param array|null $states  States to remove. Default: plugin setting.
	 * @param bool       $dry_run Show what would be removed without executing DELETE.
	 *
	 * @return void
	 */
	public function cleanup( ?int $days = null, ?array $states = null, bool $dry_run = false ): void {
		$container = $this->getContainer();
		$settings = $container && $container->has( 'options' ) ? $container->get( 'options' ) : \FPML_Settings::instance();
		$default_days = $settings ? (int) $settings->get( 'queue_retention_days', 0 ) : 0;
		$days         = $days ?? $default_days;

		if ( $days <= 0 ) {
			\WP_CLI::error( __( 'Specifica un numero di giorni maggiore di zero o configura la retention dalle impostazioni.', 'fp-multilanguage' ) );
		}

		if ( null === $states ) {
			$age_summary = ( function_exists( 'fpml_get_plugin' ) ? fpml_get_plugin() : \FPML_Plugin::instance() )->get_queue_age_summary();
			$states      = isset( $age_summary['cleanup_states'] ) ? (array) $age_summary['cleanup_states'] : array();
		}

		if ( empty( $states ) ) {
			\WP_CLI::error( __( 'Nessuno stato valido specificato per la pulizia.', 'fp-multilanguage' ) );
		}

		$queue = fpml_get_queue();

		if ( $dry_run ) {
			$count = $queue->count_old_jobs( $states, $days, 'updated_at' );

			if ( is_wp_error( $count ) ) {
				\WP_CLI::warning( $count->get_error_message() );
				return;
			}

			\WP_CLI::success(
				sprintf(
					/* translators: 1: jobs count, 2: days threshold, 3: states list */
					__( 'Dry-run: %1$d job oltre %2$d giorni (stati: %3$s) verrebbero rimossi.', 'fp-multilanguage' ),
					(int) $count,
					$days,
					implode( ',', $states )
				)
			);
			return;
		}

		$deleted = $queue->cleanup_old_jobs( $states, $days, 'updated_at' );

		if ( is_wp_error( $deleted ) ) {
			\WP_CLI::warning( $deleted->get_error_message() );
			return;
		}

		\WP_CLI::success(
			sprintf(
				/* translators: 1: deleted jobs, 2: days threshold, 3: states list */
				__( 'Pulizia completata: %1$d job rimossi (>%2$d giorni, stati: %3$s).', 'fp-multilanguage' ),
				(int) $deleted,
				$days,
				implode( ',', $states )
			)
		);
	}
}
















