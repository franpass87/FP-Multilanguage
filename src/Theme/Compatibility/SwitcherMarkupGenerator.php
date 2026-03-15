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
		$style      = $this->settings->get( 'menu_switcher_style', 'links' );
		$show_flags = $this->settings->get( 'menu_switcher_show_flags', true );

		// Map legacy 'inline' to 'links' for the new shortcode
		if ( 'inline' === $style ) {
			$style = 'links';
		}
		$style           = in_array( $style, array( 'links', 'flags', 'dropdown' ), true ) ? $style : 'links';
		$show_flags_attr = $show_flags ? 'yes' : 'no';

		$shortcode = sprintf(
			'[fpml_language_switcher style="%s" show_flags="%s" show_names="yes"]',
			esc_attr( $style ),
			esc_attr( $show_flags_attr )
		);

		$output = do_shortcode( $shortcode );

		// If shortcode is not registered yet, do_shortcode returns raw text.
		if ( ! is_string( $output ) || false !== strpos( $output, '[fpml_language_switcher' ) ) {
			$legacy_style = 'dropdown' === $style ? 'dropdown' : 'inline';
			$legacy_flags = 'yes' === $show_flags_attr ? '1' : '0';
			$legacy_code  = sprintf(
				'[fp_lang_switcher style="%s" show_flags="%s"]',
				esc_attr( $legacy_style ),
				esc_attr( $legacy_flags )
			);

			$output = do_shortcode( $legacy_code );
		}

		return is_string( $output ) ? trim( $output ) : '';
	}
}















