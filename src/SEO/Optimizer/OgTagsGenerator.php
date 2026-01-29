<?php
/**
 * SEO Optimizer OG Tags Generator - Generates Open Graph tags.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\SEO\Optimizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates Open Graph tags.
 *
 * @since 0.10.0
 */
class OgTagsGenerator {
	/**
	 * Meta description generator instance.
	 *
	 * @var MetaDescriptionGenerator
	 */
	protected MetaDescriptionGenerator $meta_description;

	/**
	 * Constructor.
	 *
	 * @param MetaDescriptionGenerator $meta_description Meta description generator instance.
	 */
	public function __construct( MetaDescriptionGenerator $meta_description ) {
		$this->meta_description = $meta_description;
	}

	/**
	 * Generate Open Graph tags.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_Post $post Post tradotto.
	 * @return void
	 */
	public function generate( \WP_Post $post ): void {
		// OG Title
		if ( ! get_post_meta( $post->ID, '_yoast_wpseo_opengraph-title', true ) ) {
			update_post_meta( $post->ID, '_yoast_wpseo_opengraph-title', $post->post_title );
		}

		// OG Description
		$description = $this->meta_description->get_existing( $post );
		if ( $description && ! get_post_meta( $post->ID, '_yoast_wpseo_opengraph-description', true ) ) {
			update_post_meta( $post->ID, '_yoast_wpseo_opengraph-description', $description );
		}

		// OG Image (use featured image if present)
		if ( has_post_thumbnail( $post->ID ) && ! get_post_meta( $post->ID, '_yoast_wpseo_opengraph-image', true ) ) {
			$thumbnail_url = get_the_post_thumbnail_url( $post->ID, 'large' );
			if ( $thumbnail_url ) {
				update_post_meta( $post->ID, '_yoast_wpseo_opengraph-image', $thumbnail_url );
			}
		}
	}
}















