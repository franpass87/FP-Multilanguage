<?php
/**
 * CLI Queue Estimator - Estimates translation costs.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\CLI\Queue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Estimates translation costs.
 *
 * @since 0.10.0
 */
class QueueEstimator {
	/**
	 * Estimate the cost of pending translations.
	 *
	 * @since 0.10.0
	 *
	 * @param array|null $states   States to estimate. Default: pending, outdated, translating.
	 * @param int        $max_jobs Maximum number of jobs to analyze.
	 *
	 * @return void
	 */
	public function estimate_cost( ?array $states = null, int $max_jobs = 500 ): void {
		$plugin = function_exists( 'fpml_get_plugin' ) ? fpml_get_plugin() : \FPML_Plugin::instance();
		$estimate = $plugin->estimate_queue_cost( $states, $max_jobs );

		if ( is_wp_error( $estimate ) ) {
			\WP_CLI::error( $estimate->get_error_message() );
		}

		if ( empty( $estimate['jobs_scanned'] ) ) {
			\WP_CLI::success( __( 'Nessun job da stimare.', 'fp-multilanguage' ) );
			return;
		}

		$characters   = isset( $estimate['characters'] ) ? (int) $estimate['characters'] : 0;
		$cost         = isset( $estimate['estimated_cost'] ) ? (float) $estimate['estimated_cost'] : 0.0;
		$jobs         = isset( $estimate['jobs_scanned'] ) ? (int) $estimate['jobs_scanned'] : 0;
		$words        = isset( $estimate['word_count'] ) ? (int) $estimate['word_count'] : 0;
		$states_label = $states ? implode( ',', $states ) : 'pending,outdated,translating';

		\WP_CLI::success(
			sprintf(
				/* translators: 1: characters, 2: words, 3: estimated cost, 4: jobs analysed, 5: states list, 6: max jobs */
				__( 'Caratteri stimati: %1$d — parole: %2$d — costo previsto: %3$.4f su %4$d job analizzati (stati: %5$s, max %6$d job).', 'fp-multilanguage' ),
				$characters,
				$words,
				$cost,
				$jobs,
				$states_label,
				$max_jobs
			)
		);
	}
}
















