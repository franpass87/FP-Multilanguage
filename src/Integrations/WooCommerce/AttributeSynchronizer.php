<?php
/**
 * WooCommerce Attribute Synchronizer - Syncs product attributes.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Integrations\WooCommerce;

use FP\Multilanguage\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Syncs product attributes.
 *
 * @since 0.10.0
 */
class AttributeSynchronizer {
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
	 * Sync product attributes.
	 *
	 * @since 0.10.0
	 *
	 * @param int $translated_id Translated product ID.
	 * @param int $original_id   Original product ID.
	 *
	 * @return void
	 */
	public function sync_product_attributes( int $translated_id, int $original_id ): void {
		if ( 'product' !== get_post_type( $original_id ) ) {
			return;
		}

		$source_product = wc_get_product( $original_id );
		$target_product = wc_get_product( $translated_id );

		if ( ! $source_product || ! $target_product ) {
			return;
		}

		// Get source attributes
		$attributes = $source_product->get_attributes();

		if ( empty( $attributes ) ) {
			return;
		}

		// Translate attribute labels
		$translated_attributes = array();
		$needs_translation = false;

		foreach ( $attributes as $attribute_name => $attribute ) {
			// Clone attribute
			$translated_attr = clone $attribute;

			// Translate attribute name (if custom)
			if ( $attribute->get_id() === 0 ) {
				// Custom attribute - save original and queue for translation
				$label = $attribute->get_name();
				// Save original label - will be translated by queue job
				$translated_attr->set_name( $label );
				$needs_translation = true;
			}

			// Translate attribute options (if custom)
			if ( $attribute->get_id() === 0 && ! $attribute->is_taxonomy() ) {
				$options = $attribute->get_options();
				// Save original options - will be translated by queue job
				$translated_attr->set_options( $options );
				$needs_translation = true;
			}

			$translated_attributes[ $attribute_name ] = $translated_attr;
		}

		// Set translated attributes
		$target_product->set_attributes( $translated_attributes );
		$target_product->save();

		// Queue translation job for product attributes if needed
		if ( $needs_translation ) {
			$queue = Container::get( 'queue' );
			if ( ! $queue ) {
				$queue = fpml_get_queue();
			}

			if ( $queue ) {
				// Get product attributes meta value
				$attributes_meta = get_post_meta( $original_id, '_product_attributes', true );
				if ( ! empty( $attributes_meta ) ) {
					$hash = md5( wp_json_encode( $attributes_meta ) );
					$queue->enqueue( 'post', $original_id, 'meta:_product_attributes', $hash );
					
					$this->log( 'Product attributes queued for translation', array(
						'product_id' => $original_id,
						'translated_id' => $translated_id,
						'attributes_count' => count( $translated_attributes ),
					) );
				}
			}
		}

		$this->log( 'Product attributes synced', array(
			'attributes_count' => count( $translated_attributes ),
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
















