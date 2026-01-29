<?php
/**
 * Setup Service.
 *
 * Handles plugin setup, activation, and deactivation logic.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Core\Services;

use FP\Multilanguage\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for managing plugin setup and lifecycle.
 *
 * @since 1.0.0
 */
class SetupService {
	/**
	 * Run setup tasks if needed.
	 *
	 * @return void
	 */
	public function runIfNeeded(): void {
		// Check if setup is needed (support both option names for backward compatibility)
		$needs_setup = get_option( 'fpml_needs_setup' ) || get_option( '\FPML_needs_setup' );
		if ( ! $needs_setup ) {
			return;
		}

		// Check if already completed (support both option names)
		$completed = get_option( 'fpml_setup_completed' ) || get_option( '\FPML_setup_completed' );
		if ( $completed ) {
			delete_option( 'fpml_needs_setup' );
			delete_option( '\FPML_needs_setup' );
			return;
		}

		// Run setup
		$this->run();
	}

	/**
	 * Run setup tasks.
	 *
	 * @return void
	 */
	public function run(): void {
		try {
			// Trigger settings restoration after initialization
			do_action( 'fpml_after_initialization' );
			
			// Check assisted mode
			$assisted_mode_service = $this->getAssistedModeService();
			$reason = $assisted_mode_service ? $assisted_mode_service->detect() : '';

			// Register rewrites if not in assisted mode
			if ( ! $reason && class_exists( '\FP\Multilanguage\Frontend\Routing\Rewrites' ) ) {
				$rewrites = function_exists( 'fpml_get_rewrites' ) ? fpml_get_rewrites() : \FP\Multilanguage\Frontend\Routing\Rewrites::instance();
				if ( method_exists( $rewrites, 'register_rewrites' ) ) {
					$rewrites->register_rewrites();
				}
			} elseif ( ! $reason && class_exists( '\FPML_Rewrites' ) ) {
				$rewrites = ( function_exists( 'fpml_get_rewrites' ) ? fpml_get_rewrites() : \FPML_Rewrites::instance() );
				if ( $rewrites && method_exists( $rewrites, 'register_rewrites' ) ) {
					$rewrites->register_rewrites();
				}
			}

			// Install queue tables
			$queue = $this->getQueue();
			if ( $queue && method_exists( $queue, 'install' ) ) {
				$queue->install();
			}

			// Flush rewrite rules
			if ( function_exists( 'flush_rewrite_rules' ) ) {
				flush_rewrite_rules();
			}

			// Mark as completed (update both option names for backward compatibility)
			update_option( 'fpml_setup_completed', '1', false );
			update_option( '\FPML_setup_completed', '1', false );
			delete_option( 'fpml_needs_setup' );
			delete_option( '\FPML_needs_setup' );
		} catch ( \Exception $e ) {
			// Log error but don't break the site
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'Plugin setup error', array( 
					'message' => $e->getMessage(), 
					'trace' => $e->getTraceAsString() 
				) );
			}
		}
	}

	/**
	 * Check if setup is completed.
	 *
	 * @return bool
	 */
	public function isCompleted(): bool {
		return (bool) ( get_option( 'fpml_setup_completed' ) || get_option( '\FPML_setup_completed' ) );
	}

	/**
	 * Mark setup as needed.
	 *
	 * @return void
	 */
	public function markAsNeeded(): void {
		update_option( 'fpml_needs_setup', '1', false );
		update_option( '\FPML_needs_setup', '1', false );
	}

	/**
	 * Handle plugin activation.
	 *
	 * @return void
	 */
	public function handleActivation(): void {
		// Trigger settings backup before activation
		do_action( 'fpml_before_activation' );
		
		// SAFE ACTIVATION: Just set a flag, do nothing else
		// Actual setup will happen on first use
		$this->markAsNeeded();
	}

	/**
	 * Handle plugin deactivation.
	 *
	 * @return void
	 */
	public function handleDeactivation(): void {
		// Clear all scheduled events
		$events = array(
			'fpml_run_queue',
			'fpml_retry_failed',
			'fpml_resync_outdated',
			'fpml_cleanup_queue',
			'fpml_daily_content_scan',
			'fpml_health_check',
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
			wp_unschedule_hook( 'fpml_reindex_post_type' );
			wp_unschedule_hook( 'fpml_reindex_taxonomy' );
		}
		
		flush_rewrite_rules();
	}

	/**
	 * Get AssistedModeService instance.
	 *
	 * @return AssistedModeService|null
	 */
	protected function getAssistedModeService(): ?AssistedModeService {
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
			if ( $kernel ) {
				$container = $kernel->getContainer();
				if ( $container && $container->has( 'service.assisted_mode' ) ) {
					return $container->get( 'service.assisted_mode' );
				}
			}
		}
		
		if ( class_exists( '\FP\Multilanguage\Core\Services\AssistedModeService' ) ) {
			return new AssistedModeService();
		}
		
		return null;
	}

	/**
	 * Get Queue instance.
	 *
	 * @return object|null
	 */
	protected function getQueue() {
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
			if ( $kernel ) {
				$container = $kernel->getContainer();
				if ( $container && $container->has( 'queue' ) ) {
					return $container->get( 'queue' );
				}
			}
		}
		
		if ( class_exists( '\FP\Multilanguage\Core\Container' ) ) {
			return \FP\Multilanguage\Core\Container::get( 'queue' );
		}
		
		if ( class_exists( '\FP\Multilanguage\Queue' ) ) {
			return fpml_get_queue();
		}
		
		return null;
	}
}

