<?php
/**
 * Plugin Name: FP Multilanguage
 * Plugin URI: https://francescopasseri.com/progetti/fp-multilanguage
 * Description: Gestione avanzata delle traduzioni multilingua per contenuti, stringhe dinamiche e SEO in WordPress.
 * Version: 1.1.0
 * Author: Francesco Passeri
 * Author URI: https://francescopasseri.com
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fp-multilanguage
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'FP_MULTILANGUAGE_FILE' ) ) {
	define( 'FP_MULTILANGUAGE_FILE', __FILE__ );
}

if ( ! defined( 'FP_MULTILANGUAGE_PATH' ) ) {
	define( 'FP_MULTILANGUAGE_PATH', plugin_dir_path( FP_MULTILANGUAGE_FILE ) );
}

if ( ! defined( 'FP_MULTILANGUAGE_URL' ) ) {
	define( 'FP_MULTILANGUAGE_URL', plugin_dir_url( FP_MULTILANGUAGE_FILE ) );
}

if ( ! defined( 'FP_MULTILANGUAGE_VERSION' ) ) {
        define( 'FP_MULTILANGUAGE_VERSION', '1.1.0' );
}

$autoload_paths = array(
	FP_MULTILANGUAGE_PATH . '../vendor/autoload.php',
	FP_MULTILANGUAGE_PATH . 'vendor/autoload.php',
);

$is_autoloaded = false;

foreach ( $autoload_paths as $autoload_path ) {
	if ( file_exists( $autoload_path ) ) {
			require_once $autoload_path;
			$is_autoloaded = true;
			break;
	}
}

if ( ! $is_autoloaded ) {
		$fallback_autoload = FP_MULTILANGUAGE_PATH . 'includes/autoload.php';

	if ( file_exists( $fallback_autoload ) ) {
			require_once $fallback_autoload;
	}
}

use FPMultilanguage\Plugin;

if ( ! function_exists( 'fp_multilanguage' ) ) {
	function fp_multilanguage(): Plugin {
		return Plugin::instance();
	}
}

register_activation_hook( FP_MULTILANGUAGE_FILE, array( Plugin::class, 'activate' ) );
register_deactivation_hook( FP_MULTILANGUAGE_FILE, array( Plugin::class, 'deactivate' ) );
register_uninstall_hook( FP_MULTILANGUAGE_FILE, array( Plugin::class, 'uninstall' ) );

fp_multilanguage()->init();
