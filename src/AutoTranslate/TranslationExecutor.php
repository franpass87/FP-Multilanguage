<?php
/**
 * Auto Translate Translation Executor - Executes immediate translations.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\AutoTranslate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executes immediate translations.
 *
 * @since 0.10.0
 */
class TranslationExecutor {
	/**
	 * Logger instance.
	 *
	 * @var \FP\Multilanguage\Logger
	 */
	protected $logger;

	/**
	 * Queue instance.
	 *
	 * @var \FPML_Queue
	 */
	protected $queue;

	/**
	 * Constructor.
	 *
	 * @param \FP\Multilanguage\Logger $logger Logger instance.
	 * @param \FPML_Queue               $queue  Queue instance.
	 */
	public function __construct( $logger, $queue ) {
		$this->logger = $logger;
		$this->queue = $queue;
	}

	/**
	 * Translate a post immediately in priority mode.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_Post $post Post to translate.
	 * @return void
	 */
	public function translate_immediately( \WP_Post $post ): void {
		// Create/get translated post
		$target_post = null;
		
		if ( class_exists( '\FPML_Translation_Manager' ) ) {
			$translation_manager = \FPML_Translation_Manager::instance();
			
			// Check if translation exists first for first enabled language
			$language_manager = fpml_get_language_manager();
			$enabled_languages = $language_manager->get_enabled_languages();
			$target_lang = ! empty( $enabled_languages ) ? $enabled_languages[0] : 'en';
			
			$existing_id = 0;
			if ( method_exists( $translation_manager, 'get_translation_id' ) ) {
				$existing_id = $translation_manager->get_translation_id( $post->ID, $target_lang );
			}
			// Backward compatibility
			if ( ! $existing_id && 'en' === $target_lang ) {
				$existing_id = (int) get_post_meta( $post->ID, '_fpml_pair_id', true );
			}
			
			if ( $existing_id ) {
				$target_post = get_post( $existing_id );
			} elseif ( method_exists( $translation_manager, 'create_post_translation' ) ) {
				// Create translation explicitly for auto-translate
				$target_post = $translation_manager->create_post_translation( $post, $target_lang, 'draft' );
			}
		}

		if ( ! $target_post ) {
			$this->logger->log(
				'error',
				sprintf( 'Impossibile creare traduzione per post #%d', $post->ID ),
				array( 'post_id' => $post->ID )
			);
			return;
		}

		// Enqueue job with priority
		if ( class_exists( '\FPML_Plugin' ) ) {
			$plugin = function_exists( 'fpml_get_plugin' ) ? fpml_get_plugin() : \FPML_Plugin::instance();
			if ( method_exists( $plugin, 'enqueue_post_jobs' ) ) {
				$plugin->enqueue_post_jobs( $post, $target_post, false );
			}
		}

		// Force immediate queue execution (max 10 seconds)
		$this->run_queue_with_timeout( 10 );

		// Publish translated post if translation is complete
		$this->maybe_publish_translation( $target_post );

		$this->logger->log(
			'info',
			sprintf( 'Post #%d tradotto automaticamente alla pubblicazione', $post->ID ),
			array(
				'source_id' => $post->ID,
				'target_id' => $target_post->ID,
			)
		);
	}

	/**
	 * Run queue with timeout.
	 *
	 * @since 0.10.0
	 *
	 * @param int $timeout_seconds Timeout in seconds.
	 * @return void
	 */
	protected function run_queue_with_timeout( int $timeout_seconds ): void {
		$start_time = time();
		$processed  = 0;

		while ( ( time() - $start_time ) < $timeout_seconds ) {
			$processor = class_exists( '\FPML_Processor' ) ? \FPML_fpml_get_processor() : null;
			$result = $processor ? $processor->run_queue() : null;

			if ( is_wp_error( $result ) ) {
				break;
			}

			if ( isset( $result['claimed'] ) && 0 === $result['claimed'] ) {
				// No jobs to process
				break;
			}

			$processed += isset( $result['processed'] ) ? $result['processed'] : 0;

			// Brief pause to avoid rate limit
			usleep( 100000 ); // 0.1 seconds
		}

		$this->logger->log(
			'debug',
			sprintf( 'Processati %d job in %d secondi', $processed, time() - $start_time ),
			array( 'processed' => $processed, 'duration' => time() - $start_time )
		);
	}

	/**
	 * Publish translated post if all jobs are completed.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_Post $target_post Translated post.
	 * @return void
	 */
	protected function maybe_publish_translation( \WP_Post $target_post ): void {
		// Check if there are pending jobs for this post
		global $wpdb;
		$table = $this->queue->get_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$pending = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE object_type = %s AND object_id = %d AND state IN ('pending', 'translating')",
				'post',
				$target_post->ID
			)
		);

		if ( 0 === (int) $pending && 'publish' !== $target_post->post_status ) {
			// Publish translated post
			\fpml_safe_update_post(
				array(
					'ID'          => $target_post->ID,
					'post_status' => 'publish',
				)
			);

			$this->logger->log(
				'info',
				sprintf( 'Post tradotto #%d pubblicato automaticamente', $target_post->ID ),
				array( 'post_id' => $target_post->ID )
			);
		}
	}
}















