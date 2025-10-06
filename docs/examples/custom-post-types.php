<?php
/**
 * Custom Post Types Integration Examples.
 *
 * @package FP_Multilanguage
 */

/**
 * Example 1: Register and Translate Custom Post Type
 */
function register_book_post_type() {
	register_post_type( 'book', array(
		'public' => true,
		'label'  => 'Books',
		'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
	));
}
add_action( 'init', 'register_book_post_type' );

// Enable translation
add_filter( 'fpml_translatable_post_types', function( $post_types ) {
	$post_types[] = 'book';
	return $post_types;
});

/**
 * Example 2: Translate Custom Fields (ACF)
 */
add_filter( 'fpml_meta_whitelist', function( $whitelist ) {
	// ACF fields for books
	$whitelist[] = 'author_bio';
	$whitelist[] = 'book_summary';
	$whitelist[] = 'publisher_info';
	
	// ACF repeater fields
	$whitelist[] = 'chapters'; // Repeater
	
	return $whitelist;
});

/**
 * Example 3: Custom Taxonomy Translation
 */
function register_book_genre_taxonomy() {
	register_taxonomy( 'book_genre', 'book', array(
		'public' => true,
		'label'  => 'Genres',
		'hierarchical' => true,
	));
}
add_action( 'init', 'register_book_genre_taxonomy' );

add_filter( 'fpml_translatable_taxonomies', function( $taxonomies ) {
	$taxonomies[] = 'book_genre';
	return $taxonomies;
});

/**
 * Example 4: Complex ACF Field Translation
 */
add_action( 'acf/save_post', function( $post_id ) {
	// Skip if revision or autosave
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	// Get repeater field
	$chapters = get_field( 'chapters', $post_id );
	
	if ( ! $chapters ) {
		return;
	}

	// Enqueue each chapter for translation
	foreach ( $chapters as $index => $chapter ) {
		if ( isset( $chapter['title'] ) ) {
			$hash = md5( $chapter['title'] );
			FPML_Queue::instance()->enqueue(
				'post',
				$post_id,
				"meta:chapters_{$index}_title",
				$hash
			);
		}
		
		if ( isset( $chapter['content'] ) ) {
			$hash = md5( $chapter['content'] );
			FPML_Queue::instance()->enqueue(
				'post',
				$post_id,
				"meta:chapters_{$index}_content",
				$hash
			);
		}
	}
}, 20 );

/**
 * Example 5: Custom Archive Pages
 */
add_action( 'template_redirect', function() {
	if ( ! is_post_type_archive( 'book' ) ) {
		return;
	}

	$current_lang = FPML_Language::instance()->get_current_language();
	
	if ( 'en' === $current_lang ) {
		// Filter to show only English books
		add_filter( 'pre_get_posts', function( $query ) {
			if ( ! $query->is_main_query() || ! $query->is_post_type_archive( 'book' ) ) {
				return $query;
			}

			$query->set( 'meta_query', array(
				array(
					'key'   => '_fpml_is_translation',
					'value' => '1',
				),
			));

			return $query;
		});
	}
});

/**
 * Example 6: Custom URL Structure
 */
add_filter( 'post_type_link', function( $post_link, $post ) {
	if ( 'book' !== $post->post_type ) {
		return $post_link;
	}

	// Add language prefix for English books
	if ( get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
		$post_link = str_replace( home_url( '/' ), home_url( '/en/' ), $post_link );
	}

	return $post_link;
}, 10, 2 );

/**
 * Example 7: Conditional Translation Based on Field Value
 */
add_filter( 'fpml_should_enqueue_job', function( $should, $post, $field ) {
	// Don't translate if marked as "no translation needed"
	$skip = get_post_meta( $post->ID, '_skip_translation', true );
	
	if ( $skip ) {
		return false;
	}

	// Only translate published posts
	if ( 'publish' !== $post->post_status ) {
		return false;
	}

	return $should;
}, 10, 3 );

/**
 * Example 8: Custom Translation Domain
 */
add_filter( 'fpml_translation_domain', function( $domain, $post_id, $field ) {
	$post = get_post( $post_id );
	
	if ( 'book' === $post->post_type ) {
		// Use literary domain for books
		return 'literary';
	}

	return $domain;
}, 10, 3 );

/**
 * Example 9: Post-Translation Processing
 */
add_action( 'fpml_post_translated', function( $post_id, $field, $translated_text ) {
	// If it's a book title, update SEO title
	if ( 'post_title' === $field ) {
		$post = get_post( $post_id );
		
		if ( 'book' === $post->post_type ) {
			// Update Yoast SEO title
			update_post_meta( $post_id, '_yoast_wpseo_title', $translated_text );
		}
	}
}, 10, 3 );

/**
 * Example 10: Bulk Translation Tool
 */
function fpml_bulk_translate_books() {
	$books = get_posts( array(
		'post_type' => 'book',
		'posts_per_page' => -1,
		'meta_query' => array(
			array(
				'key' => '_fpml_pair_id',
				'compare' => 'NOT EXISTS',
			),
		),
	));

	$created = 0;
	$enqueued = 0;

	foreach ( $books as $book ) {
		// Trigger save to create translation
		do_action( 'save_post', $book->ID, $book, true );
		
		// Check if translation created
		$translation_id = get_post_meta( $book->ID, '_fpml_pair_id', true );
		
		if ( $translation_id ) {
			$created++;
			$enqueued++;
		}
	}

	return array(
		'processed' => count( $books ),
		'translations_created' => $created,
		'jobs_enqueued' => $enqueued,
	);
}

// CLI command
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'fpml books translate', function() {
		$result = fpml_bulk_translate_books();
		
		WP_CLI::success( sprintf(
			'Processed %d books, created %d translations, enqueued %d jobs',
			$result['processed'],
			$result['translations_created'],
			$result['jobs_enqueued']
		));
	});
}
