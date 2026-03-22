<?php
/**
 * Menu Integrator - Integrates language switcher into navigation menus.
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
 * Integrates language switcher into navigation menus.
 *
 * @since 0.10.0
 */
class MenuIntegrator {
	/**
	 * Settings instance.
	 *
	 * @var \FPML_Settings
	 */
	protected $settings;

	/**
	 * Menu location mapper instance.
	 *
	 * @var MenuLocationMapper
	 */
	protected $location_mapper;

	/**
	 * Switcher markup generator instance.
	 *
	 * @var SwitcherMarkupGenerator
	 */
	protected $markup_generator;

	/**
	 * Constructor.
	 *
	 * @param \FPML_Settings          $settings         Settings instance.
	 * @param MenuLocationMapper      $location_mapper  Menu location mapper instance.
	 * @param SwitcherMarkupGenerator $markup_generator Switcher markup generator instance.
	 */
	public function __construct( $settings, MenuLocationMapper $location_mapper, SwitcherMarkupGenerator $markup_generator ) {
		$this->settings         = $settings;
		$this->location_mapper  = $location_mapper;
		$this->markup_generator = $markup_generator;
	}

	/**
	 * Add language switcher to navigation menu.
	 *
	 * @since 0.4.2
	 *
	 * @param string $items Menu items HTML.
	 * @param object $args  Menu arguments.
	 *
	 * @return string
	 */
	public function add_switcher_to_menu( $items, $args ) {
		$location = $this->location_mapper->get_primary_menu_location();

		// Check if this is the primary menu
		if ( empty( $location ) || $args->theme_location !== $location ) {
			return $items;
		}

		// Avoid duplicate switchers in mixed environments (FPML + WPML).
		if ( $this->has_existing_language_switcher_markup( $items ) ) {
			return $items;
		}

		$switcher = $this->markup_generator->get_switcher_markup();

		if ( empty( $switcher ) ) {
			return $items;
		}

		// Get position preference
		$position = $this->settings->get( 'menu_switcher_position', 'end' );

		$switcher_html = '<li class="menu-item menu-item-language-switcher fpml-auto-integrated">' . $switcher . '</li>';

		if ( 'start' === $position ) {
			return $switcher_html . $items;
		}

		return $items . $switcher_html;
	}

	/**
	 * Detect if menu markup already contains a language switcher.
	 *
	 * Covers both FPML and WPML common markup/classes to keep a single
	 * visible switcher for a seamless navigation experience.
	 *
	 * @since 1.0.0
	 *
	 * @param string $items Menu items HTML.
	 * @return bool
	 */
	protected function has_existing_language_switcher_markup( string $items ): bool {
		if ( '' === trim( $items ) ) {
			return false;
		}

		$patterns = array(
			'fpml-auto-integrated', // FPML auto-integrated list item
			'fpml-language-switcher',
			'fpml-switcher',
			'fp_lang_switcher',
			'fpml_language_switcher',
			'wpml-ls-item', // WPML language switcher
			'wpml-ls',
			'icl_language_selector',
			'menu-item-language',
		);

		foreach ( $patterns as $pattern ) {
			if ( false !== stripos( $items, $pattern ) ) {
				return true;
			}
		}

		return false;
	}
}















