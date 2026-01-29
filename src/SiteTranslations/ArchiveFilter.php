<?php
/**
 * Site Translations Archive Filter - Handles archive translations.
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
 * Handles archive translations.
 *
 * @since 0.10.0
 */
class ArchiveFilter {
	/**
	 * Filter archive titles.
	 *
	 * @param string $title Original title.
	 * @return string Translated title.
	 */
	public function filter_archive_title( $title ) {
		if ( empty( $title ) ) {
			return $title;
		}

		// Determine archive type
		$archive_type = '';
		$archive_id   = 0;

		if ( is_category() ) {
			$archive_type = 'category';
			$archive_id   = get_queried_object_id();
		} elseif ( is_tag() ) {
			$archive_type = 'tag';
			$archive_id   = get_queried_object_id();
		} elseif ( is_author() ) {
			$archive_type = 'author';
			$archive_id   = get_queried_object_id();
		} elseif ( is_date() ) {
			$archive_type = 'date';
			$year         = get_query_var( 'year' );
			$month        = get_query_var( 'monthnum' );
			$day          = get_query_var( 'day' );
			$archive_id   = $year . '-' . $month . '-' . $day;
		} elseif ( is_tax() ) {
			$archive_type = 'taxonomy';
			$archive_id   = get_queried_object_id();
		}

		if ( $archive_type && $archive_id ) {
			$translated = get_option( '_fpml_en_archive_' . $archive_type . '_' . $archive_id . '_title' );
			if ( $translated ) {
				return $translated;
			}
		}

		return $title;
	}

	/**
	 * Filter archive descriptions.
	 *
	 * @param string $description Original description.
	 * @return string Translated description.
	 */
	public function filter_archive_description( $description ) {
		if ( empty( $description ) ) {
			return $description;
		}

		// Determine archive type
		$archive_type = '';
		$archive_id   = 0;

		if ( is_category() ) {
			$archive_type = 'category';
			$archive_id   = get_queried_object_id();
		} elseif ( is_tag() ) {
			$archive_type = 'tag';
			$archive_id   = get_queried_object_id();
		} elseif ( is_tax() ) {
			$archive_type = 'taxonomy';
			$archive_id   = get_queried_object_id();
		}

		if ( $archive_type && $archive_id ) {
			$translated = get_option( '_fpml_en_archive_' . $archive_type . '_' . $archive_id . '_description' );
			if ( $translated ) {
				return $translated;
			}
		}

		return $description;
	}
}
















