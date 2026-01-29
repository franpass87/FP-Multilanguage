<?php
/**
 * Legacy Aliases for Backward Compatibility.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Compatibility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Legacy aliases to maintain backward compatibility.
 *
 * This class provides class aliases for old class names
 * that may be used by other plugins or custom code.
 *
 * @since 1.0.0
 */
class LegacyAliases {
	/**
	 * Register all legacy aliases.
	 *
	 * @return void
	 */
	public static function register(): void {
		// Container aliases - map to both old Core\Container and new Kernel\Container
		if ( ! class_exists( '\FPML_Container' ) ) {
			// Try new Kernel container first, fallback to old Core container
			if ( class_exists( 'FP\Multilanguage\Kernel\Container' ) ) {
				class_alias( 'FP\Multilanguage\Kernel\Container', 'FPML_Container' );
			} elseif ( class_exists( 'FP\Multilanguage\Core\Container' ) ) {
				class_alias( 'FP\Multilanguage\Core\Container', 'FPML_Container' );
			}
		}

		// Settings aliases - Map to SettingsAdapter for backward compatibility
		if ( ! class_exists( '\FPML_Settings' ) ) {
			// Try SettingsAdapter first (new implementation)
			if ( class_exists( 'FP\Multilanguage\Foundation\Options\SettingsAdapter' ) ) {
				class_alias( 'FP\Multilanguage\Foundation\Options\SettingsAdapter', '\FPML_Settings' );
				// Also alias Settings class to SettingsAdapter
				if ( ! class_exists( 'FP\Multilanguage\Settings' ) ) {
					class_alias( 'FP\Multilanguage\Foundation\Options\SettingsAdapter', 'FP\Multilanguage\Settings' );
				}
			} elseif ( class_exists( 'FP\Multilanguage\Settings' ) ) {
				// Fallback to old Settings class
				class_alias( 'FP\Multilanguage\Settings', '\FPML_Settings' );
			}
		}

		// Logger aliases - Map to LoggerAdapter for backward compatibility
		if ( ! class_exists( '\FPML_Logger' ) ) {
			// Try LoggerAdapter first (new implementation)
			if ( class_exists( 'FP\Multilanguage\Foundation\Logger\LoggerAdapter' ) ) {
				class_alias( 'FP\Multilanguage\Foundation\Logger\LoggerAdapter', '\FPML_Logger' );
				// Also alias Logger class to LoggerAdapter
				if ( ! class_exists( 'FP\Multilanguage\Logger' ) ) {
					class_alias( 'FP\Multilanguage\Foundation\Logger\LoggerAdapter', 'FP\Multilanguage\Logger' );
				}
			} elseif ( class_exists( 'FP\Multilanguage\Logger' ) ) {
				// Fallback to old Logger class
				class_alias( 'FP\Multilanguage\Logger', '\FPML_Logger' );
			}
		}

		// Queue aliases (keep existing)
		if ( ! class_exists( '\FPML_Queue' ) && class_exists( 'FP\Multilanguage\Queue' ) ) {
			class_alias( 'FP\Multilanguage\Queue', 'FPML_Queue' );
		}

		// Plugin aliases
		if ( ! class_exists( '\FPML_Plugin' ) ) {
			// Try new Kernel\Plugin first, fallback to old Core\Plugin
			if ( class_exists( 'FP\Multilanguage\Kernel\Plugin' ) ) {
				class_alias( 'FP\Multilanguage\Kernel\Plugin', 'FPML_Plugin' );
			} elseif ( class_exists( 'FP\Multilanguage\Core\Plugin' ) ) {
				class_alias( 'FP\Multilanguage\Core\Plugin', 'FPML_Plugin' );
			}
		}

		// Add more aliases as needed for backward compatibility
		// These will be removed in Phase 4
	}
}





