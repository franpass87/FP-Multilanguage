<?php
/**
 * Plugin Service Provider.
 *
 * Registers plugin core services and handles setup/activation logic.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Providers;

use FP\Multilanguage\Kernel\Container;
use FP\Multilanguage\Kernel\ServiceProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin service provider.
 *
 * Handles plugin setup, assisted mode detection, and core plugin services.
 *
 * @since 1.0.0
 */
class PluginServiceProvider implements ServiceProvider {
	/**
	 * Register services with the container.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Register plugin core service (for backward compatibility)
		$container->bind( 'plugin.core', function( Container $c ) {
			// Return a lightweight adapter that delegates to Kernel
			return new class {
				/**
				 * Check if assisted mode is active.
				 *
				 * @return bool
				 */
				public function is_assisted_mode(): bool {
					$reason = self::detect_external_multilingual();
					return ! empty( $reason );
				}

				/**
				 * Get assisted mode reason.
				 *
				 * @return string
				 */
				public function get_assisted_reason(): string {
					return self::detect_external_multilingual();
				}

				/**
				 * Get assisted mode label.
				 *
				 * @return string
				 */
				public function get_assisted_reason_label(): string {
					$reason = self::detect_external_multilingual();
					switch ( $reason ) {
						case 'wpml':
							return 'WPML';
						case 'polylang':
							return 'Polylang';
						default:
							return '';
					}
				}

				/**
				 * Detect external multilingual plugins.
				 *
				 * @return string
				 */
				protected static function detect_external_multilingual(): string {
					if ( defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'icl_object_id' ) ) {
						return 'wpml';
					}

					if ( defined( 'POLYLANG_VERSION' ) || function_exists( 'pll_current_language' ) ) {
						return 'polylang';
					}

					return '';
				}
			};
		}, true );

		// Register assisted mode detector
		$container->bind( 'plugin.assisted_mode', function( Container $c ) {
			$reason = self::detect_external_multilingual();
			return ! empty( $reason );
		}, false );

		// Register assisted mode reason
		$container->bind( 'plugin.assisted_reason', function( Container $c ) {
			return self::detect_external_multilingual();
		}, false );

		// Aliases for backward compatibility with helper functions
		$container->alias( 'plugin', 'plugin.core' );
	}

	/**
	 * Boot services after all providers have registered.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Run setup if needed
		$this->maybeRunSetup( $container );

		// Disable autoloaded options if needed
		$this->maybeDisableAutoloadedOptions();

		// Load textdomain
		add_action( 'init', array( $this, 'loadTextdomain' ), 1 );
	}

	/**
	 * Run plugin setup if needed.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	protected function maybeRunSetup( Container $container ): void {
		// Check if setup is already completed
		if ( get_option( '\FPML_setup_completed' ) ) {
			delete_option( '\FPML_needs_setup' );
			return;
		}

		// Check if setup is needed
		if ( ! get_option( '\FPML_needs_setup' ) ) {
			return;
		}

		// Run setup tasks
		try {
			// Trigger settings restoration after initialization
			do_action( '\FPML_after_initialization' );

			$assisted_mode = $container->has( 'plugin.assisted_mode' ) ? $container->get( 'plugin.assisted_mode' ) : false;

			// Register rewrites if not in assisted mode
			if ( ! $assisted_mode && class_exists( '\FPML_Rewrites' ) ) {
				$rewrites = ( function_exists( 'fpml_get_rewrites' ) ? fpml_get_rewrites() : \FPML_Rewrites::instance() );
				if ( $rewrites && method_exists( $rewrites, 'register_rewrites' ) ) {
					$rewrites->register_rewrites();
				}
			}

			// Install queue tables
			$queue = $container->has( 'queue' ) ? $container->get( 'queue' ) : null;
			if ( $queue && method_exists( $queue, 'install' ) ) {
				$queue->install();
			}

			// Flush rewrite rules
			if ( function_exists( 'flush_rewrite_rules' ) ) {
				flush_rewrite_rules();
			}

			// Mark as completed
			update_option( '\FPML_setup_completed', '1', false );
			delete_option( '\FPML_needs_setup' );
		} catch ( \Exception $e ) {
			// Log error but don't break the site
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$logger = $container->has( 'logger' ) ? $container->get( 'logger' ) : null;
				if ( $logger && method_exists( $logger, 'error' ) ) {
					$logger->error( 'Plugin setup error', array( 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString() ) );
				} else {
					error_log( 'FPML Setup Error: ' . $e->getMessage() );
				}
			}
		}
	}

	/**
	 * Ensure heavy options are stored without autoload.
	 *
	 * @return void
	 */
	protected function maybeDisableAutoloadedOptions(): void {
		$migrated = get_option( '\FPML_options_autoload_migrated' );

		if ( $migrated ) {
			return;
		}

		$options = array();

		if ( class_exists( '\FPML_Strings_Scanner' ) ) {
			$options[] = \FPML_Strings_Scanner::OPTION_KEY;
		}

		if ( class_exists( '\FPML_Strings_Override' ) ) {
			$options[] = \FPML_Strings_Override::OPTION_KEY;
		}

		if ( class_exists( '\FPML_Glossary' ) ) {
			$options[] = \FPML_Glossary::OPTION_KEY;
		}

		foreach ( array_filter( array_unique( $options ) ) as $option ) {
			$value = get_option( $option, null );

			if ( null === $value ) {
				continue;
			}

			update_option( $option, $value, false );
		}

		update_option( '\FPML_options_autoload_migrated', 1, false );
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @return void
	 */
	public function loadTextdomain(): void {
		$plugin_file = defined( 'FPML_PLUGIN_FILE' ) ? FPML_PLUGIN_FILE : ( defined( '\FPML_PLUGIN_FILE' ) ? \FPML_PLUGIN_FILE : __FILE__ );
		load_plugin_textdomain( 'fp-multilanguage', false, dirname( plugin_basename( $plugin_file ) ) . '/languages' );
	}

	/**
	 * Detect external multilingual plugins.
	 *
	 * @return string Empty string when no external plugin is detected, otherwise the identifier.
	 */
	protected static function detect_external_multilingual(): string {
		if ( defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'icl_object_id' ) ) {
			return 'wpml';
		}

		if ( defined( 'POLYLANG_VERSION' ) || function_exists( 'pll_current_language' ) ) {
			return 'polylang';
		}

		return '';
	}

	/**
	 * Get list of service IDs this provider registers.
	 *
	 * @return array<string> Service IDs.
	 */
	public function provides(): array {
		return array(
			'plugin.core',
			'plugin.assisted_mode',
			'plugin.assisted_reason',
		);
	}
}

