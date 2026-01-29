<?php
/**
 * SEO Optimizer Meta Description Generator - Generates meta descriptions.
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
 * Generates and saves meta descriptions.
 *
 * @since 0.10.0
 */
class MetaDescriptionGenerator {
	/**
	 * Generate meta description automatically.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_Post $post Post tradotto.
	 * @return void
	 */
	public function generate( \WP_Post $post ): void {
		// Check if exists already
		$existing = $this->get_existing( $post );

		if ( $existing && strlen( $existing ) > 50 ) {
			return; // Already present, don't overwrite
		}

		// Generate optimized description (max 160 characters)
		$content     = wp_strip_all_tags( $post->post_content );
		$content     = preg_replace( '/\s+/', ' ', $content );
		$content     = trim( $content );
		$description = wp_trim_words( $content, 25, '...' );

		// Limit to 160 characters
		if ( strlen( $description ) > 160 ) {
			$description = substr( $description, 0, 157 ) . '...';
		}

		// Save using common meta keys
		$this->save( $post, $description );
	}

	/**
	 * Get existing meta description.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	public function get_existing( \WP_Post $post ): string {
		// Yoast SEO
		$yoast = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
		if ( $yoast ) {
			return $yoast;
		}

		// Rank Math
		$rank_math = get_post_meta( $post->ID, 'rank_math_description', true );
		if ( $rank_math ) {
			return $rank_math;
		}

		// All in One SEO
		$aioseo = get_post_meta( $post->ID, '_aioseo_description', true );
		if ( $aioseo ) {
			return $aioseo;
		}

		// SEOPress
		$seopress = get_post_meta( $post->ID, '_seopress_titles_desc', true );
		if ( $seopress ) {
			return $seopress;
		}

		return '';
	}

	/**
	 * Save meta description.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_Post $post        Post object.
	 * @param string    $description Description.
	 * @return void
	 */
	protected function save( \WP_Post $post, string $description ): void {
		// Detect which SEO plugin is active
		if ( defined( 'WPSEO_VERSION' ) ) {
			// Yoast SEO
			update_post_meta( $post->ID, '_yoast_wpseo_metadesc', $description );
		} elseif ( class_exists( 'RankMath' ) ) {
			// Rank Math
			update_post_meta( $post->ID, 'rank_math_description', $description );
		} elseif ( defined( 'AIOSEO_VERSION' ) ) {
			// All in One SEO
			update_post_meta( $post->ID, '_aioseo_description', $description );
		} elseif ( defined( 'SEOPRESS_VERSION' ) ) {
			// SEOPress
			update_post_meta( $post->ID, '_seopress_titles_desc', $description );
		}

		// Save also in generic meta for compatibility
		update_post_meta( $post->ID, '_fpml_meta_description', $description );
	}
}















