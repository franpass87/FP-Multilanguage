<?php
/**
 * Post Type Indexer - Reindexes posts of a specific post type.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.10.0
 */

namespace FP\Multilanguage\Content\Indexer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reindexes posts of a specific post type.
 *
 * @since 0.10.0
 */
class PostTypeIndexer {
	/**
	 * Translation manager.
	 *
	 * @var \FPML_Translation_Manager
	 */
	protected $translation_manager;

	/**
	 * Job enqueuer.
	 *
	 * @var \FPML_Job_Enqueuer
	 */
	protected $job_enqueuer;

	/**
	 * Timeout manager instance.
	 *
	 * @var TimeoutManager
	 */
	protected $timeout_manager;

	/**
	 * Constructor.
	 *
	 * @param \FPML_Translation_Manager $translation_manager Translation manager instance.
	 * @param \FPML_Job_Enqueuer        $job_enqueuer        Job enqueuer instance.
	 * @param TimeoutManager            $timeout_manager     Timeout manager instance.
	 */
	public function __construct( $translation_manager, $job_enqueuer, TimeoutManager $timeout_manager ) {
		$this->translation_manager = $translation_manager;
		$this->job_enqueuer        = $job_enqueuer;
		$this->timeout_manager     = $timeout_manager;
	}

	/**
	 * Reindex specific post type.
	 *
	 * @since 0.4.0
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return array Summary.
	 */
	public function reindex_post_type( $post_type ) {
		$summary = array(
			'posts_scanned'        => 0,
			'posts_enqueued'       => 0,
			'translations_created' => 0,
		);

		$paged = 1;
		$start_time = time();

		do {
			// Estendi il timeout ogni 5 pagine per evitare scadenze su grandi dataset
			if ( 0 === ( $paged % 5 ) ) {
				$this->timeout_manager->maybe_extend_timeout( $start_time );
			}

			$query = new \WP_Query(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'any',
					'posts_per_page' => 100,
					'paged'          => $paged,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			);

			if ( ! $query->have_posts() ) {
				break;
			}

			// Pre-load all post meta to avoid N+1 queries.
			update_meta_cache( 'post', $query->posts );

			foreach ( $query->posts as $post_id ) {
				$post = get_post( $post_id );

				if ( ! $post instanceof \WP_Post ) {
					continue;
				}

				if ( get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
					continue;
				}

				$summary['posts_scanned']++;

				// Get translation for first enabled language
				$language_manager = fpml_get_language_manager();
				$enabled_languages = $language_manager->get_enabled_languages();
				$target_lang = ! empty( $enabled_languages ) ? $enabled_languages[0] : 'en';
				
				$existing_target = $this->translation_manager->get_translation_id( $post->ID, $target_lang );
				// Backward compatibility
				if ( ! $existing_target && 'en' === $target_lang ) {
					$existing_target = (int) get_post_meta( $post->ID, '_fpml_pair_id', true );
				}
				
				// Create translation explicitly during indexing
				if ( $existing_target ) {
					$target_post = get_post( $existing_target );
				} else {
					$target_post = $this->translation_manager->create_post_translation( $post, $target_lang, 'draft' );
				}

				if ( ! $target_post ) {
					continue;
				}

				if ( ! $existing_target ) {
					$summary['translations_created']++;
				}

				$this->job_enqueuer->enqueue_post_jobs( $post, $target_post, true );
				$summary['posts_enqueued']++;
			}

			$paged++;
		} while ( $paged <= $query->max_num_pages );

		wp_reset_postdata();

		return $summary;
	}
}















