<?php
/**
 * ACF Support Repeater Processor - Processes repeater fields.
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
 * Processes ACF repeater fields.
 *
 * @since 0.10.0
 */
class RepeaterProcessor {
	/**
	 * Post relation processor instance.
	 *
	 * @var PostRelationProcessor
	 */
	protected PostRelationProcessor $post_processor;

	/**
	 * Constructor.
	 *
	 * @param PostRelationProcessor $post_processor Post relation processor instance.
	 */
	public function __construct( PostRelationProcessor $post_processor ) {
		$this->post_processor = $post_processor;
	}

	/**
	 * Process repeater field.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_Post $target_post Post tradotto.
	 * @param string    $meta_key    Meta key.
	 * @param array     $field       Field config ACF.
	 * @return void
	 */
	public function process_repeater_field( \WP_Post $target_post, string $meta_key, array $field ): void {
		$source_id = get_post_meta( $target_post->ID, '_fpml_pair_source_id', true );
		if ( ! $source_id ) {
			return;
		}

		// Repeaters are handled as arrays by normal translation
		// Here we can process internal relations
		$rows = get_post_meta( $target_post->ID, $meta_key, true );

		if ( ! is_numeric( $rows ) || $rows <= 0 ) {
			return;
		}

		// Iterate rows
		for ( $i = 0; $i < $rows; $i++ ) {
			if ( ! isset( $field['sub_fields'] ) || ! is_array( $field['sub_fields'] ) ) {
				continue;
			}

			foreach ( $field['sub_fields'] as $sub_field ) {
				$sub_key = $meta_key . '_' . $i . '_' . $sub_field['name'];

				if ( in_array( $sub_field['type'], array( 'post_object', 'relationship' ), true ) ) {
					// Process relation in sub-field
					$this->post_processor->process_post_relation( $target_post, $sub_key, $sub_field );
				}
			}
		}
	}
}















