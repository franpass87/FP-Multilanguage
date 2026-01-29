<?php
/**
 * WP-CLI integration for FP Multilanguage.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.10.0 Refactored to use modular components.
 */

namespace FP\Multilanguage\CLI;

use FP\Multilanguage\CLI\Queue\QueueStatusHandler;
use FP\Multilanguage\CLI\Queue\QueueRunner;
use FP\Multilanguage\CLI\Queue\QueueManager;
use FP\Multilanguage\CLI\Queue\QueueEstimator;
use FP\Multilanguage\CLI\Queue\ReindexHandler;
use FP\Multilanguage\CLI\Utility\TranslationTester;
use FP\Multilanguage\CLI\Utility\SyncStatusChecker;
use FP\Multilanguage\CLI\Utility\TranslationExporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Manage the translation queue from the command line.
	 *
	 * @since 0.2.0
	 * @since 0.10.0 Refactored to use modular components.
	 */
	class QueueCommand extends \WP_CLI_Command {
		/**
		 * Status handler instance.
		 *
		 * @var QueueStatusHandler
		 */
		protected QueueStatusHandler $status_handler;

		/**
		 * Queue runner instance.
		 *
		 * @var QueueRunner
		 */
		protected QueueRunner $runner;

		/**
		 * Queue manager instance.
		 *
		 * @var QueueManager
		 */
		protected QueueManager $manager;

		/**
		 * Queue estimator instance.
		 *
		 * @var QueueEstimator
		 */
		protected QueueEstimator $estimator;

		/**
		 * Reindex handler instance.
		 *
		 * @var ReindexHandler
		 */
		protected ReindexHandler $reindex_handler;

		/**
		 * Constructor.
		 *
		 * @since 0.10.0
		 */
		public function __construct() {
			$this->status_handler = new QueueStatusHandler();
			$this->runner = new QueueRunner();
			$this->manager = new QueueManager();
			$this->estimator = new QueueEstimator();
			$this->reindex_handler = new ReindexHandler();
		}

		/**
		 * Ensure queue commands are available when assisted mode is disabled.
		 *
		 * @since 0.2.0
		 *
		 * @return void
		 */
		protected function ensure_queue_available(): void {
			$plugin = function_exists( 'fpml_get_plugin' ) ? fpml_get_plugin() : \FPML_Plugin::instance();

			if ( $plugin->is_assisted_mode() ) {
				\WP_CLI::error( __( 'Modalità assistita attiva (WPML/Polylang): la coda interna di FP Multilanguage è disabilitata.', 'fp-multilanguage' ) );
			}
		}

		/**
		 * Display queue status and scheduled events.
		 *
		 * @since 0.2.0
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 *
		 * @return void
		 */
		public function status( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			$this->ensure_queue_available();
			$this->status_handler->display_status();
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

			$this->runner->run( $show_progress, $batch_size );
		}

		/**
		 * Reset stuck jobs and release the processor lock.
		 *
		 * @since 0.2.0
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 *
		 * @return void
		 */
		public function reset( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			$this->ensure_queue_available();
			$this->manager->reset();
		}

		/**
		 * Reindex posts, terms and menus ensuring queue coverage.
		 *
		 * @since 0.2.0
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 *
		 * @return void
		 */
		public function reindex( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			$this->ensure_queue_available();
			$this->reindex_handler->reindex();
		}

		/**
		 * Reschedule outdated jobs.
		 *
		 * @since 0.2.0
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 *
		 * @return void
		 */
		public function resync( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			$this->ensure_queue_available();
			$this->manager->resync();
		}

		/**
		 * Estimate the cost of pending translations.
		 *
		 * @since 0.2.0
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
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

			$this->estimator->estimate_cost( $states, $max_jobs );
		}

		/**
		 * Purge completed jobs older than the retention window.
		 *
		 * @since 0.3.1
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 *
		 * @return void
		 */
		public function cleanup( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			$this->ensure_queue_available();

			$days = isset( $assoc_args['days'] ) ? max( 0, absint( $assoc_args['days'] ) ) : null;

			if ( isset( $assoc_args['states'] ) ) {
				$states = array_filter( array_map( 'sanitize_key', preg_split( '/[\s,]+/', (string) $assoc_args['states'] ) ) );
			} else {
				$states = null;
			}

			$dry_run = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

			$this->manager->cleanup( $days, $states, $dry_run );
		}
	}

	/**
	 * Additional CLI commands for utility operations.
	 *
	 * @since 0.10.0
	 */
	class UtilityCommand extends \WP_CLI_Command {
		/**
		 * Translation tester instance.
		 *
		 * @var TranslationTester
		 */
		protected TranslationTester $tester;

		/**
		 * Sync status checker instance.
		 *
		 * @var SyncStatusChecker
		 */
		protected SyncStatusChecker $status_checker;

		/**
		 * Translation exporter instance.
		 *
		 * @var TranslationExporter
		 */
		protected TranslationExporter $exporter;

		/**
		 * Constructor.
		 *
		 * @since 0.10.0
		 */
		public function __construct() {
			$this->tester = new TranslationTester();
			$this->status_checker = new SyncStatusChecker();
			$this->exporter = new TranslationExporter();
		}

		/**
		 * Ensure queue commands are available when assisted mode is disabled.
		 *
		 * @since 0.10.0
		 *
		 * @return void
		 */
		protected function ensure_queue_available(): void {
			$plugin = function_exists( 'fpml_get_plugin' ) ? fpml_get_plugin() : \FPML_Plugin::instance();

			if ( $plugin->is_assisted_mode() ) {
				\WP_CLI::error( __( 'Modalità assistita attiva (WPML/Polylang): la coda interna di FP Multilanguage è disabilitata.', 'fp-multilanguage' ) );
			}
		}

		/**
		 * Test translation for a single post.
		 *
		 * @since 0.10.0
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 *
		 * @return void
		 */
		public function test_translation( $args, $assoc_args ) {
			$this->ensure_queue_available();

			if ( empty( $args[0] ) ) {
				\WP_CLI::error( __( 'Specifica un post ID.', 'fp-multilanguage' ) );
			}

			$post_id = absint( $args[0] );
			$target_lang = isset( $assoc_args['lang'] ) ? sanitize_text_field( $assoc_args['lang'] ) : 'en';
			$dry_run = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

			$this->tester->test_translation( $post_id, $target_lang, $dry_run );
		}

		/**
		 * Check synchronization status for posts and terms.
		 *
		 * @since 0.10.0
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 *
		 * @return void
		 */
		public function sync_status( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			$this->ensure_queue_available();

			$post_type = isset( $assoc_args['post-type'] ) ? sanitize_key( $assoc_args['post-type'] ) : null;
			$taxonomy = isset( $assoc_args['taxonomy'] ) ? sanitize_key( $assoc_args['taxonomy'] ) : null;

			$this->status_checker->check_status( $post_type, $taxonomy );
		}

		/**
		 * Export translations to JSON file.
		 *
		 * @since 0.10.0
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 *
		 * @return void
		 */
		public function export_translations( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			$this->ensure_queue_available();

			$output_file = isset( $assoc_args['file'] ) ? sanitize_file_name( $assoc_args['file'] ) : null;
			$post_type = isset( $assoc_args['post-type'] ) ? sanitize_key( $assoc_args['post-type'] ) : null;
			$include_content = \WP_CLI\Utils\get_flag_value( $assoc_args, 'include-content', false );

			$this->exporter->export_translations( $output_file, $post_type, $include_content );
		}
	}

	\WP_CLI::add_command( 'fpml queue', QueueCommand::class );
	
	// Register utility commands with instance methods
	$utility_instance = new UtilityCommand();
	\WP_CLI::add_command( 'fpml test-translation', array( $utility_instance, 'test_translation' ) );
	\WP_CLI::add_command( 'fpml sync-status', array( $utility_instance, 'sync_status' ) );
	\WP_CLI::add_command( 'fpml export-translations', array( $utility_instance, 'export_translations' ) );
}

/**
 * CLI class placeholder for PSR-4 compatibility.
 *
 * @since 0.5.0
 */
class CLI {
	/**
	 * Initialize CLI commands.
	 *
	 * @return self
	 */
	public static function instance() {
		// Commands are registered in the WP_CLI block above
		return new self();
	}
}
