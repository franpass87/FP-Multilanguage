<?php
/**
 * Site Translations NotFound Filter - Handles 404 page translations.
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
 * Handles 404 page translations.
 *
 * @since 0.10.0
 */
class NotFoundFilter {
	/**
	 * Filter 404 page title.
	 *
	 * @param string $title Original title.
	 * @param string $sep   Separator.
	 * @return string Translated title.
	 */
	public function filter_404_title( $title, $sep = '' ) {
		if ( is_404() ) {
			$translated = get_option( '_fpml_en_404_title' );
			if ( $translated ) {
				return $translated . ( $sep ? ' ' . $sep . ' ' : '' ) . get_bloginfo( 'name' );
			}
		}

		return $title;
	}

	/**
	 * Filter 404 document title.
	 *
	 * @param array $title_parts Title parts.
	 * @return array Filtered title parts.
	 */
	public function filter_404_document_title( $title_parts ) {
		if ( is_404() ) {
			$translated = get_option( '_fpml_en_404_title' );
			if ( $translated ) {
				$title_parts['title'] = $translated;
			}
		}

		return $title_parts;
	}

	/**
	 * Filter 404 page content.
	 *
	 * @param string $content Original content.
	 * @return string Translated content.
	 */
	public function filter_404_content( $content ) {
		if ( ! is_404() ) {
			return $content;
		}

		$translated = get_option( '_fpml_en_404_page_content' );
		if ( $translated ) {
			return $translated;
		}

		// Fallback: translate generic message
		$message = get_option( '_fpml_en_404_message' );
		if ( $message && empty( $content ) ) {
			return $message;
		}

		return $content;
	}
}
















