<?php
/**
 * Site Translations Theme Options Filter - Handles theme options translations.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\SiteTranslations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles theme options translations.
 *
 * @since 0.10.0
 */
class ThemeOptionsFilter {
	/**
	 * Filter Salient theme options.
	 *
	 * @param mixed  $value  Original value.
	 * @param string $option Option name.
	 * @return mixed Translated value.
	 */
	public function filter_theme_options( $value, $option ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		// Fields to translate
		$fields_to_translate = array(
			'header_text',
			'footer_text',
			'copyright_text',
			'call_to_action_text',
		);

		foreach ( $fields_to_translate as $field ) {
			if ( isset( $value[ $field ] ) && is_string( $value[ $field ] ) ) {
				$translated = get_option( '_fpml_en_theme_option_' . $field );

				if ( $translated ) {
					$value[ $field ] = $translated;
				}
			}
		}

		return $value;
	}

	/**
	 * Filter customizer options (theme_mod).
	 *
	 * @param mixed  $value Original value.
	 * @param string $name  Option name.
	 * @return mixed Translated value.
	 */
	public function filter_theme_mod( $value, $name ) {
		// Avoid infinite loops
		if ( strpos( $name, '_fpml_en_' ) === 0 ) {
			return $value;
		}

		if ( empty( $value ) || ! is_string( $value ) ) {
			return $value;
		}

		// Skip values that look like URLs or paths
		$is_url = preg_match( '#^(https?://|/|#[a-f0-9]{3,6}$)#i', $value );

		// Check for regex errors
		if ( preg_last_error() !== PREG_NO_ERROR ) {
			\FP\Multilanguage\Logger::warning(
				'Regex error in filter_theme_mod',
				array(
					'error'   => preg_last_error(),
					'pattern' => '#^(https?://|/|#[a-f0-9]{3,6}$)#i',
				)
			);
			$is_url = false; // Safe fallback
		}

		if ( $is_url ) {
			return $value;
		}

		$translated = get_option( '_fpml_en_theme_mod_' . $name );
		return $translated ? $translated : $value;
	}
}
















