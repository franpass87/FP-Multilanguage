<?php
/**
 * Salient Handler - Handles Salient theme-specific integration.
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
 * Handles Salient theme-specific integration for language switcher.
 *
 * @since 0.10.0
 */
class SalientHandler {
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
	 * @param MenuLocationMapper      $location_mapper  Menu location mapper instance.
	 * @param SwitcherMarkupGenerator $markup_generator Switcher markup generator instance.
	 */
	public function __construct( MenuLocationMapper $location_mapper, SwitcherMarkupGenerator $markup_generator ) {
		$this->location_mapper  = $location_mapper;
		$this->markup_generator = $markup_generator;
	}

	/**
	 * Output a switcher placeholder for Salient when no menu is assigned.
	 *
	 * @since 0.9.0
	 *
	 * @return void
	 */
	public function render_salient_switcher_seed() {
		if ( is_admin() ) {
			return;
		}

		if ( has_nav_menu( $this->location_mapper->get_primary_menu_location() ) ) {
			return;
		}

		$switcher = $this->markup_generator->get_switcher_markup();

		if ( empty( $switcher ) ) {
			return;
		}

		echo '<div class="fpml-salient-switcher-placeholder" data-fpml-switcher-placeholder="true" style="display:none;" aria-hidden="true">' . $switcher . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Enqueue custom script for Salient header integration.
	 *
	 * @since 0.9.0
	 *
	 * @return void
	 */
	public function enqueue_salient_switcher_script() {
		if ( is_admin() ) {
			return;
		}

		$script_path = FPML_PLUGIN_DIR . 'assets/salient-switcher.js';
		$script_url  = FPML_PLUGIN_URL . 'assets/salient-switcher.js';

		if ( ! file_exists( $script_path ) ) {
			return;
		}

		$version = defined( 'WP_DEBUG' ) && WP_DEBUG ? (string) filemtime( $script_path ) : FPML_PLUGIN_VERSION;

		wp_enqueue_script(
			'fpml-salient-switcher',
			$script_url,
			array(),
			$version,
			true
		);
	}
}















