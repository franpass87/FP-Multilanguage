<?php
/**
 * Menu AJAX Handler - Handles AJAX requests for menu synchronization.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Menu;

use FP\Multilanguage\Menu\MenuSynchronizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles AJAX requests for menu synchronization.
 *
 * @since 0.10.0
 */
class MenuAjax {
	/**
	 * Menu synchronizer instance.
	 *
	 * @var MenuSynchronizer
	 */
	protected MenuSynchronizer $synchronizer;

	/**
	 * Constructor.
	 *
	 * @param MenuSynchronizer $synchronizer Menu synchronizer instance.
	 */
	public function __construct( MenuSynchronizer $synchronizer ) {
		$this->synchronizer = $synchronizer;
	}

	/**
	 * AJAX handler for manual menu sync.
	 *
	 * @return void
	 */
	public function ajax_sync_menu(): void {
		check_ajax_referer( 'fpml_sync_menu', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permessi insufficienti' ) );
		}

		$menu_id = isset( $_POST['menu_id'] ) ? absint( $_POST['menu_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $menu_id ) {
			wp_send_json_error( array( 'message' => 'Menu ID non valido' ) );
		}

		$result = $this->synchronizer->sync_menu( $menu_id );

		if ( $result ) {
			wp_send_json_success( array(
				'message'    => 'Menu sincronizzato con successo',
				'en_menu_id' => $result,
			) );
		} else {
			wp_send_json_error( array( 'message' => 'Errore durante la sincronizzazione' ) );
		}
	}

	/**
	 * AJAX get menu status.
	 *
	 * @return void
	 */
	public function ajax_get_menu_status(): void {
		check_ajax_referer( 'fpml_menu_status', '_wpnonce' );

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permessi insufficienti' ) );
		}

		$menu_id = isset( $_POST['menu_id'] ) ? absint( $_POST['menu_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $menu_id ) {
			wp_send_json_error( array( 'message' => 'Menu ID non valido' ) );
		}

		$en_menu_id = get_term_meta( $menu_id, '_fpml_menu_en_id', true );

		if ( $en_menu_id ) {
			$en_menu = wp_get_nav_menu_object( $en_menu_id );
			if ( $en_menu ) {
				$items = wp_get_nav_menu_items( $en_menu_id );
				wp_send_json_success( array(
					'has_en_menu'   => true,
					'en_menu_id'    => $en_menu_id,
					'en_menu_name'  => $en_menu->name,
					'items_count'   => count( $items ),
				) );
			}
		}

		wp_send_json_success( array(
			'has_en_menu' => false,
		) );
	}
}
















