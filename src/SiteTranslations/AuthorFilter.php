<?php
/**
 * Site Translations Author Filter - Handles author bio translations.
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
 * Handles author bio translations.
 *
 * @since 0.10.0
 */
class AuthorFilter {
	/**
	 * Filter author biography.
	 *
	 * @param string $description Original biography.
	 * @return string Translated biography.
	 */
	public function filter_author_bio( $description ) {
		if ( empty( $description ) ) {
			return $description;
		}

		$author_id = get_the_author_meta( 'ID' );
		if ( $author_id ) {
			$translated = get_user_meta( $author_id, '_fpml_en_bio', true );
			if ( $translated ) {
				return $translated;
			}
		}

		return $description;
	}
}
















