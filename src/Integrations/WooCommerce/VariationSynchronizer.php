<?php
/**
 * WooCommerce Variation Synchronizer - Syncs product variations.
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
 * Syncs product variations.
 *
 * @since 0.10.0
 */
class VariationSynchronizer {
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
	 * Sync product variations to translated product.
	 *
	 * @since 0.10.0
	 *
	 * @param int $translated_id Translated product ID.
	 * @param int $original_id   Original product ID.
	 *
	 * @return void
	 */
	public function sync_product_variations( int $translated_id, int $original_id ): void {
		// Only process products
		if ( 'product' !== get_post_type( $original_id ) ) {
			return;
		}

		// Get WooCommerce product objects
		$source_product = wc_get_product( $original_id );
		$target_product = wc_get_product( $translated_id );

		if ( ! $source_product || ! $target_product ) {
			return;
		}

		// Only process variable products
		if ( ! $source_product->is_type( 'variable' ) ) {
			return;
		}

		$this->log( 'Starting variations sync', array(
			'source_id' => $original_id,
			'target_id' => $translated_id,
		) );

		// Get source variations
		$source_variations = $source_product->get_children();

		if ( empty( $source_variations ) ) {
			return;
		}

		// Check if target already has variations
		$existing_variations = $target_product->get_children();

		// Map to avoid duplicates
		$variations_map = array();
		foreach ( $existing_variations as $existing_var_id ) {
			$source_var_id = get_post_meta( $existing_var_id, '_fpml_variation_source_id', true );
			if ( $source_var_id ) {
				$variations_map[ $source_var_id ] = $existing_var_id;
			}
		}

		// Sync each variation
		foreach ( $source_variations as $source_variation_id ) {
			$source_variation = wc_get_product( $source_variation_id );

			if ( ! $source_variation ) {
				continue;
			}

			// Check if variation already exists
			if ( isset( $variations_map[ $source_variation_id ] ) ) {
				$target_variation_id = $variations_map[ $source_variation_id ];
				$this->update_variation( $target_variation_id, $source_variation, $translated_id );
			} else {
				// Create new variation
				$target_variation_id = $this->create_variation( $source_variation, $translated_id, $original_id );
				
				if ( $target_variation_id ) {
					// Store mapping
					update_post_meta( $target_variation_id, '_fpml_variation_source_id', $source_variation_id );
					update_post_meta( $source_variation_id, '_fpml_variation_target_id', $target_variation_id );
				}
			}
		}

		$this->log( 'Variations sync completed', array(
			'variations_count' => count( $source_variations ),
		) );
	}

	/**
	 * Create a new product variation.
	 *
	 * @since 0.10.0
	 *
	 * @param \WC_Product_Variation $source_variation Source variation.
	 * @param int                   $parent_id         Target product ID.
	 * @param int                   $source_parent_id  Source product ID.
	 * @return int|false Variation ID or false on failure.
	 */
	protected function create_variation( $source_variation, int $parent_id, int $source_parent_id ) {
		$variation_post = array(
			'post_title'  => 'Product #' . $parent_id . ' Variation',
			'post_name'   => 'product-' . $parent_id . '-variation',
			'post_status' => 'publish',
			'post_parent' => $parent_id,
			'post_type'   => 'product_variation',
			'menu_order'  => $source_variation->get_menu_order(),
		);

		$variation_id = \fpml_safe_insert_post( $variation_post );

		if ( is_wp_error( $variation_id ) || ! $variation_id ) {
			$this->log( 'Failed to create variation', array(
				'error' => is_wp_error( $variation_id ) ? $variation_id->get_error_message() : 'Unknown error',
			), 'error' );
			return false;
		}

		// Update variation with source data
		$this->update_variation( $variation_id, $source_variation, $parent_id );

		return $variation_id;
	}

	/**
	 * Update variation data.
	 *
	 * @since 0.10.0
	 *
	 * @param int                  $variation_id     Variation ID to update.
	 * @param \WC_Product_Variation $source_variation Source variation.
	 * @param int                  $parent_id        Parent product ID.
	 *
	 * @return void
	 */
	protected function update_variation( int $variation_id, $source_variation, int $parent_id ): void {
		$variation = wc_get_product( $variation_id );

		if ( ! $variation ) {
			return;
		}

		// Sync variation attributes
		$attributes = $source_variation->get_variation_attributes();
		$variation->set_attributes( $attributes );

		// Sync prices (copy as-is, no translation needed)
		$variation->set_regular_price( $source_variation->get_regular_price() );
		$variation->set_sale_price( $source_variation->get_sale_price() );
		$variation->set_price( $source_variation->get_price() );

		// Sync stock
		$variation->set_stock_status( $source_variation->get_stock_status() );
		$variation->set_manage_stock( $source_variation->get_manage_stock() );
		if ( $source_variation->get_manage_stock() ) {
			$variation->set_stock_quantity( $source_variation->get_stock_quantity() );
		}

		// Sync dimensions
		$variation->set_weight( $source_variation->get_weight() );
		$variation->set_length( $source_variation->get_length() );
		$variation->set_width( $source_variation->get_width() );
		$variation->set_height( $source_variation->get_height() );

		// Sync SKU (copy as-is)
		$variation->set_sku( $source_variation->get_sku() );

		// Sync image
		$image_id = $source_variation->get_image_id();
		if ( $image_id ) {
			$variation->set_image_id( $image_id );
		}

		// Sync variation description (needs translation)
		$description = $source_variation->get_description();
		if ( $description ) {
			// Mark for translation via queue
			$variation->set_description( '[PENDING TRANSLATION] ' . $description );
			
			// Add to meta for translation queue
			update_post_meta( $variation_id, '_variation_description', '[PENDING TRANSLATION] ' . $description );
		}

		// Save variation
		$variation->save();

		$this->log( 'Variation updated', array(
			'variation_id' => $variation_id,
			'parent_id'    => $parent_id,
		) );
	}

	/**
	 * Log integration actions.
	 *
	 * @param string $message Log message.
	 * @param array  $context Context data.
	 * @param string $level   Log level (info, warning, error).
	 *
	 * @return void
	 */
	protected function log( string $message, array $context = array(), string $level = 'info' ): void {
		if ( ! $this->logger ) {
			return;
		}

		$this->logger->log(
			$level,
			'WooCommerce Integration: ' . $message,
			array_merge(
				array( 'context' => 'woocommerce_integration' ),
				$context
			)
		);
	}
}
















