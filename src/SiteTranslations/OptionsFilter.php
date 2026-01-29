<?php
/**
 * Site Translations Options Filter - Handles generic options translations.
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
 * Handles generic options translations.
 *
 * @since 0.10.0
 */
class OptionsFilter {
	/**
	 * Filter a generic option.
	 *
	 * @param mixed  $value  Original value.
	 * @param string $option Option name.
	 * @return mixed Translated value.
	 */
	public function filter_option( $value, $option ) {
		if ( empty( $value ) || ! is_string( $value ) ) {
			return $value;
		}

		$translated = get_option( '_fpml_en_' . $option );

		if ( $translated ) {
			return $translated;
		}

		return $value;
	}

	/**
	 * Filter generic option - allows other plugins to hook in.
	 *
	 * @param mixed  $value  Original value.
	 * @param string $option Option name.
	 * @return mixed Filtered value.
	 */
	public function filter_generic_option( $value, $option ) {
		// Avoid infinite loops
		if ( strpos( $option, '_fpml_en_' ) === 0 ) {
			return $value;
		}

		// Search for translation
		$translated = get_option( '_fpml_en_option_' . $option );

		if ( ! $translated ) {
			// Fallback: search also without 'option_' prefix for backward compatibility
			$translated = get_option( '_fpml_en_' . $option );
		}

		if ( $translated ) {
			return $translated;
		}

		// Allow other plugins to hook in with specific filter
		return apply_filters( 'fpml_filter_option_' . $option, $value, $option );
	}

	/**
	 * Filter site name (blogname).
	 *
	 * @param mixed  $value  Original value.
	 * @param string $option Option name.
	 * @return mixed Translated value.
	 */
	public function filter_blogname( $value, $option ) {
		$translated = get_option( '_fpml_en_option_blogname' );
		return $translated ? $translated : $value;
	}

	/**
	 * Filter site tagline (blogdescription).
	 *
	 * @param mixed  $value  Original value.
	 * @param string $option Option name.
	 * @return mixed Translated value.
	 */
	public function filter_blogdescription( $value, $option ) {
		$translated = get_option( '_fpml_en_option_blogdescription' );
		return $translated ? $translated : $value;
	}
}
















