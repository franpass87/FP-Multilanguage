<?php
/**
 * Menu Synchronizer - Handles menu synchronization logic.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Menu;

use FP\Multilanguage\Foundation\Logger\LoggerInterface;
use FP\Multilanguage\Foundation\Logger\LoggerAdapter;
use FP\Multilanguage\Foundation\Options\SettingsAdapter;
use FP\Multilanguage\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles menu synchronization logic.
 *
 * @since 0.10.0
 */
class MenuSynchronizer {
	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * Settings instance.
	 *
	 * @var Settings|SettingsAdapter
	 */
	protected $settings;

	/**
	 * Menu item manager instance.
	 *
	 * @var MenuItemManager
	 */
	protected MenuItemManager $item_manager;

	/**
	 * Menu location manager instance.
	 *
	 * @var MenuLocationManager
	 */
	protected MenuLocationManager $location_manager;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface|LoggerAdapter $logger          Logger instance.
	 * @param Settings|SettingsAdapter $settings        Settings instance.
	 * @param MenuItemManager      $item_manager    Menu item manager.
	 * @param MenuLocationManager  $location_manager Menu location manager.
	 */
	public function __construct( $logger, $settings, MenuItemManager $item_manager, MenuLocationManager $location_manager ) {
		// If LoggerAdapter is passed, get the wrapped LoggerInterface
		if ( $logger instanceof LoggerAdapter ) {
			$logger = $logger->getWrapped();
		}
		$this->logger = $logger;
		$this->settings = $settings;
		$this->item_manager = $item_manager;
		$this->location_manager = $location_manager;
	}

	/**
	 * Auto-sync menu when it's updated.
	 *
	 * @param int   $menu_id   Menu ID.
	 * @param array $menu_data Menu data.
	 *
	 * @return void
	 */
	public function auto_sync_menu( int $menu_id, array $menu_data = array() ): void { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		// Check if auto-sync is enabled
		if ( ! $this->settings->get( 'menu_auto_sync', true ) ) {
			return;
		}

		$this->sync_menu( $menu_id );
	}

	/**
	 * Sync a menu to create/update its English version.
	 *
	 * @param int $source_menu_id IT menu ID.
	 * @return int|false EN menu ID or false on failure.
	 */
	public function sync_menu( int $source_menu_id ) {
		$source_menu = wp_get_nav_menu_object( $source_menu_id );

		if ( ! $source_menu ) {
			return false;
		}

		$this->log( 'Starting menu sync', array(
			'source_menu_id'   => $source_menu_id,
			'source_menu_name' => $source_menu->name,
		) );

		// Check if EN menu already exists
		$target_menu_id = get_term_meta( $source_menu_id, '_fpml_menu_en_id', true );

		if ( $target_menu_id ) {
			$target_menu = wp_get_nav_menu_object( $target_menu_id );
			if ( ! $target_menu ) {
				// EN menu was deleted, create new
				$target_menu_id = false;
			}
		}

		// Create EN menu if doesn't exist
		if ( ! $target_menu_id ) {
			$target_menu_id = $this->create_en_menu( $source_menu );

			if ( ! $target_menu_id ) {
				return false;
			}

			// Store relationship
			update_term_meta( $source_menu_id, '_fpml_menu_en_id', $target_menu_id );
			update_term_meta( $target_menu_id, '_fpml_menu_source_id', $source_menu_id );
		}

		// Sync menu items
		$this->item_manager->sync_menu_items( $source_menu_id, $target_menu_id );

		// Sync menu item custom fields (Salient icons, mega menu, etc)
		$this->item_manager->sync_menu_item_custom_fields( $source_menu_id, $target_menu_id );

		// Sync menu locations
		$this->location_manager->sync_menu_locations( $source_menu_id, $target_menu_id );

		$this->log( 'Menu sync completed', array(
			'source_menu_id' => $source_menu_id,
			'target_menu_id' => $target_menu_id,
		) );

		return $target_menu_id;
	}

	/**
	 * Create English version of a menu.
	 *
	 * @param \WP_Term $source_menu Source menu object.
	 * @return int|false EN menu ID or false on failure.
	 */
	protected function create_en_menu( \WP_Term $source_menu ) {
		$en_menu_name = $source_menu->name . ' (EN)';

		// Check if menu with this name exists
		$existing = wp_get_nav_menu_object( $en_menu_name );
		if ( $existing ) {
			return $existing->term_id;
		}

		// Create new menu
		$result = wp_create_nav_menu( $en_menu_name );

		if ( is_wp_error( $result ) ) {
			$this->log( 'Failed to create EN menu', array(
				'error' => $result->get_error_message(),
			), 'error' );
			return false;
		}

		$this->log( 'EN menu created', array(
			'menu_id'   => $result,
			'menu_name' => $en_menu_name,
		) );

		return $result;
	}

	/**
	 * Handle menu deletion - delete EN menu when IT menu is deleted.
	 *
	 * @param int $menu_id Deleted menu ID.
	 *
	 * @return void
	 */
	public function handle_menu_deletion( int $menu_id ): void {
		// Check if this menu has an EN counterpart
		$en_menu_id = get_term_meta( $menu_id, '_fpml_menu_en_id', true );

		if ( $en_menu_id ) {
			// Delete EN menu
			wp_delete_nav_menu( $en_menu_id );
			
			$this->log( 'EN menu deleted (orphan cleanup)', array(
				'it_menu_id' => $menu_id,
				'en_menu_id' => $en_menu_id,
			) );
		}

		// Also check if this IS an EN menu (being deleted manually)
		$source_menu_id = get_term_meta( $menu_id, '_fpml_menu_source_id', true );
		if ( $source_menu_id ) {
			// Clean up relationship
			delete_term_meta( $source_menu_id, '_fpml_menu_en_id' );
			
			$this->log( 'EN menu relationship cleaned', array(
				'en_menu_id' => $menu_id,
				'it_menu_id' => $source_menu_id,
			) );
		}
	}

	/**
	 * Log menu sync actions.
	 *
	 * @param string $message Log message.
	 * @param array  $context Context data.
	 * @param string $level   Log level (info, warning, error).
	 *
	 * @return void
	 */
	protected function log( string $message, array $context = array(), string $level = 'info' ): void {
		$this->logger->log(
			$level,
			'Menu Sync: ' . $message,
			array_merge(
				array( 'context' => 'menu_sync' ),
				$context
			)
		);
	}
}
















