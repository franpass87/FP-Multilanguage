<?php
/**
 * WP-CLI integration for FP Multilanguage.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Extended CLI commands for FP Multilanguage.
	 *
	 * @since 0.5.0
	 */
	class FPML_CLI_Extended_Command extends WP_CLI_Command {

		/**
		 * Test all translation providers.
		 *
		 * ## OPTIONS
		 *
		 * [--text=<text>]
		 * : Text to translate for testing
		 * ---
		 * default: "Ciao mondo, questo è un test."
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *     wp fpml provider test --all
		 *     wp fpml provider test --text="Benvenuto"
		 *
		 * @subcommand provider-test
		 */
		public function provider_test( $args, $assoc_args ) {
			$text = isset( $assoc_args['text'] ) ? $assoc_args['text'] : 'Ciao mondo, questo è un test.';
			
			$providers = array( 'openai', 'deepl', 'google', 'libretranslate' );
			$results = array();

			WP_CLI::line( 'Testing translation providers...' );
			WP_CLI::line( "Text: $text" );
			WP_CLI::line( '' );

			foreach ( $providers as $provider ) {
				WP_CLI::line( "Testing $provider..." );
				
				$start = microtime( true );
				
				try {
					$translation_manager = FPML_Container::resolve( 'translation_manager' );
					$translated = $translation_manager->translate_text( $text, 'it', 'en', $provider );
					$elapsed = microtime( true ) - $start;

					if ( is_wp_error( $translated ) ) {
						$results[] = array(
							'provider' => $provider,
							'status' => '❌ Failed',
							'translation' => $translated->get_error_message(),
							'time' => '-',
						);
					} else {
						$results[] = array(
							'provider' => $provider,
							'status' => '✅ Success',
							'translation' => $translated,
							'time' => round( $elapsed, 2 ) . 's',
						);
					}
				} catch ( Exception $e ) {
					$results[] = array(
						'provider' => $provider,
						'status' => '❌ Error',
						'translation' => $e->getMessage(),
						'time' => '-',
					);
				}
			}

			WP_CLI::line( '' );
			\WP_CLI\Utils\format_items( 'table', $results, array( 'provider', 'status', 'translation', 'time' ) );
		}

		/**
		 * Get translation cache statistics.
		 *
		 * ## EXAMPLES
		 *
		 *     wp fpml cache stats
		 *
		 * @subcommand cache-stats
		 */
		public function cache_stats() {
			$cache = FPML_Container::resolve( 'translation_cache' );
			
			if ( ! $cache ) {
				WP_CLI::error( 'Translation cache not available.' );
			}

			$stats = $cache->get_stats();

			WP_CLI::line( 'Translation Cache Statistics:' );
			WP_CLI::line( '' );
			WP_CLI::line( "Total entries: {$stats['total_entries']}" );
			WP_CLI::line( "Cache hits: {$stats['hits']}" );
			WP_CLI::line( "Cache misses: {$stats['misses']}" );
			WP_CLI::line( "Hit rate: {$stats['hit_rate']}%" );
			WP_CLI::line( "Total saved: \${$stats['cost_saved']}" );
		}

		/**
		 * Rollback a translation to a previous version.
		 *
		 * ## OPTIONS
		 *
		 * --post=<post_id>
		 * : Post ID to rollback
		 *
		 * --version=<version_id>
		 * : Version ID to rollback to
		 *
		 * ## EXAMPLES
		 *
		 *     wp fpml rollback --post=123 --version=5
		 *
		 * @subcommand rollback
		 */
		public function rollback( $args, $assoc_args ) {
			if ( ! isset( $assoc_args['post'] ) || ! isset( $assoc_args['version'] ) ) {
				WP_CLI::error( 'Both --post and --version are required.' );
			}

			$post_id = intval( $assoc_args['post'] );
			$version_id = intval( $assoc_args['version'] );

			$versioning = FPML_Container::resolve( 'translation_versioning' );
			
			if ( ! $versioning ) {
				WP_CLI::error( 'Translation versioning not available.' );
			}

			WP_CLI::line( "Rolling back post $post_id to version $version_id..." );

			$result = $versioning->rollback( $version_id );

			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
			}

			WP_CLI::success( "Successfully rolled back post $post_id to version $version_id." );
		}

		/**
		 * Export translations to various formats.
		 *
		 * ## OPTIONS
		 *
		 * --format=<format>
		 * : Export format (json, csv, tmx)
		 *
		 * [--days=<days>]
		 * : Number of days to export
		 * ---
		 * default: 30
		 * ---
		 *
		 * [--file=<file>]
		 * : Output file path
		 *
		 * ## EXAMPLES
		 *
		 *     wp fpml export --format=json --days=30 --file=translations.json
		 *     wp fpml export --format=tmx --file=memory.tmx
		 *
		 * @subcommand export
		 */
		public function export( $args, $assoc_args ) {
			$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'json';
			$days = isset( $assoc_args['days'] ) ? intval( $assoc_args['days'] ) : 30;
			$file = isset( $assoc_args['file'] ) ? $assoc_args['file'] : null;

			WP_CLI::line( "Exporting translations (last $days days) to $format format..." );

			$content = '';

			switch ( $format ) {
				case 'tmx':
					$tm = FPML_Container::resolve( 'translation_memory' );
					if ( ! $tm ) {
						WP_CLI::error( 'Translation memory not available.' );
					}
					$content = $tm->export_to_tmx();
					break;

				case 'csv':
					$analytics = FPML_Container::resolve( 'analytics_dashboard' );
					if ( ! $analytics ) {
						WP_CLI::error( 'Analytics not available.' );
					}
					// Export analytics data as CSV
					$data = $analytics->get_analytics_data( $days );
					$content = $this->array_to_csv( $data['recent'] );
					break;

				case 'json':
				default:
					$analytics = FPML_Container::resolve( 'analytics_dashboard' );
					if ( ! $analytics ) {
						WP_CLI::error( 'Analytics not available.' );
					}
					$data = $analytics->get_analytics_data( $days );
					$content = wp_json_encode( $data, JSON_PRETTY_PRINT );
					break;
			}

			if ( $file ) {
				file_put_contents( $file, $content );
				WP_CLI::success( "Exported to $file" );
			} else {
				WP_CLI::line( $content );
			}
		}

		/**
		 * Get Translation Memory statistics.
		 *
		 * ## EXAMPLES
		 *
		 *     wp fpml tm stats
		 *
		 * @subcommand tm-stats
		 */
		public function tm_stats() {
			$tm = FPML_Container::resolve( 'translation_memory' );
			
			if ( ! $tm ) {
				WP_CLI::error( 'Translation memory not available.' );
			}

			$stats = $tm->get_stats();

			WP_CLI::line( 'Translation Memory Statistics:' );
			WP_CLI::line( '' );
			WP_CLI::line( "Total segments: " . number_format( $stats['total_segments'] ) );
			WP_CLI::line( "Recent matches: " . number_format( $stats['recent_matches'] ) );
			WP_CLI::line( "Cost saved: $" . number_format( $stats['estimated_savings'], 2 ) );
			WP_CLI::line( "Avg fuzzy similarity: {$stats['avg_fuzzy_similarity']}%" );
			WP_CLI::line( '' );

			if ( ! empty( $stats['top_segments'] ) ) {
				WP_CLI::line( 'Top 10 reused segments:' );
				\WP_CLI\Utils\format_items( 'table', $stats['top_segments'], array( 'source_text', 'use_count' ) );
			}
		}

		/**
		 * Clear various caches.
		 *
		 * ## OPTIONS
		 *
		 * <type>
		 * : Cache type to clear (translation, all)
		 *
		 * ## EXAMPLES
		 *
		 *     wp fpml clear translation
		 *     wp fpml clear all
		 *
		 * @subcommand clear
		 */
		public function clear( $args ) {
			$type = isset( $args[0] ) ? $args[0] : 'all';

			switch ( $type ) {
				case 'translation':
					$cache = FPML_Container::resolve( 'translation_cache' );
					if ( $cache ) {
						$cache->clear_all();
						WP_CLI::success( 'Translation cache cleared.' );
					}
					break;

				case 'all':
					$cache = FPML_Container::resolve( 'translation_cache' );
					if ( $cache ) {
						$cache->clear_all();
					}
					wp_cache_flush();
					WP_CLI::success( 'All caches cleared.' );
					break;

				default:
					WP_CLI::error( "Invalid cache type: $type" );
			}
		}

		/**
		 * Convert array to CSV format.
		 *
		 * @param array $data Data array.
		 * @return string CSV content.
		 */
		private function array_to_csv( $data ) {
			if ( empty( $data ) ) {
				return '';
			}

			$csv = '';
			$headers = array_keys( $data[0] );
			$csv .= implode( ',', $headers ) . "\n";

			foreach ( $data as $row ) {
				$csv .= implode( ',', array_map( function( $value ) {
					return '"' . str_replace( '"', '""', $value ) . '"';
				}, $row ) ) . "\n";
			}

			return $csv;
		}
	}

	WP_CLI::add_command( 'fpml', 'FPML_CLI_Extended_Command' );

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
                                WP_CLI::log( __( 'Nessun job presente nella coda.', 'fp-multilanguage' ) );
                        } else {
                                WP_CLI\Utils\format_items( 'table', $rows, array( 'stato', 'totale' ) );
                        }

                        $events = array(
                                'fpml_run_queue'       => wp_next_scheduled( 'fpml_run_queue' ),
                                'fpml_retry_failed'    => wp_next_scheduled( 'fpml_retry_failed' ),
                                'fpml_resync_outdated' => wp_next_scheduled( 'fpml_resync_outdated' ),
                                'fpml_cleanup_queue'   => wp_next_scheduled( 'fpml_cleanup_queue' ),
                        );

                        foreach ( $events as $hook => $timestamp ) {
                                if ( $timestamp ) {
                                        WP_CLI::line(
                                                sprintf(
                                                        /* translators: 1: hook name, 2: formatted datetime */
                                                        __( '%1$s: %2$s', 'fp-multilanguage' ),
                                                        $hook,
                                                        date_i18n( 'Y-m-d H:i:s', $timestamp )
                                                )
                                        );
                                } else {
                                        WP_CLI::line(
                                                sprintf(
                                                        /* translators: %s: hook name */
                                                        __( '%s: non programmato', 'fp-multilanguage' ),
                                                        $hook
                                                )
                                        );
                                }
                        }

                        WP_CLI::line(
                                sprintf(
                                        /* translators: %s: lock status label */
                                        __( 'Lock processor: %s', 'fp-multilanguage' ),
                                        $processor->is_locked() ? __( 'attivo', 'fp-multilanguage' ) : __( 'libero', 'fp-multilanguage' )
                                )
                        );

                        $settings     = FPML_Settings::instance();
                        $provider_map = array(
                                'openai'         => 'OpenAI',
                                'deepl'          => 'DeepL',
                                'google'         => 'Google Cloud Translation',
                                'libretranslate' => 'LibreTranslate',
                        );

                        $provider_slug = $settings ? $settings->get( 'provider', '' ) : '';
                        $provider_name = isset( $provider_map[ $provider_slug ] ) ? $provider_map[ $provider_slug ] : ( '' !== $provider_slug ? ucfirst( $provider_slug ) : __( 'Nessun provider', 'fp-multilanguage' ) );

                        $translator_instance = $processor->get_translator_instance();

                        if ( is_wp_error( $translator_instance ) ) {
                                WP_CLI::warning(
                                        sprintf(
                                                /* translators: 1: provider label, 2: error message */
                                                __( 'Provider %1$s non configurato: %2$s', 'fp-multilanguage' ),
                                                $provider_name,
                                                $translator_instance->get_error_message()
                                        )
                                );
                        } else {
                                WP_CLI::line(
                                        sprintf(
                                                /* translators: %s: provider label */
                                                __( 'Provider configurato: %s', 'fp-multilanguage' ),
                                                $provider_name
                                        )
                                );
                        }

                        $age_summary = FPML_Plugin::instance()->get_queue_age_summary();
                        $retention   = isset( $age_summary['retention_days'] ) ? (int) $age_summary['retention_days'] : 0;
                        $states      = isset( $age_summary['cleanup_states'] ) ? (array) $age_summary['cleanup_states'] : array();

                        if ( $retention > 0 && ! empty( $states ) ) {
                                WP_CLI::line(
                                        sprintf(
                                                /* translators: 1: days, 2: comma separated states */
                                                __( 'Retention automatica: %1$d giorni (%2$s)', 'fp-multilanguage' ),
                                                $retention,
                                                implode( ',', $states )
                                        )
                                );
                        } else {
                                WP_CLI::line( __( 'Retention automatica: disattivata', 'fp-multilanguage' ) );
                        }

                        if ( ! empty( $age_summary['pending'] ) && isset( $age_summary['pending']['age'] ) ) {
                                WP_CLI::line(
                                        sprintf(
                                                /* translators: 1: human readable age, 2: local datetime */
                                                __( 'Job in attesa più vecchio: %1$s fa (%2$s)', 'fp-multilanguage' ),
                                                $age_summary['pending']['age'],
                                                $age_summary['pending']['datetime_local']
                                        )
                                );
                        }

                        if ( ! empty( $age_summary['completed'] ) && isset( $age_summary['completed']['age'] ) ) {
                                WP_CLI::line(
                                        sprintf(
                                                /* translators: 1: human readable age, 2: local datetime */
                                                __( 'Job completato più vecchio ancora archiviato: %1$s fa (%2$s)', 'fp-multilanguage' ),
                                                $age_summary['completed']['age'],
                                                $age_summary['completed']['datetime_local']
                                        )
                                );
                        }
                }

                /**
                 * Run a single processing batch.
                 *
                 * @since 0.2.0
                 *
                 * @param array $args       Positional arguments.
                 * @param array $assoc_args Associative arguments.
                 *
                 * @return void
                 */
                public function run( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
                        $this->ensure_queue_available();

                        // Check if progress bar should be shown
                        $show_progress = isset( $assoc_args['progress'] ) && $assoc_args['progress'];
                        
                        // Get batch size if specified
                        $batch_size = isset( $assoc_args['batch'] ) ? absint( $assoc_args['batch'] ) : 0;

                        if ( $show_progress && $batch_size > 0 ) {
                                // With progress bar
                                $this->run_with_progress( $batch_size );
                        } else {
                                // Original behavior
                                $processor = FPML_Processor::instance();
                                $result    = $processor->run_queue();

                                if ( is_wp_error( $result ) ) {
                                        WP_CLI::error( $result->get_error_message() );
                                }

                                if ( empty( $result['claimed'] ) ) {
                                        WP_CLI::success( __( 'Nessun job disponibile in coda.', 'fp-multilanguage' ) );

                                        return;
                                }

                                WP_CLI::success(
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
                 * @since 0.3.2
                 *
                 * @param int $batch_size Number of jobs to process.
                 *
                 * @return void
                 */
                protected function run_with_progress( $batch_size ) {
                        $queue = FPML_Queue::instance();
                        $processor = FPML_Processor::instance();

                        // Get jobs to process
                        $jobs = $queue->get_next_jobs( $batch_size );

                        if ( empty( $jobs ) ) {
                                WP_CLI::success( __( 'Nessun job disponibile in coda.', 'fp-multilanguage' ) );
                                return;
                        }

                        $total = count( $jobs );
                        WP_CLI::log( sprintf( __( 'Processando %d job...', 'fp-multilanguage' ), $total ) );

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

                        WP_CLI::success(
                                sprintf(
                                        /* translators: 1: processed jobs, 2: skipped jobs, 3: errored jobs */
                                        __( 'Batch completato: %1$d processati, %2$d saltati, %3$d errori.', 'fp-multilanguage' ),
                                        $stats['processed'],
                                        $stats['skipped'],
                                        $stats['errors']
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

                        WP_CLI::success(
                                sprintf(
                                        /* translators: %d: number of jobs reset */
                                        __( 'Lock rilasciato. Job ripristinati in pending: %d', 'fp-multilanguage' ),
                                        (int) $reset
                                )
                        );
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
                                        /* translators: 1: posts scanned, 2: translations created, 3: posts enqueued, 4: terms scanned, 5: menus synced */
                                        __( 'Reindex completato: %1$d post analizzati (%2$d nuove traduzioni, %3$d enqueued), %4$d termini e %5$d menu sincronizzati.', 'fp-multilanguage' ),
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

                        WP_CLI::success(
                                sprintf(
                                        /* translators: %d: number of jobs */
                                        __( 'Job outdated riportati in pending: %d', 'fp-multilanguage' ),
                                        (int) $updated
                                )
                        );
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

                        $states = null;

                        if ( ! empty( $assoc_args['states'] ) ) {
                                $states = array_filter( array_map( 'sanitize_key', preg_split( '/[\s,]+/', (string) $assoc_args['states'] ) ) );
                        }

                        $max_jobs = isset( $assoc_args['max-jobs'] ) ? max( 1, absint( $assoc_args['max-jobs'] ) ) : 500;

                        $plugin   = FPML_Plugin::instance();
                        $estimate = $plugin->estimate_queue_cost( $states, $max_jobs );

                        if ( is_wp_error( $estimate ) ) {
                                WP_CLI::error( $estimate->get_error_message() );
                        }

                        if ( empty( $estimate['jobs_scanned'] ) ) {
                                WP_CLI::success( __( 'Nessun job da stimare.', 'fp-multilanguage' ) );

                                return;
                        }

                        $characters   = isset( $estimate['characters'] ) ? (int) $estimate['characters'] : 0;
                        $cost         = isset( $estimate['estimated_cost'] ) ? (float) $estimate['estimated_cost'] : 0.0;
                        $jobs         = isset( $estimate['jobs_scanned'] ) ? (int) $estimate['jobs_scanned'] : 0;
                        $words        = isset( $estimate['word_count'] ) ? (int) $estimate['word_count'] : 0;
                        $states_label = $states ? implode( ',', $states ) : 'pending,outdated,translating';

                        WP_CLI::success(
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

                /**
                 * Purge completed jobs older than the retention window.
                 *
                 * ## OPTIONS
                 *
                 * [--days=<days>]
                 * : Numero di giorni da conservare. Predefinito: impostazione del plugin.
                 *
                 * [--states=<states>]
                 * : Stati da rimuovere, separati da virgola. Predefinito: hook fpml_queue_cleanup_states.
                 *
                 * [--dry-run]
                 * : Mostra quanti job verrebbero rimossi senza eseguire la DELETE.
                 *
                 * @since 0.3.1
                 *
                 * @param array $args       Positional arguments.
                 * @param array $assoc_args Associative arguments.
                 *
                 * @return void
                 */
                public function cleanup( $args, $assoc_args ) {
                        $this->ensure_queue_available();

                        $settings     = FPML_Settings::instance();
                        $default_days = $settings ? (int) $settings->get( 'queue_retention_days', 0 ) : 0;
                        $days         = isset( $assoc_args['days'] ) ? max( 0, absint( $assoc_args['days'] ) ) : $default_days;

                        if ( $days <= 0 ) {
                                WP_CLI::error( __( 'Specifica un numero di giorni maggiore di zero o configura la retention dalle impostazioni.', 'fp-multilanguage' ) );
                        }

                        if ( isset( $assoc_args['states'] ) ) {
                                $states = array_filter( array_map( 'sanitize_key', preg_split( '/[\s,]+/', (string) $assoc_args['states'] ) ) );
                        } else {
                                $age_summary = FPML_Plugin::instance()->get_queue_age_summary();
                                $states      = isset( $age_summary['cleanup_states'] ) ? (array) $age_summary['cleanup_states'] : array();
                        }

                        if ( empty( $states ) ) {
                                WP_CLI::error( __( 'Nessuno stato valido specificato per la pulizia.', 'fp-multilanguage' ) );
                        }

                        $queue   = FPML_Queue::instance();
                        $dry_run = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

                        if ( $dry_run ) {
                                $count = $queue->count_old_jobs( $states, $days, 'updated_at' );

                                if ( is_wp_error( $count ) ) {
                                        WP_CLI::warning( $count->get_error_message() );

                                        return;
                                }

                                WP_CLI::success(
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
                                WP_CLI::warning( $deleted->get_error_message() );

                                return;
                        }

                        WP_CLI::success(
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

        WP_CLI::add_command( 'fpml queue', 'FPML_CLI_Queue_Command' );
}
