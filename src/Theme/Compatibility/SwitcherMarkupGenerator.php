<?php
/**
 * Switcher Markup Generator - Generates language switcher markup.
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
 * Generates language switcher markup for menu integration.
 *
 * @since 0.10.0
 */
class SwitcherMarkupGenerator {
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
	 * Generate language switcher markup.
	 *
	 * @since 0.9.0
	 *
	 * @return string
	 */
	public function get_switcher_markup() {
		$style = $this->settings->get( 'menu_switcher_style', 'inline' );
		$show_flags = $this->settings->get( 'menu_switcher_show_flags', true );

		$style = in_array( $style, array( 'inline', 'dropdown' ), true ) ? $style : 'inline';
		$show_flags_attr = $show_flags ? '1' : '0';

		$shortcode = sprintf(
			'[fp_lang_switcher style="%s" show_flags="%s"]',
			esc_attr( $style ),
			esc_attr( $show_flags_attr )
		);

		$output = do_shortcode( $shortcode );

		return is_string( $output ) ? trim( $output ) : '';
	}
}















