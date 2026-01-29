<?php
/**
 * Translation Sync Service.
 *
 * Centralizes translation synchronization logic (taxonomies, meta fields, etc.).
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Core\Services;

use FP\Multilanguage\Content\TranslationManager;
use FP\Multilanguage\Translation\JobEnqueuer;
use FP\Multilanguage\Core\PostHandlers;
use FP\Multilanguage\Logger;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for synchronizing translations.
 *
 * @since 1.0.0
 */
class TranslationSyncService {

	/**
	 * Translation manager instance.
	 *
	 * @var TranslationManager|null
	 */
	protected $translation_manager;

	/**
	 * Job enqueuer instance.
	 *
	 * @var JobEnqueuer|null
	 */
	protected $job_enqueuer;

	/**
	 * Constructor.
	 *
	 * @param TranslationManager|null $translation_manager Translation manager instance.
	 * @param JobEnqueuer|null         $job_enqueuer        Job enqueuer instance.
	 */
	public function __construct( ?TranslationManager $translation_manager = null, ?JobEnqueuer $job_enqueuer = null ) {
		$this->translation_manager = $translation_manager;
		$this->job_enqueuer        = $job_enqueuer;
	}

	/**
	 * Sync post taxonomies between source and target posts.
	 *
	 * @param WP_Post $source_post Source post.
	 * @param WP_Post $target_post Target post.
	 * @return void
	 */
	public function syncPostTaxonomies( WP_Post $source_post, WP_Post $target_post ): void {
		if ( class_exists( '\FP\Multilanguage\Core\PostHandlers' ) ) {
			$post_handlers = function_exists( 'fpml_get_post_handlers' ) ? fpml_get_post_handlers() : PostHandlers::instance();
			if ( method_exists( $post_handlers, 'sync_post_taxonomies' ) ) {
				$post_handlers->sync_post_taxonomies( $source_post, $target_post );
			}
		}
	}

	/**
	 * Enqueue translation jobs after translation is created.
	 *
	 * @param int $target_id Target post ID.
	 * @param int $source_id Source post ID.
	 * @return void
	 */
	public function enqueueJobsAfterTranslation( int $target_id, int $source_id ): void {
		if ( ! $this->job_enqueuer ) {
			Logger::debug( 'job_enqueuer not available in enqueueJobsAfterTranslation' );
			return;
		}

		$source_post = get_post( $source_id );
		$target_post = get_post( $target_id );

		if ( ! $source_post || ! $target_post ) {
			Logger::warning( 'source_post or target_post not found', array( 'source_id' => $source_id, 'target_id' => $target_id ) );
			return;
		}

		Logger::debug( 'Enqueueing jobs for translation', array( 'source_id' => $source_id, 'target_id' => $target_id ) );

		// Enqueue jobs for the newly created translation
		$this->job_enqueuer->enqueue_post_jobs( $source_post, $target_post, false );

		// Sync taxonomies (categorie, tag, ecc.)
		$this->syncPostTaxonomies( $source_post, $target_post );

		Logger::debug( 'Jobs enqueued successfully', array( 'source_id' => $source_id, 'target_id' => $target_id ) );
	}

	/**
	 * Set translation manager.
	 *
	 * @param TranslationManager $translation_manager Translation manager instance.
	 * @return void
	 */
	public function setTranslationManager( TranslationManager $translation_manager ): void {
		$this->translation_manager = $translation_manager;
	}

	/**
	 * Set job enqueuer.
	 *
	 * @param JobEnqueuer $job_enqueuer Job enqueuer instance.
	 * @return void
	 */
	public function setJobEnqueuer( JobEnqueuer $job_enqueuer ): void {
		$this->job_enqueuer = $job_enqueuer;
	}
}








