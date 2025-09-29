<?php
/**
 * WP-CLI integration for FP Multilanguage.
 *
 * @package FP_Multilanguage
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
        /**
         * Manage the translation queue from the command line.
         *
         * @since 0.2.0
         */
        class FPML_CLI_Queue_Command extends WP_CLI_Command {
                /**
                 * Ensure queue commands are available when assisted mode is disabled.
                 *
                 * @since 0.2.0
                 *
                 * @return void
                 */
                protected function ensure_queue_available() {
                        $plugin = FPML_Plugin::instance();

                        if ( $plugin->is_assisted_mode() ) {
                                WP_CLI::error( __( 'Modalità assistita attiva (WPML/Polylang): la coda interna di FP Multilanguage è disabilitata.', 'fp-multilanguage' ) );
                        }
                }

                /**
                 * Display queue status and scheduled events.
                 *
                 * @since 0.2.0
                 *
                 * @return void
                 */
                public function status( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                        $this->ensure_queue_available();

                        $queue      = FPML_Queue::instance();
                        $processor  = FPML_Processor::instance();
                        $state_data = $queue->get_state_counts();

                        $rows   = array();
                        $states = array( 'pending', 'translating', 'outdated', 'done', 'error', 'skipped' );

                        foreach ( $states as $state ) {
                                $rows[] = array(
                                        'stato'  => $state,
                                        'totale' => isset( $state_data[ $state ] ) ? (int) $state_data[ $state ] : 0,
                                );
                        }

                        if ( empty( $rows ) ) {
                                WP_CLI::log( 'Nessun job presente nella coda.' );
                        } else {
                                WP_CLI\Utils\format_items( 'table', $rows, array( 'stato', 'totale' ) );
                        }

                        $events = array(
                                'fpml_run_queue'       => wp_next_scheduled( 'fpml_run_queue' ),
                                'fpml_retry_failed'    => wp_next_scheduled( 'fpml_retry_failed' ),
                                'fpml_resync_outdated' => wp_next_scheduled( 'fpml_resync_outdated' ),
                        );

                        foreach ( $events as $hook => $timestamp ) {
                                if ( $timestamp ) {
                                        WP_CLI::line( sprintf( '%s: %s', $hook, date_i18n( 'Y-m-d H:i:s', $timestamp ) ) );
                                } else {
                                        WP_CLI::line( sprintf( '%s: non programmato', $hook ) );
                                }
                        }

                        WP_CLI::line( sprintf( 'Lock processor: %s', $processor->is_locked() ? 'attivo' : 'libero' ) );
                }

                /**
                 * Run a single processing batch.
                 *
                 * @since 0.2.0
                 *
                 * @return void
                 */
                public function run( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                        $this->ensure_queue_available();

                        $processor = FPML_Processor::instance();
                        $result    = $processor->run_queue();

                        if ( is_wp_error( $result ) ) {
                                WP_CLI::error( $result->get_error_message() );
                        }

                        if ( empty( $result['claimed'] ) ) {
                                WP_CLI::success( 'Nessun job disponibile in coda.' );

                                return;
                        }

                        WP_CLI::success(
                                sprintf(
                                        'Batch completato: %d processati, %d saltati, %d errori su %d job.',
                                        isset( $result['processed'] ) ? (int) $result['processed'] : 0,
                                        isset( $result['skipped'] ) ? (int) $result['skipped'] : 0,
                                        isset( $result['errors'] ) ? (int) $result['errors'] : 0,
                                        isset( $result['claimed'] ) ? (int) $result['claimed'] : 0
                                )
                        );
                }

                /**
                 * Reset stuck jobs and release the processor lock.
                 *
                 * @since 0.2.0
                 *
                 * @return void
                 */
                public function reset( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                        $this->ensure_queue_available();

                        $processor = FPML_Processor::instance();
                        $queue     = FPML_Queue::instance();

                        $processor->force_release_lock();
                        $reset = $queue->reset_states( array( 'translating' ) );

                        WP_CLI::success( sprintf( 'Lock rilasciato. Job ripristinati in pending: %d', (int) $reset ) );
                }

                /**
                 * Reindex posts, terms and menus ensuring queue coverage.
                 *
                 * @since 0.2.0
                 *
                 * @return void
                 */
                public function reindex( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                        $this->ensure_queue_available();

                        $plugin  = FPML_Plugin::instance();
                        $summary = $plugin->reindex_content();

                        if ( is_wp_error( $summary ) ) {
                                WP_CLI::error( $summary->get_error_message() );
                        }

                        WP_CLI::success(
                                sprintf(
                                        'Reindex completato: %1$d post analizzati (%2$d nuove traduzioni, %3$d enqueued), %4$d termini e %5$d menu sincronizzati.',
                                        isset( $summary['posts_scanned'] ) ? (int) $summary['posts_scanned'] : 0,
                                        isset( $summary['translations_created'] ) ? (int) $summary['translations_created'] : 0,
                                        isset( $summary['posts_enqueued'] ) ? (int) $summary['posts_enqueued'] : 0,
                                        isset( $summary['terms_scanned'] ) ? (int) $summary['terms_scanned'] : 0,
                                        isset( $summary['menus_synced'] ) ? (int) $summary['menus_synced'] : 0
                                )
                        );
                }

                /**
                 * Reschedule outdated jobs.
                 *
                 * @since 0.2.0
                 *
                 * @return void
                 */
                public function resync( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                        $this->ensure_queue_available();

                        $processor = FPML_Processor::instance();
                        $updated   = $processor->resync_outdated_jobs();

                        WP_CLI::success( sprintf( 'Job outdated riportati in pending: %d', (int) $updated ) );
                }

                /**
                 * Estimate the cost of pending translations.
                 *
                 * @since 0.2.0
                 *
                 * @return void
                 */
                public function estimate_cost( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                        $this->ensure_queue_available();

                        $plugin   = FPML_Plugin::instance();
                        $estimate = $plugin->estimate_queue_cost();

                        if ( is_wp_error( $estimate ) ) {
                                WP_CLI::error( $estimate->get_error_message() );
                        }

                        if ( empty( $estimate['jobs_scanned'] ) ) {
                                WP_CLI::success( 'Nessun job da stimare.' );

                                return;
                        }

                        $characters = isset( $estimate['characters'] ) ? (int) $estimate['characters'] : 0;
                        $cost       = isset( $estimate['estimated_cost'] ) ? (float) $estimate['estimated_cost'] : 0.0;
                        $jobs       = isset( $estimate['jobs_scanned'] ) ? (int) $estimate['jobs_scanned'] : 0;
                        $words      = isset( $estimate['word_count'] ) ? (int) $estimate['word_count'] : 0;

                        WP_CLI::success(
                                sprintf(
                                        'Caratteri stimati: %1$d — parole: %2$d — costo previsto: %3$.4f su %4$d job analizzati.',
                                        $characters,
                                        $words,
                                        $cost,
                                        $jobs
                                )
                        );
                }
        }

        WP_CLI::add_command( 'fpml queue', 'FPML_CLI_Queue_Command' );
}
