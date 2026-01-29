<?php
/**
 * WooCommerce Relation Synchronizer - Syncs product relations (upsell, cross-sell).
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
 * Syncs product relations (upsell, cross-sell).
 *
 * @since 0.10.0
 */
class RelationSynchronizer {
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
	 * Sync product relations (upsell, cross-sell).
	 *
	 * Maps IT product IDs to EN product IDs.
	 *
	 * @since 0.10.0
	 *
	 * @param int $translated_id Translated product ID.
	 * @param int $original_id   Original product ID.
	 *
	 * @return void
	 */
	public function sync_product_relations( int $translated_id, int $original_id ): void {
		if ( 'product' !== get_post_type( $original_id ) ) {
			return;
		}

		$source_product = wc_get_product( $original_id );
		$target_product = wc_get_product( $translated_id );

		if ( ! $source_product || ! $target_product ) {
			return;
		}

		// Map upsell IDs
		$upsell_ids = $source_product->get_upsell_ids();
		if ( ! empty( $upsell_ids ) ) {
			$mapped_upsells = $this->map_product_ids( $upsell_ids );
			$target_product->set_upsell_ids( $mapped_upsells );
		}

		// Map cross-sell IDs
		$crosssell_ids = $source_product->get_cross_sell_ids();
		if ( ! empty( $crosssell_ids ) ) {
			$mapped_crosssells = $this->map_product_ids( $crosssell_ids );
			$target_product->set_cross_sell_ids( $mapped_crosssells );
		}

		$target_product->save();

		$this->log( 'Product relations synced', array(
			'upsells'    => count( $upsell_ids ),
			'crosssells' => count( $crosssell_ids ),
		) );
	}

	/**
	 * Map IT product IDs to EN product IDs.
	 *
	 * @since 0.10.0
	 *
	 * @param array $product_ids IT product IDs.
	 * @return array EN product IDs.
	 */
	protected function map_product_ids( array $product_ids ): array {
		if ( empty( $product_ids ) ) {
			return array();
		}

		$mapped_ids = array();

		foreach ( $product_ids as $product_id ) {
			// Get EN version of product
			$en_product_id = get_post_meta( $product_id, '_fpml_pair_id', true );
			
			if ( $en_product_id ) {
				$mapped_ids[] = (int) $en_product_id;
			} else {
				// EN version doesn't exist yet - keep original ID as fallback
				// It will be fixed when that product gets translated
				$mapped_ids[] = (int) $product_id;
			}
		}

		return array_filter( $mapped_ids );
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
















