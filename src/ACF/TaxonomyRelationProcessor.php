<?php
/**
 * ACF Support Taxonomy Relation Processor - Processes taxonomy relations.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\ACF;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Processes ACF taxonomy relations.
 *
 * @since 0.10.0
 */
class TaxonomyRelationProcessor {
	/**
	 * Logger instance.
	 *
	 * @var \FP\Multilanguage\Logger
	 */
	protected $logger;

	/**
	 * Constructor.
	 *
	 * @param \FP\Multilanguage\Logger $logger Logger instance.
	 */
	public function __construct( $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Process taxonomy relation.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_Post $target_post Post tradotto.
	 * @param string    $meta_key    Meta key.
	 * @param array     $field       Field config ACF.
	 * @return void
	 */
	public function process_taxonomy_relation( \WP_Post $target_post, string $meta_key, array $field ): void {
		$source_id = get_post_meta( $target_post->ID, '_fpml_pair_source_id', true );
		if ( ! $source_id ) {
			return;
		}

		$source_value = get_post_meta( $source_id, $meta_key, true );

		if ( empty( $source_value ) ) {
			return;
		}

		// Convert to array
		$term_ids = is_array( $source_value ) ? $source_value : array( $source_value );
		$translated_ids = array();
		$taxonomy = $field['taxonomy'];

		foreach ( $term_ids as $term_id ) {
			// Get term translation
			$translation_id = get_term_meta( $term_id, '_fpml_pair_id', true );

			if ( $translation_id ) {
				$translated_ids[] = (int) $translation_id;
			}
		}

		if ( empty( $translated_ids ) ) {
			return;
		}

		// Save translated relation
		$new_value = 'checkbox' !== $field['field_type'] && ! empty( $field['field_type'] ) ? $translated_ids[0] : $translated_ids;

		update_post_meta( $target_post->ID, $meta_key, $new_value );

		$this->logger->log(
			'debug',
			sprintf( 'Relazione taxonomy ACF aggiornata per %s', $meta_key ),
			array(
				'post_id'    => $target_post->ID,
				'meta_key'   => $meta_key,
				'taxonomy'   => $taxonomy,
				'translated' => $translated_ids,
			)
		);
	}
}















