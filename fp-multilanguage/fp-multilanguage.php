<?php
/**
 * Plugin Name: FP Multilanguage
 * Plugin URI: https://francescopasseri.com
 * Description: Automates Italian-to-English copies of content, taxonomies, menus, media, and SEO data with queue-based routing and trusted translation providers.
 * Version: 0.4.1
 * Author: Francesco Passeri
 * Author URI: https://francescopasseri.com
 * Text Domain: fp-multilanguage
 * Domain Path: /languages
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

define( 'FPML_PLUGIN_VERSION', '0.4.1' );
define( 'FPML_PLUGIN_FILE', __FILE__ );
define( 'FPML_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FPML_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

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

	// Load files (skip core files as they're already loaded)
	foreach ( $files as $path ) {
		// Skip core directory files (already loaded explicitly)
		$normalized = str_replace( array( '\\', '/' ), DIRECTORY_SEPARATOR, $path );
		if ( strpos( $normalized, DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR ) !== false ) {
			continue;
		}
		
		if ( file_exists( $path ) && is_readable( $path ) ) {
			require_once $path;
		}
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
 * Bootstrap the plugin - load all files.
 *
 * @since 0.4.1
 * @return void
 */
function fpml_load_files() {
	// Load core classes FIRST
	$core_classes = array(
		'includes/core/class-container.php',
		'includes/core/class-plugin.php',
		'includes/core/class-simple-settings.php', // NUOVO SISTEMA SEMPLICE
		'includes/core/class-secure-settings.php',
		'includes/core/class-settings-migration.php',
		'includes/core/class-translation-cache.php',
		'includes/core/class-translation-versioning.php',
	);

	foreach ( $core_classes as $core_class ) {
		$file = FPML_PLUGIN_DIR . $core_class;
		if ( file_exists( $file ) && is_readable( $file ) ) {
			require_once $file;
		}
	}

	// Load all other includes
	autoload_fpml_files();

	// Register services
	fpml_register_services();

	// Load admin, REST, CLI
	if ( file_exists( FPML_PLUGIN_DIR . 'admin/class-admin.php' ) ) {
		require_once FPML_PLUGIN_DIR . 'admin/class-admin.php';
	}

	if ( file_exists( FPML_PLUGIN_DIR . 'rest/class-rest-admin.php' ) ) {
		require_once FPML_PLUGIN_DIR . 'rest/class-rest-admin.php';
	}

	if ( defined( 'WP_CLI' ) && WP_CLI && file_exists( FPML_PLUGIN_DIR . 'cli/class-cli.php' ) ) {
		require_once FPML_PLUGIN_DIR . 'cli/class-cli.php';
	}
}

/**
 * Bootstrap - Load all files when WordPress is ready.
 *
 * @since 0.4.1
 * @return void
 */
function fpml_bootstrap() {
	fpml_load_files();
}

/**
 * Process deferred activation.
 *
 * @since 0.4.1
 * @return void
 */
function fpml_do_activation() {
	if ( get_option( 'fpml_needs_activation' ) ) {
		delete_option( 'fpml_needs_activation' );
		
		if ( class_exists( 'FPML_Plugin' ) && method_exists( 'FPML_Plugin', 'activate' ) ) {
			try {
				FPML_Plugin::activate();
			} catch ( Exception $e ) {
				// Log error but don't crash
				if ( function_exists( 'error_log' ) ) {
					error_log( 'FPML Activation Error: ' . $e->getMessage() );
				}
			}
		}
	}
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

// Load files first (priority 1)
add_action( 'plugins_loaded', 'fpml_bootstrap', 1 );

// Process deferred activation (priority 5)
add_action( 'plugins_loaded', 'fpml_do_activation', 5 );

// Initialize plugin (priority 10)
add_action( 'plugins_loaded', 'fpml_run_plugin', 10 );

/**
 * Activation hook - only sets a flag, no complex code.
 *
 * @since 0.4.1
 * @return void
 */
function fpml_activate() {
	// Only set a flag - safe operation that never fails
	update_option( 'fpml_needs_activation', '1', false );
}

/**
 * Deactivation hook.
 *
 * @since 0.4.1
 * @return void
 */
function fpml_deactivate() {
	if ( class_exists( 'FPML_Plugin' ) && method_exists( 'FPML_Plugin', 'deactivate' ) ) {
		try {
			FPML_Plugin::deactivate();
		} catch ( Exception $e ) {
			// Log error but don't crash
			if ( function_exists( 'error_log' ) ) {
				error_log( 'FPML Deactivation Error: ' . $e->getMessage() );
			}
		}
	}
}

register_activation_hook( __FILE__, 'fpml_activate' );
register_deactivation_hook( __FILE__, 'fpml_deactivate' );

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
		return class_exists( 'FPML_Translation_Manager' ) ? FPML_Translation_Manager::instance() : null;
	} );

	FPML_Container::register( 'job_enqueuer', function() {
		return class_exists( 'FPML_Job_Enqueuer' ) ? FPML_Job_Enqueuer::instance() : null;
	} );

	// Diagnostic services.
	FPML_Container::register( 'diagnostics', function() {
		return class_exists( 'FPML_Diagnostics' ) ? FPML_Diagnostics::instance() : null;
	} );

	FPML_Container::register( 'cost_estimator', function() {
		return class_exists( 'FPML_Cost_Estimator' ) ? FPML_Cost_Estimator::instance() : null;
	} );

	// Content indexing.
	FPML_Container::register( 'content_indexer', function() {
		return class_exists( 'FPML_Content_Indexer' ) ? FPML_Content_Indexer::instance() : null;
	} );

	// Translation cache.
	FPML_Container::register( 'translation_cache', function() {
		return class_exists( 'FPML_Translation_Cache' ) ? FPML_Translation_Cache::instance() : null;
	} );

	// Secure settings.
	FPML_Container::register( 'secure_settings', function() {
		return class_exists( 'FPML_Secure_Settings' ) ? FPML_Secure_Settings::instance() : null;
	} );

	// Translation versioning.
	FPML_Container::register( 'translation_versioning', function() {
		return class_exists( 'FPML_Translation_Versioning' ) ? FPML_Translation_Versioning::instance() : null;
	} );

	// Plugin detector.
	FPML_Container::register( 'plugin_detector', function() {
		return class_exists( 'FPML_Plugin_Detector' ) ? FPML_Plugin_Detector::instance() : null;
	} );

	// Settings migration.
	FPML_Container::register( 'settings_migration', function() {
		return class_exists( 'FPML_Settings_Migration' ) ? FPML_Settings_Migration::instance() : null;
	} );
}
