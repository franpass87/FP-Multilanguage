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
);

foreach ( $option_keys as $key ) {
delete_option( $key );
delete_site_option( $key );
}

global $wpdb;
$table_name = $wpdb->prefix . 'fpml_queue';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
