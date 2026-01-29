<?php
/**
 * Menu Location Mapper - Maps theme slugs to menu locations.
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
 * Maps theme slugs to their primary menu locations.
 *
 * @since 0.10.0
 */
class MenuLocationMapper {
	/**
	 * Theme slug.
	 *
	 * @var string
	 */
	protected $theme_slug;

	/**
	 * Constructor.
	 *
	 * @param string $theme_slug Theme slug.
	 */
	public function __construct( $theme_slug ) {
		$this->theme_slug = $theme_slug;
	}

	/**
	 * Get primary menu location for current theme.
	 *
	 * @since 0.4.2
	 *
	 * @return string
	 */
	public function get_primary_menu_location() {
		// Note: Child themes automatically inherit parent theme location
		// because $this->theme_slug uses get_template() which returns parent theme.
		$locations = array(
			'salient'           => 'top_nav',
			'astra'             => 'primary',
			'generatepress'     => 'primary',
			'oceanwp'           => 'main_menu',
			'neve'              => 'primary',
			'kadence'           => 'primary',
			'blocksy'           => 'primary',
			'hello-elementor'   => 'primary',
			'storefront'        => 'primary',
			'twentytwentyfour'  => 'primary',
			'twentytwentythree' => 'primary',
			'twentytwentytwo'   => 'primary',
			'twentytwentyone'   => 'primary',
			'divi'              => 'primary-menu',
			'avada'             => 'main_navigation',
			'enfold'            => 'avia',
			'flatsome'          => 'primary',
			'bridge'            => 'main-menu',
			'the7'              => 'primary',
		);

		return isset( $locations[ $this->theme_slug ] ) ? $locations[ $this->theme_slug ] : 'primary';
	}

	/**
	 * Check if current theme is explicitly supported.
	 *
	 * @since 0.4.2
	 *
	 * @return bool
	 */
	public function is_theme_supported() {
		$supported_themes = array(
			'salient',
			'astra',
			'generatepress',
			'oceanwp',
			'neve',
			'kadence',
			'blocksy',
			'hello-elementor',
			'storefront',
			'twentytwentyfour',
			'twentytwentythree',
			'twentytwentytwo',
			'twentytwentyone',
			'divi',
			'avada',
			'enfold',
			'flatsome',
			'the7',
			'bridge',
		);

		return in_array( $this->theme_slug, $supported_themes, true );
	}
}















