<?php
/**
 * Site Translations Search Filter - Handles search translations.
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
 * Handles search translations.
 *
 * @since 0.10.0
 */
class SearchFilter {
	/**
	 * Filter search query (for messages).
	 *
	 * @param string $query Original query.
	 * @return string Query (not translated, but messages are).
	 */
	public function filter_search_query( $query ) {
		// The query itself is not translated, but messages are
		// Search messages are filtered via theme-specific filters
		return $query;
	}

	/**
	 * Filter search page title.
	 *
	 * @param array $title_parts Title parts.
	 * @return array Filtered title parts.
	 */
	public function filter_search_title( $title_parts ) {
		if ( is_search() ) {
			$search_query     = get_search_query();
			$translated_format = get_option( '_fpml_en_search_results_found' );
			if ( $translated_format ) {
				$title_parts['title'] = sprintf( $translated_format, $search_query );
			}
		}

		return $title_parts;
	}
}
















