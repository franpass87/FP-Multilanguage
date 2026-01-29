<?php
/**
 * Site Translations Breadcrumb Filter - Handles breadcrumb translations.
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
 * Handles breadcrumb translations for various SEO plugins.
 *
 * @since 0.10.0
 */
class BreadcrumbFilter {
	/**
	 * Filter Yoast SEO breadcrumbs.
	 *
	 * @param array $links Breadcrumb links.
	 * @return array Filtered links.
	 */
	public function filter_yoast_breadcrumbs( $links ) {
		if ( ! is_array( $links ) ) {
			return $links;
		}

		foreach ( $links as &$link ) {
			if ( isset( $link['text'] ) && ! empty( $link['text'] ) ) {
				$translated = get_option( '_fpml_en_breadcrumb_' . md5( $link['text'] ) );
				if ( $translated ) {
					$link['text'] = $translated;
				}
			}
		}

		return $links;
	}

	/**
	 * Filter Rank Math breadcrumbs.
	 *
	 * @param array $items Breadcrumb items.
	 * @param array $args  Arguments.
	 * @return array Filtered items.
	 */
	public function filter_rankmath_breadcrumbs( $items, $args ) {
		if ( ! is_array( $items ) ) {
			return $items;
		}

		foreach ( $items as &$item ) {
			if ( isset( $item['text'] ) && ! empty( $item['text'] ) ) {
				$translated = get_option( '_fpml_en_breadcrumb_' . md5( $item['text'] ) );
				if ( $translated ) {
					$item['text'] = $translated;
				}
			}
		}

		return $items;
	}

	/**
	 * Filter All in One SEO breadcrumbs.
	 *
	 * @param array $trail Breadcrumb trail.
	 * @return array Filtered trail.
	 */
	public function filter_aioseo_breadcrumbs( $trail ) {
		if ( ! is_array( $trail ) ) {
			return $trail;
		}

		foreach ( $trail as &$item ) {
			if ( isset( $item['text'] ) && ! empty( $item['text'] ) ) {
				$translated = get_option( '_fpml_en_breadcrumb_' . md5( $item['text'] ) );
				if ( $translated ) {
					$item['text'] = $translated;
				}
			}
		}

		return $trail;
	}
}
















