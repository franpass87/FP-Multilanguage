<?php
/**
 * Menu Filter - Filters menu items by language on frontend.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filters menu items by language on frontend.
 *
 * @since 0.10.0
 */
class MenuFilter {
	/**
	 * Filter menu items by language on frontend.
	 *
	 * @param array  $items  Menu items.
	 * @param object $menu   Menu object.
	 * @param array  $args   Menu args.
	 * @return array
	 */
	public function filter_menu_items_by_language( array $items, $menu, $args ): array {
		// Detect current language
		$current_lang = $this->get_current_language();

		// Check if current language is Italian (source language)
		$is_italian = ( 'it' === $current_lang );
		if ( $is_italian ) {
			return $items; // Show IT menu as-is
		}

		// On EN version, try to get EN menu
		$menu_id = is_object( $menu ) ? $menu->term_id : 0;

		if ( ! $menu_id ) {
			// Try to get from theme location
			if ( isset( $args->theme_location ) ) {
				$en_locations = get_option( 'fpml_en_menu_locations', array() );
				if ( isset( $en_locations[ $args->theme_location ] ) ) {
					$en_menu_id = $en_locations[ $args->theme_location ];
					$en_items = wp_get_nav_menu_items( $en_menu_id );
					return $en_items ? $en_items : $items;
				}
			}
			return $items;
		}

		// Check if this is IT menu and EN version exists
		$en_menu_id = get_term_meta( $menu_id, '_fpml_menu_en_id', true );

		if ( $en_menu_id ) {
			// Load EN menu items instead
			$en_items = wp_get_nav_menu_items( $en_menu_id );
			return $en_items ? $en_items : $items;
		}

		return $items;
	}

	/**
	 * Get current language based on URL.
	 *
	 * @return string 'it' or 'en'
	 */
	protected function get_current_language(): string {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER

		// Check against all enabled languages
		if ( class_exists( '\FP\Multilanguage\MultiLanguage\LanguageManager' ) ) {
			$language_manager = fpml_get_language_manager();
			$enabled_languages = $language_manager->get_enabled_languages();
			$all_languages = $language_manager->get_all_languages();
			
			foreach ( $enabled_languages as $lang_code ) {
				if ( ! isset( $all_languages[ $lang_code ] ) ) {
					continue;
				}
				$lang_info = $all_languages[ $lang_code ];
				if ( $lang_info && ! empty( $lang_info['slug'] ) ) {
					$lang_slug = trim( $lang_info['slug'], '/' );
					if ( strpos( $request_uri, '/' . $lang_slug . '/' ) === 0 || '/' . $lang_slug === $request_uri ) {
						return $lang_code;
					}
				}
			}
		}
		
		// Fallback: check for /en/ for backward compatibility
		if ( strpos( $request_uri, '/en/' ) === 0 || '/en' === $request_uri ) {
			return 'en';
		}

		return 'it';
	}
}
















