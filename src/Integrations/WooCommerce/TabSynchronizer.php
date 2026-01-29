<?php
/**
 * WooCommerce Tab Synchronizer - Syncs product tabs (custom).
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Integrations\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Syncs product tabs (custom).
 *
 * @since 0.10.0
 */
class TabSynchronizer {
	/**
	 * Logger instance.
	 *
	 * @var \FP\Multilanguage\Logger|null
	 */
	protected $logger = null;

	/**
	 * Constructor.
	 *
	 * @param \FP\Multilanguage\Logger|null $logger Logger instance.
	 */
	public function __construct( $logger = null ) {
		$this->logger = $logger;
	}

	/**
	 * Sync product tabs (custom).
	 *
	 * @since 0.10.0
	 *
	 * @param int $translated_id Translated product ID.
	 * @param int $original_id   Original product ID.
	 *
	 * @return void
	 */
	public function sync_product_tabs( int $translated_id, int $original_id ): void {
		if ( 'product' !== get_post_type( $original_id ) ) {
			return;
		}

		// Check for custom product tabs (common plugin/theme feature)
		$tab_titles = get_post_meta( $original_id, '_product_tab_title', false );
		$tab_contents = get_post_meta( $original_id, '_product_tab_content', false );

		if ( empty( $tab_titles ) && empty( $tab_contents ) ) {
			return;
		}

		// Mark tabs for translation
		if ( ! empty( $tab_titles ) ) {
			foreach ( $tab_titles as $index => $title ) {
				if ( $title ) {
					update_post_meta( $translated_id, '_product_tab_title', '[PENDING TRANSLATION] ' . $title );
				}
			}
		}

		if ( ! empty( $tab_contents ) ) {
			foreach ( $tab_contents as $index => $content ) {
				if ( $content ) {
					update_post_meta( $translated_id, '_product_tab_content', '[PENDING TRANSLATION] ' . $content );
				}
			}
		}

		$this->log( 'Product tabs synced', array(
			'tabs_count' => max( count( $tab_titles ), count( $tab_contents ) ),
		) );
	}

	/**
	 * Log integration actions.
	 *
	 * @param string $message Log message.
	 * @param array  $context Context data.
	 *
	 * @return void
	 */
	protected function log( string $message, array $context = array() ): void {
		if ( ! $this->logger ) {
			return;
		}

		$this->logger->log(
			'info',
			'WooCommerce Integration: ' . $message,
			array_merge(
				array( 'context' => 'woocommerce_integration' ),
				$context
			)
		);
	}
}
















