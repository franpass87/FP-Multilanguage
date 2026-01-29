<?php
/**
 * Hook Manager - Centralized hook registration and management.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Core\Hook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralized hook manager for WordPress hooks.
 *
 * @since 1.0.0
 */
class HookManager {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Registered hooks.
	 *
	 * @var array<string,array>
	 */
	protected $registered_hooks = array();

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
	 * Constructor.
	 */
	protected function __construct() {
		// Hook manager is initialized but hooks are registered by service providers
	}

	/**
	 * Register a WordPress action.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Number of arguments.
	 * @return void
	 */
	public function addAction( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void {
		add_action( $hook, $callback, $priority, $args );
		$this->registered_hooks[ $hook ][] = array(
			'type'     => 'action',
			'callback' => $callback,
			'priority' => $priority,
		);
	}

	/**
	 * Register a WordPress filter.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Number of arguments.
	 * @return void
	 */
	public function addFilter( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void {
		add_filter( $hook, $callback, $priority, $args );
		$this->registered_hooks[ $hook ][] = array(
			'type'     => 'filter',
			'callback' => $callback,
			'priority' => $priority,
		);
	}

	/**
	 * Remove a WordPress action.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @return void
	 */
	public function removeAction( string $hook, callable $callback, int $priority = 10 ): void {
		remove_action( $hook, $callback, $priority );
	}

	/**
	 * Remove a WordPress filter.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @return void
	 */
	public function removeFilter( string $hook, callable $callback, int $priority = 10 ): void {
		remove_filter( $hook, $callback, $priority );
	}

	/**
	 * Get all registered hooks.
	 *
	 * @return array Registered hooks.
	 */
	public function getRegisteredHooks(): array {
		return $this->registered_hooks;
	}

	/**
	 * Check if a hook is registered.
	 *
	 * @param string $hook Hook name.
	 * @return bool True if registered.
	 */
	public function hasHook( string $hook ): bool {
		return isset( $this->registered_hooks[ $hook ] );
	}
}









