<?php
/**
 * SEO Optimizer Slug Optimizer - Optimizes post slugs.
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
 * Optimizes post slugs.
 *
 * @since 0.10.0
 */
class SlugOptimizer {
	/**
	 * Settings instance.
	 *
	 * @var \FPML_Settings
	 */
	protected $settings;

	/**
	 * Constructor.
	 *
	 * @param \FPML_Settings $settings Settings instance.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Optimize post slug.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_Post $post Post tradotto.
	 * @return void
	 */
	public function optimize( \WP_Post $post ): void {
		if ( ! $this->settings || ! $this->settings->get( 'translate_slugs', false ) ) {
			return;
		}

		// Slug is already handled by \FPML_SEO, here we can only validate
		$slug = $post->post_name;

		if ( ! $slug || strlen( $slug ) < 3 ) {
			// Generate slug from title
			$new_slug = sanitize_title( $post->post_title );

			if ( $new_slug && $new_slug !== $slug ) {
				\fpml_safe_update_post(
					array(
						'ID'        => $post->ID,
						'post_name' => $new_slug,
					)
				);
			}
		}
	}
}















