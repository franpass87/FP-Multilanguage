<?php
/**
 * SEO Sitemap Config - Manages sitemap configuration.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\SEO\Sitemap;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages sitemap configuration (post types, taxonomies).
 *
 * @since 0.10.0
 */
class SitemapConfig {
	/**
	 * Get post types included in sitemap.
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	public function get_sitemap_post_types(): array {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'names'
		);

		if ( ! in_array( 'attachment', $post_types, true ) ) {
			$post_types[] = 'attachment';
		}

		$post_types = apply_filters( '\FPML_sitemap_post_types', $post_types );

		return array_filter( array_map( 'sanitize_key', (array) $post_types ) );
	}

	/**
	 * Get taxonomies included in sitemap.
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	public function get_sitemap_taxonomies(): array {
		$taxonomies = get_taxonomies(
			array(
				'public' => true,
			),
			'names'
		);

		$taxonomies = apply_filters( '\FPML_sitemap_taxonomies', $taxonomies );

		return array_filter( array_map( 'sanitize_key', (array) $taxonomies ) );
	}
}















