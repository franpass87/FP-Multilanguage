<?php
/**
 * Theme Detector - Detects current WordPress theme.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.10.0
 */

namespace FP\Multilanguage\Theme\Compatibility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects current WordPress theme information.
 *
 * @since 0.10.0
 */
class ThemeDetector {
	/**
	 * Current theme slug.
	 *
	 * @var string
	 */
	protected $theme_slug = '';

	/**
	 * Current theme name.
	 *
	 * @var string
	 */
	protected $theme_name = '';

	/**
	 * Whether current theme is Salient.
	 *
	 * @var bool
	 */
	protected $is_salient = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Detect theme - get_template() returns parent theme for child themes
		// This ensures child themes automatically inherit parent theme configuration
		$theme = wp_get_theme();
		$this->theme_slug = strtolower( $theme->get_template() );
		$this->theme_name = $theme->get( 'Name' );
		$this->is_salient = ( 'salient' === $this->theme_slug );
	}

	/**
	 * Get theme slug.
	 *
	 * @return string
	 */
	public function get_theme_slug() {
		return $this->theme_slug;
	}

	/**
	 * Get theme name.
	 *
	 * @return string
	 */
	public function get_theme_name() {
		return $this->theme_name;
	}

	/**
	 * Check if current theme is Salient.
	 *
	 * @return bool
	 */
	public function is_salient() {
		return $this->is_salient;
	}
}















