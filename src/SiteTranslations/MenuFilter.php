<?php
/**
 * Site Translations Menu Filter - Handles menu item translations.
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
 * Handles menu item translations.
 *
 * @since 0.10.0
 */
class MenuFilter {
	/**
	 * Filter menu items to show translations.
	 *
	 * @param array $items Menu items.
	 * @param array $args  Menu arguments.
	 * @return array Filtered items.
	 */
	public function filter_menu_items( $items, $args ) {
		if ( ! is_array( $items ) ) {
			return $items;
		}

		foreach ( $items as $item ) {
			if ( isset( $item->ID ) ) {
				$translated_title = get_option( '_fpml_en_menu_item_' . $item->ID . '_title' );

				if ( $translated_title ) {
					$item->title = $translated_title;
				}
			}
		}

		return $items;
	}

	/**
	 * Filter menu item title.
	 *
	 * @param string   $title Original title.
	 * @param \WP_Post $item  Menu item.
	 * @param \stdClass $args  Menu arguments.
	 * @param int      $depth Menu depth.
	 * @return string Translated title.
	 */
	public function filter_menu_item_title( $title, $item, $args, $depth ) {
		if ( isset( $item->ID ) ) {
			$translated_title = get_option( '_fpml_en_menu_item_' . $item->ID . '_title' );

			if ( $translated_title ) {
				return $translated_title;
			}
		}

		return $title;
	}
}
















