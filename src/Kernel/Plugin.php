<?php
/**
 * Plugin Kernel - Main orchestrator.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Kernel;

use FP\Multilanguage\Foundation\Environment\CompatibilityChecker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin kernel that orchestrates service providers.
 *
 * @since 1.0.0
 */
class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Plugin file path.
	 *
	 * @var string
	 */
	protected $plugin_file;

	/**
	 * Service container.
	 *
	 * @var Container
	 */
	protected $container;

	/**
	 * Registered service providers.
	 *
	 * @var array<ServiceProvider>
	 */
	protected $providers = array();

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Plugin file path.
	 */
	public function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;
		$this->container = new Container();

		// Register container as a service
		$this->container->singleton( 'container', $this->container );
		$this->container->singleton( 'kernel', $this );

		// Store instance
		self::$instance = $this;
	}

	/**
	 * Get singleton instance.
	 *
	 * @return self|null
	 */
	public static function getInstance(): ?self {
		return self::$instance;
	}

	/**
	 * Register all service providers.
	 *
	 * @return void
	 */
	public function registerProviders(): void {
		// Check compatibility first
		$compatibility = new CompatibilityChecker();
		if ( ! $compatibility->isCompatible() ) {
			$this->showCompatibilityError( $compatibility->getIssues() );
			return;
		}

		// Get list of providers to register
		$providers = $this->getProviders();

		// Register each provider
		foreach ( $providers as $provider_class ) {
			if ( ! class_exists( $provider_class ) ) {
				continue;
			}

			$provider = new $provider_class();
			if ( $provider instanceof ServiceProvider ) {
				$provider->register( $this->container );
				$this->providers[] = $provider;
			}
		}
	}

	/**
	 * Boot all registered providers.
	 *
	 * @return void
	 */
	public function boot(): void {
		foreach ( $this->providers as $provider ) {
			$provider->boot( $this->container );
		}
		
		// Trigger initialization hooks (new and legacy for backward compatibility)
		do_action( 'fpml_after_initialization' );
		do_action( '\FPML_after_initialization' );
	}

	/**
	 * Get list of service provider classes.
	 *
	 * @return array<string> Provider class names.
	 */
	protected function getProviders(): array {
		// Providers will be registered in order
		// Foundation first, then core, then feature providers
		$providers = array(
			\FP\Multilanguage\Providers\FoundationServiceProvider::class,
			\FP\Multilanguage\Providers\SecurityServiceProvider::class,
			\FP\Multilanguage\Providers\LanguageServiceProvider::class,
			\FP\Multilanguage\Providers\CoreServiceProvider::class,
			\FP\Multilanguage\Providers\PluginServiceProvider::class,
			\FP\Multilanguage\Providers\AdminServiceProvider::class,
			\FP\Multilanguage\Providers\RESTServiceProvider::class,
			\FP\Multilanguage\Providers\FrontendServiceProvider::class,
			\FP\Multilanguage\Providers\CLIServiceProvider::class,
			\FP\Multilanguage\Providers\IntegrationServiceProvider::class,
		);

		return apply_filters( 'fpml_service_providers', $providers );
	}

	/**
	 * Show compatibility error notice.
	 *
	 * @param array $issues Compatibility issues.
	 * @return void
	 */
	protected function showCompatibilityError( array $issues ): void {
		add_action( 'admin_notices', function() use ( $issues ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			echo '<div class="notice notice-error"><p>';
			echo '<strong>FP Multilanguage:</strong> ';
			echo esc_html( implode( ' ', $issues ) );
			echo '</p></div>';
		} );
	}

	/**
	 * Get the container instance.
	 *
	 * @return Container
	 */
	public function getContainer(): Container {
		return $this->container;
	}

	/**
	 * Handle plugin activation.
	 *
	 * @return void
	 */
	public function activate(): void {
		// Trigger settings backup before activation
		do_action( '\FPML_before_activation' );
		
		// SAFE ACTIVATION: Just set a flag, do nothing else
		// Actual setup will happen on first use via PluginServiceProvider
		update_option( '\FPML_needs_setup', '1', false );
		
		// CRITICAL: Flush rewrites per /en/ routing
		// Deve essere fatto DOPO che le regole sono registrate
		update_option( 'fpml_flush_rewrites_needed', '1', false );
		
		// Trigger activation hook for backward compatibility
		do_action( 'fpml_activate' );
	}

	/**
	 * Handle plugin deactivation.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		// Clear all scheduled events
		$events = array(
			'\FPML_run_queue',
			'\FPML_retry_failed',
			'\FPML_resync_outdated',
			'\FPML_cleanup_queue',
			'\FPML_daily_content_scan',
			'\FPML_health_check',
		);
		
		foreach ( $events as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			while ( false !== $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
				$timestamp = wp_next_scheduled( $hook );
			}
		}
		
		// Clear single events with args (WordPress 5.1+)
		if ( function_exists( 'wp_unschedule_hook' ) ) {
			wp_unschedule_hook( '\FPML_reindex_post_type' );
			wp_unschedule_hook( '\FPML_reindex_taxonomy' );
		}
		
		flush_rewrite_rules();
		
		// Trigger deactivation hook for backward compatibility
		do_action( 'fpml_deactivate' );
	}
}





