<?php
/**
 * ACF Support Flexible Content Processor - Processes flexible content fields.
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
 * Processes ACF flexible content fields.
 *
 * @since 0.10.0
 */
class FlexibleContentProcessor {
	/**
	 * Logger instance.
	 *
	 * @var \FP\Multilanguage\Logger
	 */
	protected $logger;

	/**
	 * Post relation processor instance.
	 *
	 * @var PostRelationProcessor
	 */
	protected PostRelationProcessor $post_processor;

	/**
	 * Constructor.
	 *
	 * @param \FP\Multilanguage\Logger $logger         Logger instance.
	 * @param PostRelationProcessor     $post_processor Post relation processor instance.
	 */
	public function __construct( $logger, PostRelationProcessor $post_processor ) {
		$this->logger = $logger;
		$this->post_processor = $post_processor;
	}

	/**
	 * Process flexible content.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_Post $target_post Post tradotto.
	 * @param string    $meta_key    Meta key.
	 * @param array     $field       Field config ACF.
	 * @return void
	 */
	public function process_flexible_content( \WP_Post $target_post, string $meta_key, array $field ): void {
		// Similar to repeater but with dynamic layouts
		$source_id = get_post_meta( $target_post->ID, '_fpml_pair_source_id', true );
		if ( ! $source_id ) {
			return;
		}

		$rows = get_post_meta( $target_post->ID, $meta_key, true );

		if ( ! is_numeric( $rows ) || $rows <= 0 ) {
			return;
		}

		// Iterate layouts
		for ( $i = 0; $i < $rows; $i++ ) {
			$layout_key = $meta_key . '_' . $i . '_acf_fc_layout';
			$layout = get_post_meta( $target_post->ID, $layout_key, true );

			if ( ! $layout || ! isset( $field['layouts'] ) ) {
				continue;
			}

			// Find layout config
			$layout_config = null;
			foreach ( $field['layouts'] as $l ) {
				if ( isset( $l['name'] ) && $l['name'] === $layout ) {
					$layout_config = $l;
					break;
				}
			}

			if ( ! $layout_config || empty( $layout_config['sub_fields'] ) ) {
				continue;
			}

			// Process sub-fields of layout
			foreach ( $layout_config['sub_fields'] as $sub_field ) {
				$sub_key = $meta_key . '_' . $i . '_' . $sub_field['name'];

				if ( in_array( $sub_field['type'], array( 'post_object', 'relationship' ), true ) ) {
					$this->post_processor->process_post_relation( $target_post, $sub_key, $sub_field );
				}
			}
		}

		$this->logger->log(
			'debug',
			sprintf( 'Flexible content ACF processato: %s', $meta_key ),
			array( 'post_id' => $target_post->ID, 'meta_key' => $meta_key, 'rows' => $rows )
		);
	}
}















