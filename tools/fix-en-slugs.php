<?php
/**
 * Fix English slugs with -en suffix
 * 
 * This script finds all English pages with -en suffix and re-queues
 * them for slug translation.
 * 
 * Usage: wp eval-file tools/fix-en-slugs.php
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'This script must be run via WP-CLI' );
}

WP_CLI::line( 'Starting slug fix for English pages...' );

global $wpdb;

// Find all translated posts with -en slug
$query = "
	SELECT p.ID, p.post_name, p.post_title
	FROM {$wpdb->posts} p
	INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
	WHERE pm.meta_key = '_fpml_is_translation'
	AND pm.meta_value = '1'
	AND p.post_name LIKE '%-en'
	AND p.post_status IN ('publish', 'draft', 'pending')
	AND p.post_type NOT IN ('revision', 'attachment', 'nav_menu_item')
";

$posts = $wpdb->get_results( $query );

if ( empty( $posts ) ) {
	WP_CLI::success( 'No posts found with -en suffix.' );
	return;
}

WP_CLI::line( sprintf( 'Found %d posts with -en suffix.', count( $posts ) ) );
WP_CLI::confirm( 'Do you want to re-queue slug translation for these posts?' );

$queue = FPML_Queue::instance();
$settings = FPML_Settings::instance();

// Check if slug translation is enabled
if ( ! $settings || ! $settings->get( 'translate_slugs', false ) ) {
	WP_CLI::warning( 'Slug translation is disabled in settings. Enabling it now...' );
	
	$current_settings = get_option( FPML_Settings::OPTION_KEY, array() );
	$current_settings['translate_slugs'] = true;
	update_option( FPML_Settings::OPTION_KEY, $current_settings );
	
	WP_CLI::success( 'Slug translation enabled.' );
}

$updated = 0;
$failed = 0;

foreach ( $posts as $post ) {
	// Get source post
	$source_id = (int) get_post_meta( $post->ID, '_fpml_pair_source_id', true );
	
	if ( ! $source_id ) {
		WP_CLI::warning( sprintf( 'Post #%d has no source post. Skipping.', $post->ID ) );
		$failed++;
		continue;
	}
	
	$source_post = get_post( $source_id );
	
	if ( ! $source_post ) {
		WP_CLI::warning( sprintf( 'Source post #%d not found for #%d. Skipping.', $source_id, $post->ID ) );
		$failed++;
		continue;
	}
	
	// Get source slug
	$source_slug = $source_post->post_name ? $source_post->post_name : sanitize_title( $source_post->post_title );
	
	if ( '' === $source_slug ) {
		WP_CLI::warning( sprintf( 'Cannot determine source slug for post #%d. Skipping.', $post->ID ) );
		$failed++;
		continue;
	}
	
	// Enqueue slug translation job
	$hash = md5( $source_slug );
	$queue->enqueue( 'post', $source_id, 'slug', $hash );
	
	// Update status
	update_post_meta( $post->ID, '_fpml_status_slug', 'needs_update' );
	
	WP_CLI::line( sprintf( 
		'✓ Post #%d "%s" (%s) - queued for slug translation', 
		$post->ID, 
		$post->post_title,
		$post->post_name
	) );
	
	$updated++;
}

WP_CLI::line( '' );
WP_CLI::success( sprintf( 
	'Done! %d posts queued for slug translation, %d failed.', 
	$updated, 
	$failed 
) );
WP_CLI::line( 'Run the queue processor to translate the slugs:' );
WP_CLI::line( '  wp fpml queue run' );
