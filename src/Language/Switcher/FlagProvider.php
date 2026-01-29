<?php
/**
 * Flag Provider - Provides flag emojis for languages.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.10.0
 */

namespace FP\Multilanguage\Language\Switcher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides flag emojis for languages.
 *
 * @since 0.10.0
 */
class FlagProvider {
	/**
	 * Flag fallback map for common languages.
	 *
	 * @var array
	 */
	protected $flag_fallback = array(
		'it' => '🇮🇹',
		'en' => '🇬🇧',
		'de' => '🇩🇪',
		'fr' => '🇫🇷',
		'es' => '🇪🇸',
	);

	/**
	 * Get flag for a language.
	 *
	 * @param string $lang_code Language code.
	 * @param array  $lang_info Language info array.
	 * @return string
	 */
	public function get_flag( $lang_code, $lang_info = array() ) {
		$lang_flag = isset( $lang_info['flag'] ) ? $lang_info['flag'] : '';
		if ( empty( $lang_flag ) ) {
			$lang_flag = isset( $this->flag_fallback[ $lang_code ] ) ? $this->flag_fallback[ $lang_code ] : '';
		}
		return $lang_flag;
	}

	/**
	 * Get flag with trailing space if not empty.
	 *
	 * @param string $lang_code Language code.
	 * @param array  $lang_info Language info array.
	 * @return string
	 */
	public function get_flag_with_space( $lang_code, $lang_info = array() ) {
		$flag = $this->get_flag( $lang_code, $lang_info );
		return ! empty( $flag ) ? $flag . ' ' : '';
	}
}















