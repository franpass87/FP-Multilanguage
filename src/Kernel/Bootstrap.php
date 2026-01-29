<?php
/**
 * Plugin Bootstrap.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Kernel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstrap class for initializing the plugin.
 *
 * @since 1.0.0
 */
class Bootstrap {
	/**
	 * Boot the plugin.
	 *
	 * @param string $plugin_file Main plugin file path.
	 * @return void
	 */
	public static function boot( string $plugin_file ): void {
		$kernel = new Plugin( $plugin_file );
		$kernel->registerProviders();
		$kernel->boot();
	}
}













