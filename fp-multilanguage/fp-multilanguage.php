<?php
/**
 * Plugin Name: FP Multilanguage
 * Plugin URI: https://francescopasseri.com
 * Description: Automates Italian-to-English copies of content, taxonomies, menus, media, and SEO data with queue-based routing and trusted translation providers.
 * Version: 0.3.1
 * Author: Francesco Passeri
 * Author URI: https://francescopasseri.com
 * Text Domain: fp-multilanguage
 * Domain Path: /languages
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

define( 'FPML_PLUGIN_VERSION', '0.3.1' );
define( 'FPML_PLUGIN_FILE', __FILE__ );
define( 'FPML_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FPML_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

$autoload = __DIR__ . '/vendor/autoload.php';

if ( is_readable( $autoload ) ) {
        require $autoload;
}

autoload_fpml_files();
fpml_register_services();

require_once FPML_PLUGIN_DIR . 'admin/class-admin.php';

if ( file_exists( FPML_PLUGIN_DIR . 'rest/class-rest-admin.php' ) ) {
        require_once FPML_PLUGIN_DIR . 'rest/class-rest-admin.php';
}

if ( defined( 'WP_CLI' ) && WP_CLI && file_exists( FPML_PLUGIN_DIR . 'cli/class-cli.php' ) ) {
        require_once FPML_PLUGIN_DIR . 'cli/class-cli.php';
}

/**
 * Require plugin includes.
 *
 * @since 0.2.0
 * @return void
 */
function autoload_fpml_files() {
        $includes_dir = FPML_PLUGIN_DIR . 'includes/';

        if ( ! is_dir( $includes_dir ) ) {
                return;
        }

        $files = array();

        if ( class_exists( 'RecursiveIteratorIterator' ) && class_exists( 'RecursiveDirectoryIterator' ) ) {
                $flags = 0;

                if ( class_exists( 'FilesystemIterator' ) && defined( 'FilesystemIterator::SKIP_DOTS' ) ) {
                        $flags = FilesystemIterator::SKIP_DOTS;
                }

                try {
                        $iterator = new RecursiveIteratorIterator(
                                new RecursiveDirectoryIterator( $includes_dir, $flags ),
                                RecursiveIteratorIterator::SELF_FIRST
                        );

                        foreach ( $iterator as $file ) {
                                if ( ! is_object( $file ) || ! method_exists( $file, 'getExtension' ) ) {
                                        continue;
                                }

                                if ( 'php' === strtolower( $file->getExtension() ) && method_exists( $file, 'getPathname' ) ) {
                                        $files[] = $file->getPathname();
                                }
                        }
                } catch ( Exception $exception ) { // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.exception_instance
                        $files = fpml_scan_php_files( $includes_dir );
                }
        } else {
                $files = fpml_scan_php_files( $includes_dir );
        }

        if ( ! empty( $files ) ) {
                $files = array_unique( $files );
                sort( $files );
        }

        foreach ( $files as $path ) {
                require_once $path;
        }
}

/**
 * Recursively scan a directory for PHP files.
 *
 * @since 0.3.1
 *
 * @param string $directory Base directory.
 *
 * @return array
 */
function fpml_scan_php_files( $directory ) {
        $php_files = array();

        $items = scandir( $directory );

        if ( false === $items ) {
                return $php_files;
        }

        foreach ( $items as $item ) {
                if ( '.' === $item || '..' === $item ) {
                        continue;
                }

                $path = rtrim( $directory, '/\\' ) . '/' . $item;

                if ( is_dir( $path ) ) {
                        $php_files = array_merge( $php_files, fpml_scan_php_files( $path ) );
                        continue;
                }

                if ( 'php' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
                        $php_files[] = $path;
                }
        }

        return $php_files;
}

/**
 * Initialize the plugin.
 *
 * @since 0.2.0
 * @return void
 */
function fpml_run_plugin() {
if ( ! class_exists( 'FPML_Plugin' ) ) {
return;
}

FPML_Plugin::instance();
}

add_action( 'plugins_loaded', 'fpml_run_plugin' );

register_activation_hook( __FILE__, array( 'FPML_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'FPML_Plugin', 'deactivate' ) );

/**
 * Register services in the dependency container.
 *
 * @since 0.4.0
 * @return void
 */
function fpml_register_services() {
	if ( ! class_exists( 'FPML_Container' ) ) {
		return;
	}

	// Core services.
	FPML_Container::register( 'settings', function() {
		return FPML_Settings::instance();
	} );

	FPML_Container::register( 'logger', function() {
		return FPML_Logger::instance();
	} );

	FPML_Container::register( 'queue', function() {
		return FPML_Queue::instance();
	} );

	// Translation services.
	FPML_Container::register( 'translation_manager', function() {
		return FPML_Translation_Manager::instance();
	} );

	FPML_Container::register( 'job_enqueuer', function() {
		return FPML_Job_Enqueuer::instance();
	} );

	// Diagnostic services.
	FPML_Container::register( 'diagnostics', function() {
		return FPML_Diagnostics::instance();
	} );

	FPML_Container::register( 'cost_estimator', function() {
		return FPML_Cost_Estimator::instance();
	} );

	// Content indexing.
	FPML_Container::register( 'content_indexer', function() {
		return FPML_Content_Indexer::instance();
	} );
}
