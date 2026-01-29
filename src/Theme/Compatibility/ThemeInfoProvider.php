<?php
/**
 * Theme Info Provider - Provides theme information.
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
 * Provides theme information for compatibility.
 *
 * @since 0.10.0
 */
class ThemeInfoProvider {
	/**
	 * Theme detector instance.
	 *
	 * @var ThemeDetector
	 */
	protected $detector;

	/**
	 * Menu location mapper instance.
	 *
	 * @var MenuLocationMapper
	 */
	protected $location_mapper;

	/**
	 * Constructor.
	 *
	 * @param ThemeDetector      $detector       Theme detector instance.
	 * @param MenuLocationMapper $location_mapper Menu location mapper instance.
	 */
	public function __construct( ThemeDetector $detector, MenuLocationMapper $location_mapper ) {
		$this->detector        = $detector;
		$this->location_mapper = $location_mapper;
	}

	/**
	 * Get detected theme info.
	 *
	 * @since 0.4.2
	 *
	 * @return array
	 */
	public function get_theme_info() {
		return array(
			'slug'      => $this->detector->get_theme_slug(),
			'name'      => $this->detector->get_theme_name(),
			'location'  => $this->location_mapper->get_primary_menu_location(),
			'supported' => $this->location_mapper->is_theme_supported(),
		);
	}
}















