<?php
/**
 * Legacy Plugin Adapter.
 *
 * Provides backward compatibility for old Core\Plugin usage.
 * Delegates to new Kernel\Plugin system.
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
 * Adapter for legacy Core\Plugin class.
 *
 * @since 1.0.0
 * @deprecated Use Kernel\Plugin instead
 */
class LegacyPluginAdapter {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Activate plugin.
	 *
	 * @return void
	 */
	public static function activate(): void {
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
			if ( $kernel ) {
				$kernel->activate();
			}
		}
	}

	/**
	 * Deactivate plugin.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
			if ( $kernel ) {
				$kernel->deactivate();
			}
		}
	}

	/**
	 * Get old plugin instance (Core or Kernel).
	 *
	 * @return object|null
	 */
	protected function getOldPlugin() {
		// Try old Core first (has more methods like get_diagnostics_snapshot)
		if ( class_exists( '\FP\Multilanguage\Core\Plugin' ) ) {
			if ( method_exists( '\FP\Multilanguage\Core\Plugin', 'instance' ) ) {
				return \FP\Multilanguage\Core\Plugin::instance();
			}
		}
		
		// Fallback to new Kernel
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
			if ( $kernel ) {
				return $kernel;
			}
		}
		
		return null;
	}

	/**
	 * Check if plugin is in assisted mode.
	 *
	 * @return bool
	 */
	public function is_assisted_mode(): bool {
		$old_plugin = $this->getOldPlugin();
		if ( $old_plugin && method_exists( $old_plugin, 'is_assisted_mode' ) ) {
			return $old_plugin->is_assisted_mode();
		}
		return false;
	}

	/**
	 * Magic method to delegate calls to old Plugin instance.
	 *
	 * @param string $name      Method name.
	 * @param array  $arguments Method arguments.
	 * @return mixed
	 */
	public function __call( string $name, array $arguments ) {
		$old_plugin = $this->getOldPlugin();
		if ( $old_plugin && method_exists( $old_plugin, $name ) ) {
			return call_user_func_array( array( $old_plugin, $name ), $arguments );
		}
		return null;
	}

	/**
	 * Magic method to delegate static calls to old Plugin class.
	 *
	 * @param string $name      Method name.
	 * @param array  $arguments Method arguments.
	 * @return mixed
	 */
	public static function __callStatic( string $name, array $arguments ) {
		if ( class_exists( '\FP\Multilanguage\Core\Plugin' ) ) {
			if ( method_exists( '\FP\Multilanguage\Core\Plugin', $name ) ) {
				return call_user_func_array( array( '\FP\Multilanguage\Core\Plugin', $name ), $arguments );
			}
		}
		return null;
	}
}

