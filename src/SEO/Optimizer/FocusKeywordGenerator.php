<?php
/**
 * SEO Optimizer Focus Keyword Generator - Generates focus keywords.
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
 * Generates and saves focus keywords.
 *
 * @since 0.10.0
 */
class FocusKeywordGenerator {
	/**
	 * Generate focus keyword based on title.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_Post $post Post tradotto.
	 * @return void
	 */
	public function generate( \WP_Post $post ): void {
		$existing = $this->get_existing( $post );

		if ( $existing ) {
			return; // Already present
		}

		// Extract 2-3 most significant words from title
		$title = $post->post_title;
		$title = strtolower( $title );

		// Remove common English stop words
		$stop_words = array( 'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'as', 'is', 'are', 'was', 'were', 'be', 'been', 'being' );
		$words      = explode( ' ', $title );
		$words      = array_diff( $words, $stop_words );
		$words      = array_values( $words );

		if ( empty( $words ) ) {
			return;
		}

		// Take first 2-3 significant words
		$keyword = implode( ' ', array_slice( $words, 0, min( 3, count( $words ) ) ) );

		$this->save( $post, $keyword );
	}

	/**
	 * Get existing focus keyword.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	public function get_existing( \WP_Post $post ): string {
		// Yoast SEO
		$yoast = get_post_meta( $post->ID, '_yoast_wpseo_focuskw', true );
		if ( $yoast ) {
			return $yoast;
		}

		// Rank Math
		$rank_math = get_post_meta( $post->ID, 'rank_math_focus_keyword', true );
		if ( $rank_math ) {
			return $rank_math;
		}

		return '';
	}

	/**
	 * Save focus keyword.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_Post $post    Post object.
	 * @param string    $keyword Keyword.
	 * @return void
	 */
	protected function save( \WP_Post $post, string $keyword ): void {
		if ( defined( 'WPSEO_VERSION' ) ) {
			update_post_meta( $post->ID, '_yoast_wpseo_focuskw', $keyword );
		} elseif ( class_exists( 'RankMath' ) ) {
			update_post_meta( $post->ID, 'rank_math_focus_keyword', $keyword );
		}

		update_post_meta( $post->ID, '_fpml_focus_keyword', $keyword );
	}
}















