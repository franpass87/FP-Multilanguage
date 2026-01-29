<?php
/**
 * WooCommerce Whitelist Manager - Manages WooCommerce post types, taxonomies, and meta in whitelist.
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
 * Manages WooCommerce post types, taxonomies, and meta in whitelist.
 *
 * @since 0.10.0
 */
class WhitelistManager {
	/**
	 * Add product taxonomies.
	 *
	 * @since 0.10.0
	 *
	 * @param array $taxonomies Translatable taxonomies.
	 * @return array
	 */
	public function add_product_taxonomies( array $taxonomies ): array {
		$wc_taxonomies = array( 'product_cat', 'product_tag' );
		
		// Add product_brand if exists (from extensions)
		if ( taxonomy_exists( 'product_brand' ) ) {
			$wc_taxonomies[] = 'product_brand';
		}

		foreach ( $wc_taxonomies as $taxonomy ) {
			if ( ! in_array( $taxonomy, $taxonomies, true ) ) {
				$taxonomies[] = $taxonomy;
			}
		}

		return $taxonomies;
	}

	/**
	 * Add product post type.
	 *
	 * @since 0.10.0
	 *
	 * @param array $post_types Translatable post types.
	 * @return array
	 */
	public function add_product_post_type( array $post_types ): array {
		if ( ! in_array( 'product', $post_types, true ) ) {
			$post_types[] = 'product';
		}
		return $post_types;
	}

	/**
	 * Add WooCommerce meta fields to whitelist.
	 *
	 * @since 0.10.0
	 *
	 * @param array $meta_keys Current whitelist.
	 * @return array
	 */
	public function add_woocommerce_meta( array $meta_keys ): array {
		$wc_meta = array(
			// Product basic data (copy as-is)
			'_product_attributes',
			'_sku',
			'_regular_price',
			'_sale_price',
			'_price',
			'_stock',
			'_stock_status',
			'_manage_stock',
			'_sold_individually',
			'_virtual',
			'_downloadable',
			'_download_limit',
			'_download_expiry',
			'_weight',
			'_length',
			'_width',
			'_height',
			'_backorders',
			'_low_stock_amount',
			
			// Product purchase note (TRANSLATABLE)
			'_purchase_note',
			
			// Product tabs (TRANSLATABLE)
			'_product_tab_title',
			'_product_tab_content',
			
			// Variation description (TRANSLATABLE)
			'_variation_description',
			
			// Product relations (will be mapped)
			'_upsell_ids',
			'_crosssell_ids',
			
			// Downloads (file names translatable)
			'_downloadable_files',
			
			// External/Affiliate products (TRANSLATABLE button text)
			'_button_text',
			'_product_url',
			
			// Tax & shipping
			'_tax_status',
			'_tax_class',
			
			// Gallery
			'_product_image_gallery',
			'_thumbnail_id',
			
			// Reviews
			'_wc_review_count',
			'_wc_average_rating',
		);

		return array_merge( $meta_keys, $wc_meta );
	}
}
















