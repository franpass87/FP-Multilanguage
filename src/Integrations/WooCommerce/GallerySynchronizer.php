<?php
/**
 * WooCommerce Gallery Synchronizer - Syncs product gallery images with alt text.
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
 * Syncs product gallery images with alt text.
 *
 * @since 0.10.0
 */
class GallerySynchronizer {
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
	 * Sync product gallery images with alt text.
	 *
	 * @since 0.10.0
	 *
	 * @param int $translated_id Translated product ID.
	 * @param int $original_id   Original product ID.
	 *
	 * @return void
	 */
	public function sync_product_gallery( int $translated_id, int $original_id ): void {
		if ( 'product' !== get_post_type( $original_id ) ) {
			return;
		}

		$source_product = wc_get_product( $original_id );
		$target_product = wc_get_product( $translated_id );

		if ( ! $source_product || ! $target_product ) {
			return;
		}

		// Sync featured image
		$featured_image_id = $source_product->get_image_id();
		if ( $featured_image_id ) {
			$target_product->set_image_id( $featured_image_id );
		}

		// Sync gallery images (IDs)
		$gallery_ids = $source_product->get_gallery_image_ids();
		if ( ! empty( $gallery_ids ) ) {
			$target_product->set_gallery_image_ids( $gallery_ids );
			
			// Queue gallery alt text for translation
			foreach ( $gallery_ids as $image_id ) {
				$alt_text = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
				if ( $alt_text ) {
					// Alt text will be translated via whitelist
					// (already in FP-Multilanguage core)
				}
			}
		}

		$target_product->save();

		$this->log( 'Product gallery synced', array(
			'featured_image' => $featured_image_id ? 'yes' : 'no',
			'gallery_count'  => count( $gallery_ids ),
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
















