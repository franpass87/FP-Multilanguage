<?php
/**
 * CLI Queue Runner - Handles queue execution with progress bar support.
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
 * Handles queue execution with progress bar support.
 *
 * @since 0.10.0
 */
class QueueRunner {
	/**
	 * Run a single processing batch.
	 *
	 * @since 0.10.0
	 *
	 * @param bool $show_progress Whether to show progress bar.
	 * @param int  $batch_size    Number of jobs to process.
	 *
	 * @return void
	 */
	public function run( bool $show_progress = false, int $batch_size = 0 ): void {
		if ( $show_progress && $batch_size > 0 ) {
			// With progress bar
			$this->run_with_progress( $batch_size );
		} else {
			// Original behavior
			$processor = \FPML_fpml_get_processor();
			$result    = $processor->run_queue();

			if ( is_wp_error( $result ) ) {
				\WP_CLI::error( $result->get_error_message() );
			}

			if ( empty( $result['claimed'] ) ) {
				\WP_CLI::success( __( 'Nessun job disponibile in coda.', 'fp-multilanguage' ) );
				return;
			}

			\WP_CLI::success(
				sprintf(
					/* translators: 1: processed jobs, 2: skipped jobs, 3: errored jobs, 4: claimed jobs */
					__( 'Batch completato: %1$d processati, %2$d saltati, %3$d errori su %4$d job.', 'fp-multilanguage' ),
					isset( $result['processed'] ) ? (int) $result['processed'] : 0,
					isset( $result['skipped'] ) ? (int) $result['skipped'] : 0,
					isset( $result['errors'] ) ? (int) $result['errors'] : 0,
					isset( $result['claimed'] ) ? (int) $result['claimed'] : 0
				)
			);
		}
	}

	/**
	 * Run queue with progress bar.
	 *
	 * @since 0.10.0
	 *
	 * @param int $batch_size Number of jobs to process.
	 *
	 * @return void
	 */
	protected function run_with_progress( int $batch_size ): void {
		$queue = fpml_get_queue();
		$processor = \FPML_fpml_get_processor();

		// Get jobs to process
		$jobs = $queue->get_next_jobs( $batch_size );

		if ( empty( $jobs ) ) {
			\WP_CLI::success( __( 'Nessun job disponibile in coda.', 'fp-multilanguage' ) );
			return;
		}

		$total = count( $jobs );
		\WP_CLI::log( sprintf( __( 'Processando %d job...', 'fp-multilanguage' ), $total ) );

		// Create progress bar
		$progress = \WP_CLI\Utils\make_progress_bar( __( 'Traduzioni', 'fp-multilanguage' ), $total );

		$stats = array(
			'processed' => 0,
			'skipped'   => 0,
			'errors'    => 0,
		);

		foreach ( $jobs as $job ) {
			$result = $processor->process_job( $job );

			if ( is_wp_error( $result ) ) {
				$stats['errors']++;
			} elseif ( 'skipped' === $result ) {
				$stats['skipped']++;
			} else {
				$stats['processed']++;
			}

			$progress->tick();
		}

		$progress->finish();

		\WP_CLI::success(
			sprintf(
				/* translators: 1: processed jobs, 2: skipped jobs, 3: errored jobs */
				__( 'Batch completato: %1$d processati, %2$d saltati, %3$d errori.', 'fp-multilanguage' ),
				$stats['processed'],
				$stats['skipped'],
				$stats['errors']
			)
		);
	}
}
















