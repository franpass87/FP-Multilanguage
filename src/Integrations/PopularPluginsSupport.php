<?php
/**
 * Popular Plugins Support
 *
 * Automatically detects and includes translatable meta fields from popular WordPress plugins.
 *
 * @package FP_Multilanguage
 * @since 0.9.0
 */

namespace FP\Multilanguage\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Support for popular WordPress plugins.
 *
 * @since 0.9.0
 */
class PopularPluginsSupport {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		add_filter( 'fpml_meta_whitelist', array( $this, 'add_popular_plugins_meta_fields' ), 20 );
	}

	/**
	 * Add meta fields from popular WordPress plugins to the whitelist.
	 *
	 * @param array $whitelist Current whitelist.
	 * @return array Updated whitelist.
	 */
	public function add_popular_plugins_meta_fields( $whitelist ) {
		// Elementor
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			$whitelist[] = '_elementor_data';
			$whitelist[] = '_elementor_page_settings';
			$whitelist[] = '_elementor_css';
		}

		// Beaver Builder
		if ( class_exists( 'FLBuilder' ) ) {
			$whitelist[] = '_fl_builder_data';
			$whitelist[] = '_fl_builder_draft';
			$whitelist[] = '_fl_builder_enabled';
		}

		// Divi Builder
		if ( defined( 'ET_BUILDER_VERSION' ) ) {
			$whitelist[] = '_et_pb_use_builder';
			$whitelist[] = '_et_pb_old_content';
			$whitelist[] = '_et_pb_page_layout';
		}

		// Contact Form 7
		if ( defined( 'WPCF7_VERSION' ) ) {
			$whitelist[] = '_wpcf7';
		}

		// WPForms
		if ( class_exists( 'WPForms' ) ) {
			$whitelist[] = '_wpforms';
		}

		// Gravity Forms
		if ( class_exists( 'GFCommon' ) ) {
			$whitelist[] = '_gravity_form_data';
		}

		// Advanced Custom Fields (ACF)
		if ( class_exists( 'ACF' ) ) {
			// ACF stores fields in postmeta with field keys
			// We'll add common ACF field patterns
			$whitelist[] = 'acf_';
		}

		// Meta Box
		if ( class_exists( 'RWMB_Loader' ) ) {
			$whitelist[] = 'meta_box_';
		}

		// Pods
		if ( class_exists( 'Pods' ) ) {
			$whitelist[] = 'pods_';
		}

		// Toolset
		if ( defined( 'WPCF_VERSION' ) ) {
			$whitelist[] = 'wpcf-';
		}

		// Yoast SEO
		if ( defined( 'WPSEO_VERSION' ) ) {
			$whitelist[] = '_yoast_wpseo_title';
			$whitelist[] = '_yoast_wpseo_metadesc';
			$whitelist[] = '_yoast_wpseo_focuskw';
			$whitelist[] = '_yoast_wpseo_opengraph-title';
			$whitelist[] = '_yoast_wpseo_opengraph-description';
			$whitelist[] = '_yoast_wpseo_twitter-title';
			$whitelist[] = '_yoast_wpseo_twitter-description';
		}

		// All in One SEO
		if ( defined( 'AIOSEO_VERSION' ) ) {
			$whitelist[] = '_aioseo_title';
			$whitelist[] = '_aioseo_description';
			$whitelist[] = '_aioseo_og_title';
			$whitelist[] = '_aioseo_og_description';
		}

		// Rank Math
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$whitelist[] = 'rank_math_title';
			$whitelist[] = 'rank_math_description';
			$whitelist[] = 'rank_math_facebook_title';
			$whitelist[] = 'rank_math_facebook_description';
		}

		// WP Rocket
		if ( defined( 'WP_ROCKET_VERSION' ) ) {
			// WP Rocket doesn't store translatable content in postmeta
		}

		// WP Super Cache
		if ( defined( 'WPCACHEHOME' ) ) {
			// WP Super Cache doesn't store translatable content in postmeta
		}

		// Jetpack
		if ( defined( 'JETPACK__VERSION' ) ) {
			$whitelist[] = '_jetpack_featured_media';
		}

		// MonsterInsights
		if ( defined( 'MONSTERINSIGHTS_VERSION' ) ) {
			// MonsterInsights doesn't store translatable content in postmeta
		}

		// Smush
		if ( defined( 'WP_SMUSH_VERSION' ) ) {
			// Smush doesn't store translatable content in postmeta
		}

		// UpdraftPlus
		if ( defined( 'UPDRAFTPLUS_VERSION' ) ) {
			// UpdraftPlus doesn't store translatable content in postmeta
		}

		// WPML
		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			// WPML handles translations itself, but we can still translate meta fields
		}

		// Polylang
		if ( defined( 'POLYLANG_VERSION' ) ) {
			// Polylang handles translations itself, but we can still translate meta fields
		}

		// Custom Post Type UI
		if ( class_exists( 'cptui_load_ui' ) ) {
			// CPT UI doesn't store translatable content in postmeta
		}

		// Redux Framework
		if ( class_exists( 'ReduxFramework' ) ) {
			$whitelist[] = 'redux_';
		}

		// Customizer Export/Import
		if ( class_exists( 'CEI_Core' ) ) {
			// Customizer Export/Import doesn't store translatable content in postmeta
		}

		return $whitelist;
	}
}


