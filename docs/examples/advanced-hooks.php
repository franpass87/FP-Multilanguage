<?php
/**
 * Advanced Hook Examples for FP Multilanguage.
 *
 * @package FP_Multilanguage
 */

/**
 * Example 1: Quality Score for Translations
 */
add_action( 'fpml_post_translated', function( $post_id, $field, $translated_text ) {
	$source_id = get_post_meta( $post_id, '_fpml_pair_source_id', true );
	
	if ( ! $source_id || 'post_content' !== $field ) {
		return;
	}

	$source_post = get_post( $source_id );
	$source_text = $source_post->post_content;

	// Calculate quality score
	$quality = calculate_translation_quality( $source_text, $translated_text );
	
	update_post_meta( $post_id, '_fpml_quality_score', $quality );
	
	// Flag for review if low quality
	if ( $quality < 0.7 ) {
		update_post_meta( $post_id, '_fpml_needs_review', 1 );
		
		// Send notification
		wp_mail(
			'editor@example.com',
			'Low Quality Translation',
			"Post #$post_id has quality score of $quality. Please review."
		);
	}
}, 10, 3 );

function calculate_translation_quality( $source, $translation ) {
	// Simple heuristic: length ratio
	$source_len = mb_strlen( strip_tags( $source ) );
	$trans_len = mb_strlen( strip_tags( $translation ) );
	
	if ( $source_len === 0 ) {
		return 1.0;
	}

	$ratio = $trans_len / $source_len;
	
	// Good translations are typically 80-120% of source length
	if ( $ratio >= 0.8 && $ratio <= 1.2 ) {
		return 1.0;
	} elseif ( $ratio >= 0.6 && $ratio <= 1.5 ) {
		return 0.8;
	} else {
		return 0.5;
	}
}

/**
 * Example 2: A/B Testing Translations
 */
add_filter( 'the_content', function( $content ) {
	if ( ! is_singular( 'post' ) ) {
		return $content;
	}

	global $post;
	
	// Check if translation
	if ( ! get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
		return $content;
	}

	// 50% of users see alternative translation
	$user_variant = isset( $_COOKIE['fpml_ab_variant'] ) 
		? $_COOKIE['fpml_ab_variant'] 
		: ( rand( 0, 1 ) ? 'A' : 'B' );

	if ( ! isset( $_COOKIE['fpml_ab_variant'] ) ) {
		setcookie( 'fpml_ab_variant', $user_variant, time() + WEEK_IN_SECONDS, '/' );
	}

	if ( 'B' === $user_variant ) {
		// Use alternative translation
		$alt_translation = get_post_meta( $post->ID, '_fpml_alt_translation', true );
		
		if ( $alt_translation ) {
			return $alt_translation;
		}
	}

	return $content;
});

/**
 * Example 3: Translation Workflow with Approval
 */
add_action( 'fpml_post_translated', function( $post_id, $field, $translated_text ) {
	// Set translation to draft for review
	if ( 'post_content' === $field ) {
		wp_update_post( array(
			'ID' => $post_id,
			'post_status' => 'draft',
		));
		
		// Store original auto-translation
		update_post_meta( $post_id, '_fpml_auto_translation', $translated_text );
		
		// Flag for review
		update_post_meta( $post_id, '_fpml_review_status', 'pending' );
	}
}, 10, 3 );

// Admin interface for approval
add_action( 'add_meta_boxes', function() {
	add_meta_box(
		'fpml_translation_review',
		'Translation Review',
		'fpml_render_review_metabox',
		array( 'post', 'page' ),
		'side',
		'high'
	);
});

function fpml_render_review_metabox( $post ) {
	if ( ! get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
		return;
	}

	$status = get_post_meta( $post->ID, '_fpml_review_status', true );
	
	?>
	<p>
		<strong>Review Status:</strong> 
		<?php echo $status ? ucfirst( $status ) : 'N/A'; ?>
	</p>
	
	<?php if ( 'pending' === $status ) : ?>
	<p>
		<button type="button" class="button button-primary" onclick="fpmlApproveTranslation(<?php echo $post->ID; ?>)">
			Approve Translation
		</button>
	</p>
	<?php endif; ?>
	
	<script>
	function fpmlApproveTranslation(postId) {
		if (!confirm('Approve this translation and publish?')) return;
		
		fetch('/wp-admin/admin-ajax.php', {
			method: 'POST',
			body: new URLSearchParams({
				action: 'fpml_approve_translation',
				post_id: postId,
				nonce: '<?php echo wp_create_nonce( 'fpml_approve' ); ?>'
			})
		}).then(() => {
			location.reload();
		});
	}
	</script>
	<?php
}

// AJAX handler
add_action( 'wp_ajax_fpml_approve_translation', function() {
	check_ajax_referer( 'fpml_approve', 'nonce' );
	
	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( 'Insufficient permissions' );
	}

	// Mark as approved
	update_post_meta( $post_id, '_fpml_review_status', 'approved' );
	
	// Publish
	wp_update_post( array(
		'ID' => $post_id,
		'post_status' => 'publish',
	));

	wp_send_json_success();
});

/**
 * Example 4: Event Post Type with Date Localization
 */
add_action( 'fpml_post_translated', function( $post_id, $field, $translated_text ) {
	$post = get_post( $post_id );
	
	if ( 'event' !== $post->post_type ) {
		return;
	}

	// Translate event-specific meta
	$event_date = get_post_meta( $post_id, '_event_date', true );
	
	if ( $event_date ) {
		// Format date for English locale
		$timestamp = strtotime( $event_date );
		$en_date = date_i18n( 'F j, Y', $timestamp, false ); // No timezone conversion
		
		update_post_meta( $post_id, '_event_date_formatted_en', $en_date );
	}
}, 10, 3 );

/**
 * Example 5: Portfolio Items with Image Captions
 */
add_filter( 'fpml_meta_whitelist', function( $whitelist ) {
	// Portfolio custom fields
	$whitelist[] = 'project_description';
	$whitelist[] = 'client_name';
	$whitelist[] = 'project_role';
	$whitelist[] = 'gallery_images'; // Serialized array
	
	return $whitelist;
});

// Handle gallery images separately
add_action( 'fpml_post_translated', function( $post_id, $field, $translated_text ) {
	if ( 'meta:gallery_images' !== $field ) {
		return;
	}

	// Unserialize
	$images = maybe_unserialize( $translated_text );
	
	if ( ! is_array( $images ) ) {
		return;
	}

	// Translate each image caption
	foreach ( $images as &$image ) {
		if ( isset( $image['caption'] ) && ! empty( $image['caption'] ) ) {
			$processor = FPML_Processor::instance();
			$translator = $processor->get_translator_instance();
			
			if ( ! is_wp_error( $translator ) ) {
				$translated_caption = $translator->translate( $image['caption'] );
				
				if ( ! is_wp_error( $translated_caption ) ) {
					$image['caption'] = $translated_caption;
				}
			}
		}
	}

	// Save back
	update_post_meta( $post_id, 'gallery_images', $images );
}, 10, 3 );

/**
 * Example 6: Recipe Post Type
 */
register_post_type( 'recipe', array(
	'public' => true,
	'label'  => 'Recipes',
));

add_filter( 'fpml_translatable_post_types', function( $types ) {
	$types[] = 'recipe';
	return $types;
});

// Translate ingredients (line by line)
add_action( 'save_post_recipe', function( $post_id, $post ) {
	$ingredients = get_post_meta( $post_id, '_recipe_ingredients', true );
	
	if ( empty( $ingredients ) ) {
		return;
	}

	// Split into lines
	$lines = explode( "\n", $ingredients );
	
	foreach ( $lines as $index => $line ) {
		if ( trim( $line ) === '' ) {
			continue;
		}

		$hash = md5( $line );
		FPML_Queue::instance()->enqueue(
			'post',
			$post_id,
			"meta:ingredient_line_$index",
			$hash
		);
	}
}, 20, 2 );
