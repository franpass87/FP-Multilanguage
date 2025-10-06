<?php
/**
 * WooCommerce Integration Examples for FP Multilanguage.
 *
 * @package FP_Multilanguage
 */

/**
 * Example 1: Translate WooCommerce Product Attributes
 */
add_filter( 'fpml_meta_whitelist', function( $whitelist ) {
	// Add WooCommerce product meta fields
	$wc_fields = array(
		'_product_attributes',
		'_purchase_note',
		'_wc_review_count',
		'_downloadable_files',
	);

	return array_merge( $whitelist, $wc_fields );
});

/**
 * Example 2: Sync Product Categories
 */
add_filter( 'fpml_translatable_taxonomies', function( $taxonomies ) {
	$taxonomies[] = 'product_cat';
	$taxonomies[] = 'product_tag';
	return $taxonomies;
});

/**
 * Example 3: Translate Product Short Description
 */
add_action( 'woocommerce_update_product', function( $product_id ) {
	$product = wc_get_product( $product_id );
	
	if ( ! $product ) {
		return;
	}

	// Get short description
	$short_desc = $product->get_short_description();
	
	if ( empty( $short_desc ) ) {
		return;
	}

	// Enqueue translation
	$queue = FPML_Queue::instance();
	$hash = md5( $short_desc );
	$queue->enqueue( 'post', $product_id, 'meta:_product_short_description', $hash );
});

/**
 * Example 4: Translate Product Variations
 */
add_action( 'woocommerce_save_product_variation', function( $variation_id ) {
	$variation = wc_get_product( $variation_id );
	
	if ( ! $variation ) {
		return;
	}

	$parent_id = $variation->get_parent_id();
	
	// Get English parent
	$en_parent_id = get_post_meta( $parent_id, '_fpml_pair_id', true );
	
	if ( ! $en_parent_id ) {
		return;
	}

	// Create English variation
	$en_variation_id = wp_insert_post( array(
		'post_title'  => $variation->get_name(),
		'post_parent' => $en_parent_id,
		'post_type'   => 'product_variation',
		'post_status' => 'publish',
	));

	// Link variations
	update_post_meta( $variation_id, '_fpml_pair_id', $en_variation_id );
	update_post_meta( $en_variation_id, '_fpml_pair_source_id', $variation_id );
	update_post_meta( $en_variation_id, '_fpml_is_translation', 1 );
});

/**
 * Example 5: Different Prices for EN Market
 */
add_filter( 'woocommerce_product_get_price', function( $price, $product ) {
	// Check if viewing English version
	if ( FPML_Language::instance()->get_current_language() !== 'en' ) {
		return $price;
	}

	// Check if this is a translation
	if ( ! get_post_meta( $product->get_id(), '_fpml_is_translation', true ) ) {
		return $price;
	}

	// Apply currency conversion or markup
	$conversion_rate = 1.1; // 10% markup for international
	
	return $price * $conversion_rate;
}, 10, 2 );

/**
 * Example 6: Translate Product Reviews
 */
add_action( 'comment_post', function( $comment_id, $approved ) {
	if ( 1 !== (int) $approved ) {
		return;
	}

	$comment = get_comment( $comment_id );
	
	if ( ! $comment || 'product' !== get_post_type( $comment->comment_post_ID ) ) {
		return;
	}

	// Get English product
	$en_product_id = get_post_meta( $comment->comment_post_ID, '_fpml_pair_id', true );
	
	if ( ! $en_product_id ) {
		return;
	}

	// Translate and create English review
	$processor = FPML_Processor::instance();
	$translator = $processor->get_translator_instance();
	
	if ( is_wp_error( $translator ) ) {
		return;
	}

	$translated_content = $translator->translate( $comment->comment_content );
	
	if ( is_wp_error( $translated_content ) ) {
		return;
	}

	// Create English review
	wp_insert_comment( array(
		'comment_post_ID'      => $en_product_id,
		'comment_author'       => $comment->comment_author,
		'comment_author_email' => $comment->comment_author_email,
		'comment_content'      => $translated_content,
		'comment_approved'     => 1,
		'comment_meta'         => array(
			'_fpml_is_translation' => 1,
			'_fpml_source_comment_id' => $comment_id,
		),
	));
}, 10, 2 );

/**
 * Example 7: Stock Sync Between Translations
 */
add_action( 'woocommerce_product_set_stock', function( $product ) {
	$product_id = $product->get_id();
	$stock = $product->get_stock_quantity();

	// Sync to English version
	$en_product_id = get_post_meta( $product_id, '_fpml_pair_id', true );
	
	if ( $en_product_id ) {
		$en_product = wc_get_product( $en_product_id );
		
		if ( $en_product ) {
			$en_product->set_stock_quantity( $stock );
			$en_product->save();
		}
	}
});
