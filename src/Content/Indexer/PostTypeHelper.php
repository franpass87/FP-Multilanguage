<?php
/**
 * Post Type Helper - Retrieves translatable post types.
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
 * Retrieves allowed post types for translation.
 *
 * @since 0.10.0
 */
class PostTypeHelper {
	/**
	 * Retrieve allowed post types for translation.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public function get_translatable_post_types() {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'names'
		);

		if ( ! in_array( 'attachment', $post_types, true ) ) {
			$post_types[] = 'attachment';
		}

		// Aggiungi post types personalizzati accettati.
		$custom_post_types = get_option( '\FPML_custom_translatable_post_types', array() );
		if ( ! empty( $custom_post_types ) ) {
			$post_types = array_merge( $post_types, $custom_post_types );
		}

		$post_types = apply_filters( '\FPML_translatable_post_types', $post_types );

		return array_filter( array_map( 'sanitize_key', $post_types ) );
	}
}















