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

$remove_data = get_option( 'fpml_remove_data', false );

if ( ! $remove_data ) {
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

global $wpdb;

// Drop queue table
$table_name = $wpdb->prefix . 'fpml_queue';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

// Drop versioning table
$versions_table = $wpdb->prefix . 'fpml_translation_versions';
$wpdb->query( "DROP TABLE IF EXISTS {$versions_table}" );

// Drop logger table
$logger_table = $wpdb->prefix . 'fpml_logs';
$wpdb->query( "DROP TABLE IF EXISTS {$logger_table}" );

// Clean up all post meta
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_fpml_%'" );

// Clean up all term meta
$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE '_fpml_%'" );

// Clean up all transients (cache)
$wpdb->query(
	"DELETE FROM {$wpdb->options} 
	 WHERE option_name LIKE '_transient_fpml_%' 
	 OR option_name LIKE '_transient_timeout_fpml_%'"
);

// Clean up any remaining fpml options
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'fpml_%'"
);
