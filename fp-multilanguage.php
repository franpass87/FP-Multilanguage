<?php
/**
 * Plugin Name: FP Multilanguage
 * Plugin URI: https://francescopasseri.com
 * Description: Automates Italian-to-English copies of content, taxonomies, menus, media, and SEO data with queue-based routing and trusted translation providers.
 * Version: 0.9.2
 * Author: Francesco Passeri
 * Author URI: https://francescopasseri.com
 * Text Domain: fp-multilanguage
 * Domain Path: /languages
 * Requires PHP: 8.0
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

// Early language_attributes filter - must run before theme loads
// This ensures the lang attribute is set correctly even if Language class isn't loaded yet
add_filter( 'language_attributes', function( $output, $doctype = 'html' ) {
	if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $output;
	}
	
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	
	$locale_map = array(
		'en' => 'en-US',
		'de' => 'de-DE',
		'fr' => 'fr-FR',
		'es' => 'es-ES',
	);
	
	$detected_lang = null;
	foreach ( array_keys( $locale_map ) as $lang ) {
		if ( preg_match( '#^/' . preg_quote( $lang, '#' ) . '(/|$)#i', $request_uri ) ) {
			$detected_lang = $lang;
			break;
		}
	}
	
	if ( ! $detected_lang ) {
		return $output;
	}
	
	$bcp47_lang = $locale_map[ $detected_lang ];
	
	if ( preg_match( '/lang="[^"]*"/', $output ) ) {
		$output = preg_replace( '/lang="[^"]*"/', 'lang="' . esc_attr( $bcp47_lang ) . '"', $output );
	} else {
		$output = 'lang="' . esc_attr( $bcp47_lang ) . '" ' . $output;
	}
	
	return $output;
}, 1, 2 );

use FP\Multilanguage\Core\Container;
use FP\Multilanguage\Core\Plugin;
use FP\Multilanguage\Settings;
use FP\Multilanguage\Logger;
use FP\Multilanguage\Queue;
use FP\Multilanguage\Content\TranslationManager;
use FP\Multilanguage\Translation\JobEnqueuer;
use FP\Multilanguage\Diagnostics\Diagnostics;
use FP\Multilanguage\Diagnostics\CostEstimator;
use FP\Multilanguage\Content\ContentIndexer;
use FP\Multilanguage\Core\TranslationCache;
use FP\Multilanguage\Core\SecureSettings;
use FP\Multilanguage\Core\TranslationVersioning;
use FP\Multilanguage\PluginDetector;
use FP\Multilanguage\Core\SettingsMigration;
use FP\Multilanguage\Core\DatabaseMigration;
use FP\Multilanguage\Admin\Admin;
use FP\Multilanguage\Admin\BulkTranslator;
use FP\Multilanguage\Admin\PreviewInline;
use FP\Multilanguage\Admin\TranslationHistoryUI;
use FP\Multilanguage\Admin\AdminBarSwitcher;
use FP\Multilanguage\Admin\TranslationMetabox;
use FP\Multilanguage\Admin\PostListColumn;
use FP\Multilanguage\Rest\RestAdmin;
use FP\Multilanguage\CLI\CLI;
use FP\Multilanguage\Security\SecurityHeaders;
use FP\Multilanguage\Security\AuditLog;
use FP\Multilanguage\Analytics\Dashboard as AnalyticsDashboard;
use FP\Multilanguage\Integrations\WPBakerySupport;
use FP\Multilanguage\Integrations\ElementorSupport;
use FP\Multilanguage\Integrations\SalientThemeSupport;
use FP\Multilanguage\Integrations\FpSeoSupport;
use FP\Multilanguage\Integrations\FpReservationsSupport;
use FP\Multilanguage\Integrations\FpExperiencesSupport;
use FP\Multilanguage\Integrations\FpFormsSupport;
use FP\Multilanguage\Integrations\WooCommerceSupport;
use FP\Multilanguage\Integrations\FpPluginsSupport;
use FP\Multilanguage\Integrations\PopularPluginsSupport;
use FP\Multilanguage\TranslationMemory\MemoryStore;
use FP\Multilanguage\MultiLanguage\LanguageManager;
use FP\Multilanguage\MenuSync;
use FP\Multilanguage\AutoStringTranslator;
use FP\Multilanguage\SiteTranslations;
use FP\Multilanguage\Frontend\Content\SiteTranslations as FrontendSiteTranslations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check PHP version requirement
if ( version_compare( PHP_VERSION, '8.0.0', '<' ) ) {
	add_action( 'admin_notices', function() {
		echo '<div class="notice notice-error"><p>';
		echo '<strong>FP Multilanguage:</strong> Requires PHP 8.0 or higher. ';
		echo 'Current version: ' . esc_html( PHP_VERSION ) . '. Please upgrade PHP.';
		echo '</p></div>';
	} );
	return;
}

define( 'FPML_PLUGIN_VERSION', '0.9.2' );
define( 'FPML_PLUGIN_FILE', __FILE__ );
define( 'FPML_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FPML_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load Composer autoloader (PSR-4)
if ( file_exists( FPML_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once FPML_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	add_action( 'admin_notices', function() {
		echo '<div class="notice notice-error"><p>';
		echo '<strong>FP Multilanguage Error:</strong> Composer autoload not found. ';
		echo 'Please run <code>composer install</code> in the plugin directory.';
		echo '</p></div>';
	} );
	return;
}

// Load backward compatibility aliases
if ( file_exists( FPML_PLUGIN_DIR . 'src/compatibility.php' ) ) {
	require_once FPML_PLUGIN_DIR . 'src/compatibility.php';
}

// Register new legacy aliases
if ( class_exists( '\FP\Multilanguage\Compatibility\LegacyAliases' ) ) {
	\FP\Multilanguage\Compatibility\LegacyAliases::register();
}

// Load helper functions (must be in global namespace)
if ( file_exists( FPML_PLUGIN_DIR . 'src/helpers.php' ) ) {
	require_once FPML_PLUGIN_DIR . 'src/helpers.php';
}

/**
 * Register services in the dependency container.
 *
 * @since 0.5.0
 * @deprecated 1.0.0 Services are now registered via Service Providers in Kernel system.
 *              This function is kept for backward compatibility but does nothing.
 *              All services are registered by CoreServiceProvider and other providers.
 * @return void
 */
function fpml_register_services() {
	// Services are now registered via Service Providers in Kernel\Plugin
	// This function is kept for backward compatibility but does nothing
	// to avoid breaking any code that might call it
}

/**
 * Bootstrap the plugin.
 *
 * @since 0.5.0
 * @deprecated 1.0.0 Bootstrap is now handled by Kernel\Bootstrap
 * 
 * This function is kept for backward compatibility but most initialization
 * is now handled by Service Providers in the Kernel system.
 * 
 * @return void
 */
function fpml_bootstrap() {
	// All services are now registered via Service Providers in Kernel\Plugin
	// This function is kept for backward compatibility but does nothing
	// to avoid breaking any code that might call it
	
	// Legacy singleton initializations (will be migrated)
	// These are kept for backward compatibility during transition
	if ( ! class_exists( '\FP\Multilanguage\Kernel\Plugin' ) || ! \FP\Multilanguage\Kernel\Plugin::getInstance() ) {
		// Only initialize if Kernel is not available (fallback mode)
		SecurityHeaders::instance();
		AuditLog::instance();
		MemoryStore::instance();
		LanguageManager::instance();
		// ... other legacy initializations
	}
}

/**
 * Process deferred activation.
 *
 * @since 0.4.1
 * @deprecated 1.0.0 Activation is now handled by Kernel\Plugin directly
 * @return void
 */
function fpml_do_activation() {
	// Activation is now handled directly by Kernel\Plugin::activate()
	// This function is kept for backward compatibility but does nothing
	// The flag 'fpml_needs_activation' is no longer used
	if ( get_option( 'fpml_needs_activation' ) ) {
		delete_option( 'fpml_needs_activation' );
	}
}

/**
 * Initialize the plugin.
 *
 * @since 0.5.0
 * @return void
 */
function fpml_run_plugin() {
	if ( ! class_exists( Plugin::class ) ) {
		return;
	}

	Plugin::instance();
}

// Load files first (priority 1)
add_action( 'plugins_loaded', 'fpml_bootstrap', 1 );

// Process deferred activation (priority 5)
add_action( 'plugins_loaded', 'fpml_do_activation', 5 );

// Initialize plugin (priority 10)
// Use Kernel-based bootstrap (migrated from dual-bootstrap system)
// Old bootstrap is deprecated but kept for emergency fallback via filter
if ( ! apply_filters( 'fpml_use_old_bootstrap', false ) ) {
	// Kernel-based bootstrap (primary)
	try {
		\FP\Multilanguage\Kernel\Bootstrap::boot( __FILE__ );
	} catch ( \Exception $e ) {
		// Emergency fallback to old bootstrap only on critical error
		error_log( 'FP Multilanguage: Kernel bootstrap failed, using legacy fallback. Error: ' . $e->getMessage() );
		add_action( 'admin_notices', function() use ( $e ) {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p>';
				echo '<strong>FP Multilanguage:</strong> ';
				echo esc_html__( 'Kernel bootstrap failed, using legacy fallback. Please report this error. Error: ', 'fp-multilanguage' );
				echo esc_html( $e->getMessage() );
				echo '</p></div>';
			}
		} );
		// Only use old bootstrap as last resort
		add_action( 'plugins_loaded', 'fpml_run_plugin', 10 );
	}
} else {
	// Old bootstrap (explicitly requested via filter - deprecated)
	_doing_it_wrong( 'fpml_use_old_bootstrap', 'Old bootstrap is deprecated. Remove the filter to use the modern Kernel-based system.', '1.0.0' );
	add_action( 'plugins_loaded', 'fpml_run_plugin', 10 );
}

// Flush rewrites if needed (priority 999 - after all rewrites registered)
add_action( 'init', 'fpml_maybe_flush_rewrites', 999 );

/**
 * Ensure TranslationMetabox is initialized in admin.
 *
 * This is a fallback to guarantee the metabox is always available.
 *
 * @since 0.9.2
 * @return void
 */
function fpml_ensure_translation_metabox() {
	if ( ! is_admin() ) {
		return;
	}
	
	// Check if TranslationMetabox exists and initialize if not already done
	if ( class_exists( '\FP\Multilanguage\Admin\TranslationMetabox' ) ) {
		// This call is idempotent - singleton ensures only one instance
		\FP\Multilanguage\Admin\TranslationMetabox::instance();
	}
}
add_action( 'admin_init', 'fpml_ensure_translation_metabox', 5 );

/**
 * Activation hook.
 *
 * @since 0.4.1
 * @return void
 */
function fpml_activate() {
	// Try new kernel activation first
	$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
	if ( $kernel ) {
		$kernel->activate();
	} else {
		// Fallback to old activation
		update_option( 'fpml_needs_activation', '1', false );
	}
	
	// CRITICAL: Flush rewrites per /en/ routing
	// Deve essere fatto DOPO che le regole sono registrate
	update_option( 'fpml_flush_rewrites_needed', '1', false );
}

/**
 * Flush rewrites se necessario.
 *
 * @since 0.5.0
 * @return void
 */
function fpml_maybe_flush_rewrites() {
	// Verifica che routing_mode sia 'segment' prima di flushare
	$settings = \FPML_Settings::instance();
	$routing_mode = $settings->get( 'routing_mode', 'segment' );
	
	if ( 'segment' !== $routing_mode ) {
		// Se routing_mode non è 'segment', le rewrite rules non vengono registrate
		// Quindi non serve flushare
		return;
	}
	
	// Assicurati che Rewrites sia istanziato e le regole registrate
	if ( class_exists( '\FPML_Rewrites' ) ) {
		\FPML_Rewrites::instance()->register_rewrites();
	}
	
	if ( get_option( 'fpml_flush_rewrites_needed' ) ) {
		flush_rewrite_rules( false ); // false = non hard flush, più veloce
		delete_option( 'fpml_flush_rewrites_needed' );
	}
}

/**
 * Deactivation hook.
 *
 * @since 0.4.1
 * @return void
 */
function fpml_deactivate() {
	// Try new kernel deactivation first
	$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
	if ( $kernel ) {
		try {
			$kernel->deactivate();
		} catch ( \Exception $e ) {
			if ( function_exists( 'error_log' ) ) {
				error_log( 'FPML Deactivation Error: ' . $e->getMessage() );
			}
		}
	}
	
	// Fallback to old deactivation if needed
	if ( class_exists( Plugin::class ) && method_exists( Plugin::class, 'deactivate' ) ) {
		try {
			Plugin::deactivate();
		} catch ( \Exception $e ) {
			if ( function_exists( 'error_log' ) ) {
				error_log( 'FPML Deactivation Error: ' . $e->getMessage() );
			}
		}
	}
}

register_activation_hook( __FILE__, 'fpml_activate' );
register_deactivation_hook( __FILE__, 'fpml_deactivate' );
