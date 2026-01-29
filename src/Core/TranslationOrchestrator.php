<?php
/**
 * Translation Orchestrator - Manages translation workflow.
 *
 * Extracted from Plugin.php to follow Single Responsibility Principle.
 *
 * @package FP_Multilanguage
 * @since 0.10.0
 */

namespace FP\Multilanguage\Core;

use FP\Multilanguage\Content\TranslationManager;
use FP\Multilanguage\Translation\JobEnqueuer;
use FP\Multilanguage\Queue;
use FP\Multilanguage\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrates translation workflow for posts, terms, and other content types.
 *
 * @since 0.10.0
 */
class TranslationOrchestrator {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Translation manager instance.
	 *
	 * @var TranslationManager
	 */
	protected $translation_manager;

	/**
	 * Job enqueuer instance.
	 *
	 * @var JobEnqueuer
	 */
	protected $job_enqueuer;

	/**
	 * Queue instance.
	 *
	 * @var Queue
	 */
	protected $queue;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Get singleton instance.
	 *
	 * @since 0.10.0
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 0.10.0
	 */
	protected function __construct() {
		$this->translation_manager = Container::get( 'translation_manager' ) 
			?: fpml_get_translation_manager();
		$this->job_enqueuer = Container::get( 'job_enqueuer' ) 
			?: ( class_exists( JobEnqueuer::class ) ? fpml_get_job_enqueuer() : null );
		$this->queue = Container::get( 'queue' ) ?: fpml_get_queue();
		$this->logger = Container::get( 'logger' ) ?: fpml_get_logger();
	}

	/**
	 * Enqueue jobs after translation is created.
	 *
	 * @since 0.10.0
	 *
	 * @param int $target_id Translated post ID.
	 * @param int $source_id Source post ID.
	 * @return void
	 */
	public function enqueue_jobs_after_translation( int $target_id, int $source_id ): void {
		if ( ! $this->job_enqueuer ) {
			return;
		}

		if ( $this->logger ) {
			$this->logger->debug( 
				'enqueue_jobs_after_translation called', 
				array( 
					'target_id' => $target_id, 
					'source_id' => $source_id 
				) 
			);
		}

		// Enqueue translation jobs for the newly created translation
		try {
			$this->job_enqueuer->enqueue_post_jobs( $source_id, $target_id );

			if ( $this->logger ) {
				$this->logger->debug( 
					'Jobs enqueued successfully', 
					array( 
						'source_id' => $source_id, 
						'target_id' => $target_id 
					) 
				);
			}
		} catch ( \Exception $e ) {
			if ( $this->logger ) {
				$this->logger->error( 
					'Failed to enqueue jobs after translation', 
					array( 
						'error' => $e->getMessage(),
						'source_id' => $source_id,
						'target_id' => $target_id,
					) 
				);
			}
		}
	}

	/**
	 * Sync taxonomies between source and translated post.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_Post $source_post Source post object.
	 * @param \WP_Post $target_post Translated post object.
	 * @return void
	 */
	public function sync_post_taxonomies( \WP_Post $source_post, \WP_Post $target_post ): void {
		if ( ! $this->translation_manager ) {
			return;
		}

		$taxonomies = get_object_taxonomies( $source_post->post_type, 'objects' );

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! $taxonomy->public || ! $taxonomy->show_ui ) {
				continue;
			}

			$source_terms = wp_get_post_terms( $source_post->ID, $taxonomy->name, array( 'fields' => 'ids' ) );

			if ( is_wp_error( $source_terms ) || empty( $source_terms ) ) {
				continue;
			}

			$translated_term_ids = array();

			foreach ( $source_terms as $term_id ) {
				$translated_term_id = $this->translation_manager->ensure_term_translation( 
					$term_id, 
					$taxonomy->name, 
					'en' 
				);

				if ( $translated_term_id ) {
					$translated_term_ids[] = $translated_term_id;
				}
			}

			if ( ! empty( $translated_term_ids ) ) {
				wp_set_post_terms( $target_post->ID, $translated_term_ids, $taxonomy->name, false );
			}
		}
	}
}







