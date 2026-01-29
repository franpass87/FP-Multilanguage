<?php
/**
 * Menu Location Manager - Handles menu location synchronization.
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
 * Handles menu location synchronization.
 *
 * @since 0.10.0
 */
class MenuLocationManager {
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
	 * Sync menu locations (assign EN menu to EN theme locations).
	 *
	 * Stores EN menu location associations in option for frontend switching.
	 * Does NOT override IT menu locations to avoid breaking IT navigation.
	 *
	 * @param int $source_menu_id IT menu ID.
	 * @param int $target_menu_id EN menu ID.
	 *
	 * @return void
	 */
	public function sync_menu_locations( int $source_menu_id, int $target_menu_id ): void {
		$locations = get_nav_menu_locations();

		if ( empty( $locations ) ) {
			return;
		}

		// Get or initialize EN menu locations map
		$en_locations_map = get_option( 'fpml_en_menu_locations', array() );

		$synced_count = 0;

		foreach ( $locations as $location => $menu_id ) {
			if ( $menu_id == $source_menu_id ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
				// Store EN menu for this location
				$en_locations_map[ $location ] = $target_menu_id;
				$synced_count++;
				
				$this->logger->log(
					'info',
					'Menu Sync: Menu location mapped',
					array(
						'context' => 'menu_sync',
						'location'    => $location,
						'it_menu_id'  => $source_menu_id,
						'en_menu_id'  => $target_menu_id,
					)
				);
			}
		}

		if ( $synced_count > 0 ) {
			// Save EN locations map
			update_option( 'fpml_en_menu_locations', $en_locations_map, false );
			
			$this->logger->log(
				'info',
				'Menu Sync: Menu locations synced',
				array(
					'context' => 'menu_sync',
					'locations_count' => $synced_count,
					'total_en_menus'  => count( $en_locations_map ),
				)
			);
		}
	}
}
















