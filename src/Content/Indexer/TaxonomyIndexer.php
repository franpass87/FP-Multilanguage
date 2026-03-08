<?php
/**
 * Taxonomy Indexer - Reindexes terms of a specific taxonomy.
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
 * Reindexes terms of a specific taxonomy.
 *
 * @since 0.10.0
 */
class TaxonomyIndexer {
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
	 * Constructor.
	 *
	 * @param \FPML_Translation_Manager $translation_manager Translation manager instance.
	 * @param \FPML_Job_Enqueuer        $job_enqueuer        Job enqueuer instance.
	 */
	public function __construct( $translation_manager, $job_enqueuer ) {
		$this->translation_manager = $translation_manager;
		$this->job_enqueuer        = $job_enqueuer;
	}

	/**
	 * Reindex specific taxonomy.
	 *
	 * @since 0.4.0
	 *
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return array Summary.
	 */
	public function reindex_taxonomy( $taxonomy ) {
		$summary = array(
			'terms_scanned' => 0,
		);

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return $summary;
		}

		// Pre-load term meta to avoid N+1 queries.
		if ( ! empty( $terms ) ) {
			update_meta_cache( 'term', $terms );
		}

		$language_manager   = function_exists( 'fpml_get_language_manager' ) ? fpml_get_language_manager() : null;
		$enabled_languages  = $language_manager ? $language_manager->get_enabled_languages() : array();

		foreach ( $terms as $term_id ) {
			if ( get_term_meta( $term_id, '_fpml_is_translation', true ) ) {
				continue;
			}

			$summary['terms_scanned']++;

			foreach ( $enabled_languages as $target_lang ) {
				$target_term = $this->translation_manager->ensure_term_translation( $term_id, $taxonomy, $target_lang );

				if ( $target_term instanceof \WP_Term ) {
					$term = get_term( $term_id, $taxonomy );
					if ( $term && ! is_wp_error( $term ) ) {
						$this->job_enqueuer->enqueue_term_jobs( $term, $target_term );
					}
				}
			}
		}

		return $summary;
	}
}















