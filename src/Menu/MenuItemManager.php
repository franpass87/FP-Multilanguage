<?php
/**
 * Menu Item Manager - Handles menu item creation and synchronization.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Menu;

use FP\Multilanguage\Foundation\Logger\LoggerInterface;
use FP\Multilanguage\Foundation\Logger\LoggerAdapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles menu item creation and synchronization.
 *
 * @since 0.10.0
 */
class MenuItemManager {
	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface|LoggerAdapter $logger Logger instance.
	 */
	public function __construct( $logger ) {
		// If LoggerAdapter is passed, get the wrapped LoggerInterface
		if ( $logger instanceof LoggerAdapter ) {
			$logger = $logger->getWrapped();
		}
		$this->logger = $logger;
	}

	/**
	 * Sync menu items from IT to EN menu.
	 *
	 * @param int $source_menu_id IT menu ID.
	 * @param int $target_menu_id EN menu ID.
	 *
	 * @return void
	 */
	public function sync_menu_items( int $source_menu_id, int $target_menu_id ): void {
		// Get source menu items
		$source_items = wp_get_nav_menu_items( $source_menu_id );

		if ( ! $source_items ) {
			return;
		}

		// Get existing target items to avoid duplicates
		$existing_items = wp_get_nav_menu_items( $target_menu_id );
		$existing_map = array();

		if ( $existing_items ) {
			foreach ( $existing_items as $item ) {
				$source_item_id = get_post_meta( $item->ID, '_fpml_menu_item_source_id', true );
				if ( $source_item_id ) {
					$existing_map[ $source_item_id ] = $item->ID;
				}
			}
		}

		// Map to store parent relationships
		$parent_map = array();

		foreach ( $source_items as $source_item ) {
			// Check if item already exists
			if ( isset( $existing_map[ $source_item->ID ] ) ) {
				$target_item_id = $existing_map[ $source_item->ID ];
				$this->update_menu_item( $target_item_id, $source_item, $target_menu_id, $parent_map );
			} else {
				$target_item_id = $this->create_menu_item( $source_item, $target_menu_id, $parent_map );
				
				if ( $target_item_id ) {
					// Store mapping
					update_post_meta( $target_item_id, '_fpml_menu_item_source_id', $source_item->ID );
					update_post_meta( $source_item->ID, '_fpml_menu_item_target_id', $target_item_id );
				}
			}

			// Store parent mapping for child items
			if ( $target_item_id ) {
				$parent_map[ $source_item->ID ] = $target_item_id;
			}
		}

		$this->logger->log(
			'info',
			'Menu Sync: Menu items synced',
			array(
				'context' => 'menu_sync',
				'items_count' => count( $source_items ),
			)
		);
	}

	/**
	 * Sync menu item custom fields (Salient icons, mega menu settings, etc).
	 *
	 * @param int $source_menu_id IT menu ID.
	 * @param int $target_menu_id EN menu ID.
	 *
	 * @return void
	 */
	public function sync_menu_item_custom_fields( int $source_menu_id, int $target_menu_id ): void {
		$source_items = wp_get_nav_menu_items( $source_menu_id );
		$target_items = wp_get_nav_menu_items( $target_menu_id );

		if ( ! $source_items || ! $target_items ) {
			return;
		}

		// Build map of source → target items
		$item_map = array();
		foreach ( $target_items as $target_item ) {
			$source_item_id = get_post_meta( $target_item->ID, '_fpml_menu_item_source_id', true );
			if ( $source_item_id ) {
				$item_map[ $source_item_id ] = $target_item->ID;
			}
		}

		$synced_count = 0;

		// Salient menu custom fields (non-translatable)
		$salient_menu_fields = array(
			// Icons
			'_menu_item_icon',
			'_menu_item_icon_image',
			
			// Mega Menu settings
			'_menu_item_mega_menu',
			'_menu_item_mega_menu_width',
			'_menu_item_mega_menu_alignment',
			'_menu_item_mega_menu_bg_img',
			'_menu_item_mega_menu_bg_img_alignment',
			'_menu_item_disable_mega_menu_title',
			'_menu_item_mega_menu_global_section',
			
			// Menu columns
			'_menu_item_is_column',
			'_menu_item_column_width',
			
			// Button styling
			'_menu_item_button_style',
			'_menu_item_button_color',
			
			// Hide/show
			'_menu_item_hide_label',
			'_menu_item_hide_on_mobile',
			'_menu_item_hide_on_desktop',
		);

		foreach ( $source_items as $source_item ) {
			if ( ! isset( $item_map[ $source_item->ID ] ) ) {
				continue;
			}

			$target_item_id = $item_map[ $source_item->ID ];

			// Copy all custom fields
			foreach ( $salient_menu_fields as $field ) {
				$value = get_post_meta( $source_item->ID, $field, true );
				if ( $value ) {
					update_post_meta( $target_item_id, $field, $value );
					$synced_count++;
				}
			}
		}

		if ( $synced_count > 0 ) {
			$this->logger->log(
				'info',
				'Menu Sync: Menu item custom fields synced',
				array(
					'context' => 'menu_sync',
					'fields_synced' => $synced_count,
				)
			);
		}
	}

	/**
	 * Create a menu item in EN menu.
	 *
	 * @param object $source_item  Source menu item.
	 * @param int    $menu_id      Target menu ID.
	 * @param array  $parent_map   Parent ID mapping.
	 * @return int|false Menu item ID or false on failure.
	 */
	protected function create_menu_item( $source_item, int $menu_id, array $parent_map ) {
		$args = $this->prepare_menu_item_args( $source_item, $menu_id, $parent_map );

		$item_id = wp_update_nav_menu_item( $menu_id, 0, $args );

		if ( is_wp_error( $item_id ) ) {
			$this->logger->log(
				'error',
				'Menu Sync: Failed to create menu item',
				array(
					'context' => 'menu_sync',
					'error' => $item_id->get_error_message(),
				)
			);
			return false;
		}

		return $item_id;
	}

	/**
	 * Update an existing menu item.
	 *
	 * @param int    $item_id      Menu item ID to update.
	 * @param object $source_item  Source menu item.
	 * @param int    $menu_id      Target menu ID.
	 * @param array  $parent_map   Parent ID mapping.
	 *
	 * @return void
	 */
	protected function update_menu_item( int $item_id, $source_item, int $menu_id, array $parent_map ): void {
		$args = $this->prepare_menu_item_args( $source_item, $menu_id, $parent_map );

		wp_update_nav_menu_item( $menu_id, $item_id, $args );
	}

	/**
	 * Prepare menu item args for creation/update.
	 *
	 * @param object $source_item  Source menu item.
	 * @param int    $menu_id      Target menu ID.
	 * @param array  $parent_map   Parent ID mapping.
	 * @return array
	 */
	protected function prepare_menu_item_args( $source_item, int $menu_id, array $parent_map ): array { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$args = array(
			'menu-item-title'       => $source_item->title,
			'menu-item-url'         => $source_item->url,
			'menu-item-description' => $source_item->description,
			'menu-item-attr-title'  => $source_item->attr_title,
			'menu-item-target'      => $source_item->target,
			'menu-item-classes'     => implode( ' ', $source_item->classes ),
			'menu-item-xfn'         => $source_item->xfn,
			'menu-item-status'      => 'publish',
			'menu-item-position'    => $source_item->menu_order,
		);

		// Handle parent relationship
		if ( $source_item->menu_item_parent && isset( $parent_map[ $source_item->menu_item_parent ] ) ) {
			$args['menu-item-parent-id'] = $parent_map[ $source_item->menu_item_parent ];
		} else {
			$args['menu-item-parent-id'] = 0;
		}

		// Handle different menu item types
		switch ( $source_item->type ) {
			case 'post_type':
				// Map to EN post if exists
				$en_post_id = get_post_meta( $source_item->object_id, '_fpml_pair_id', true );
				if ( $en_post_id ) {
					$args['menu-item-type']      = 'post_type';
					$args['menu-item-object']    = $source_item->object;
					$args['menu-item-object-id'] = $en_post_id;
				} else {
					// EN post doesn't exist yet, use custom link as fallback
					$args['menu-item-type'] = 'custom';
					// Get current language slug for URL construction
					$current_lang = $this->get_current_language();
					$language_manager = fpml_get_language_manager();
					$lang_info = $language_manager->get_language_info( $current_lang );
					$lang_slug = $lang_info ? trim( $lang_info['slug'], '/' ) : 'en';
					$args['menu-item-url']  = home_url( '/' . $lang_slug . parse_url( $source_item->url, PHP_URL_PATH ) );
				}
				break;

			case 'taxonomy':
				// Map to EN term if exists
				$en_term_id = get_term_meta( $source_item->object_id, '_fpml_term_en_id', true );
				if ( $en_term_id ) {
					$args['menu-item-type']      = 'taxonomy';
					$args['menu-item-object']    = $source_item->object;
					$args['menu-item-object-id'] = $en_term_id;
				} else {
					// EN term doesn't exist, use custom link
					$args['menu-item-type'] = 'custom';
					// Get current language slug for URL construction
					$current_lang = $this->get_current_language();
					$language_manager = fpml_get_language_manager();
					$lang_info = $language_manager->get_language_info( $current_lang );
					$lang_slug = $lang_info ? trim( $lang_info['slug'], '/' ) : 'en';
					$args['menu-item-url']  = home_url( '/' . $lang_slug . parse_url( $source_item->url, PHP_URL_PATH ) );
				}
				break;

			case 'custom':
			default:
				// Custom link - translate URL to target language version
				$args['menu-item-type'] = 'custom';
				$original_url = $source_item->url;
				
				// If it's an internal link, add language prefix
				if ( strpos( $original_url, home_url() ) === 0 ) {
					$path = str_replace( home_url(), '', $original_url );
					// Get current language slug for URL construction
					$current_lang = $this->get_current_language();
					$language_manager = fpml_get_language_manager();
					$lang_info = $language_manager->get_language_info( $current_lang );
					$lang_slug = $lang_info ? trim( $lang_info['slug'], '/' ) : 'en';
					$args['menu-item-url'] = home_url( '/' . $lang_slug . $path );
				} else {
					// External link - keep as-is
					$args['menu-item-url'] = $original_url;
				}
				break;
		}

		// Menu item title - Keep original for now
		// Will be translated via post_title when menu item post is processed
		// OR via custom translation queue for menu items
		if ( $source_item->title ) {
			$args['menu-item-title'] = $source_item->title;
			
			// Store original title for later translation queue
			// This will be picked up by the translation system
		}
		
		// Translate description if present
		if ( $source_item->description ) {
			// Queue description for translation
			$args['menu-item-description'] = '[PENDING TRANSLATION] ' . $source_item->description;
		}
		
		// Translate attr_title (tooltip) if present
		if ( $source_item->attr_title ) {
			// Queue attr_title for translation
			$args['menu-item-attr-title'] = '[PENDING TRANSLATION] ' . $source_item->attr_title;
		}

		return $args;
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
















