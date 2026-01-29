<?php
/**
 * Uninstall handler.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Cleanup function for a single site.
 *
 * @param int $blog_id Optional blog ID for multisite.
 */
function fpml_uninstall_site( $blog_id = 0 ) {
	global $wpdb;
	
	// Switch to blog if multisite
	if ( $blog_id > 0 && function_exists( 'switch_to_blog' ) ) {
		switch_to_blog( $blog_id );
	}
	
	$remove_data = get_option( 'fpml_remove_data', false );

	if ( ! $remove_data ) {
		if ( $blog_id > 0 && function_exists( 'restore_current_blog' ) ) {
			restore_current_blog();
		}
		return;
	}

	$option_keys = array(
		'fpml_settings',
		'fpml_logs',
		'fpml_queue_lock',
		'fpml_slug_redirects',
		'fpml_glossary',
		'fpml_strings_overrides',
		'fpml_strings_scanner',
		'fpml_options_autoload_migrated',
		'fpml_versioning_table_version',
		'fpml_remove_data',
	);

	foreach ( $option_keys as $key ) {
		delete_option( $key );
		delete_site_option( $key );
	}

	// Get correct prefix for this site
	$prefix = $blog_id > 0 ? $wpdb->get_blog_prefix( $blog_id ) : $wpdb->prefix;

	// Drop queue table
	$table_name = $prefix . 'fpml_queue';
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

	// Drop versioning table
	$versions_table = $prefix . 'fpml_translation_versions';
	$wpdb->query( "DROP TABLE IF EXISTS {$versions_table}" );

	// Drop logger table
	$logger_table = $prefix . 'fpml_logs';
	$wpdb->query( "DROP TABLE IF EXISTS {$logger_table}" );

	// Clean up all post meta
	$postmeta_table = $prefix . 'postmeta';
	$wpdb->query( "DELETE FROM {$postmeta_table} WHERE meta_key LIKE '_fpml_%'" );

	// Clean up all term meta
	$termmeta_table = $prefix . 'termmeta';
	$wpdb->query( "DELETE FROM {$termmeta_table} WHERE meta_key LIKE '_fpml_%'" );

	// Clean up all transients (cache)
	$options_table = $prefix . 'options';
	$wpdb->query(
		"DELETE FROM {$options_table} 
		 WHERE option_name LIKE '_transient_fpml_%' 
		 OR option_name LIKE '_transient_timeout_fpml_%'"
	);

	// Clean up any remaining fpml options
	$wpdb->query(
		"DELETE FROM {$options_table} WHERE option_name LIKE 'fpml_%'"
	);
	
	// Restore blog if multisite
	if ( $blog_id > 0 && function_exists( 'restore_current_blog' ) ) {
		restore_current_blog();
	}
}

// Check if multisite
if ( is_multisite() ) {
	global $wpdb;
	
	// Get all sites
	$sites = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
	
	foreach ( $sites as $blog_id ) {
		fpml_uninstall_site( (int) $blog_id );
	}
	
	// Clean network-wide options
	delete_site_option( 'fpml_settings' );
	delete_site_option( 'fpml_remove_data' );
} else {
	// Single site installation
	fpml_uninstall_site();
}
