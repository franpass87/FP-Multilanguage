<?php
/**
 * Base Hook Handler.
 *
 * Abstract base class for all hook handlers.
 * Provides common functionality and eliminates code duplication.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Core\Hooks;

use FP\Multilanguage\Core\ContainerAwareTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for hook handlers.
 *
 * Provides common functionality like assisted mode checking and delegation patterns.
 *
 * @since 1.0.0
 */
abstract class BaseHookHandler {
	use ContainerAwareTrait;

	/**
	 * Register hooks.
	 *
	 * Must be implemented by child classes.
	 * Some handlers may use register_hooks() instead for consistency.
	 *
	 * @return void
	 */
	abstract public function register(): void;

	/**
	 * Register hooks (alias for register() for consistency).
	 *
	 * Default implementation calls register().
	 * Override if needed.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		$this->register();
	}

	/**
	 * Get assisted mode status.
	 *
	 * @return bool
	 */
	protected function getAssistedMode(): bool {
		$container = $this->getContainer();
		if ( $container && $container->has( 'plugin.assisted_mode' ) ) {
			return (bool) $container->get( 'plugin.assisted_mode' );
		}
		return false;
	}

	/**
	 * Delegate to a handler service.
	 *
	 * @param string $handler_id Service ID of the handler.
	 * @param string $method    Method name to call.
	 * @param mixed  ...$args   Method arguments.
	 * @return mixed|null
	 */
	protected function delegateToHandler( string $handler_id, string $method, ...$args ) {
		$container = $this->getContainer();
		$handler = $container && $container->has( $handler_id ) 
			? $container->get( $handler_id ) 
			: null;

		if ( $handler && method_exists( $handler, $method ) ) {
			return call_user_func_array( array( $handler, $method ), $args );
		}

		return null;
	}

	/**
	 * Delegate to handler with fallback to Core\Plugin.
	 *
	 * @param string $handler_id Service ID of the handler.
	 * @param string $method    Method name to call.
	 * @param mixed  ...$args   Method arguments.
	 * @return mixed|null
	 */
	protected function delegateWithFallback( string $handler_id, string $method, ...$args ) {
		// Try handler first
		$result = $this->delegateToHandler( $handler_id, $method, ...$args );
		if ( null !== $result ) {
			return $result;
		}

		// Fallback to Core\Plugin
		return $this->delegateToLegacyPlugin( $method, ...$args );
	}

	/**
	 * Delegate to legacy Core\Plugin for backward compatibility.
	 *
	 * @param string $method Method name to call.
	 * @param mixed  ...$args Method arguments.
	 * @return mixed|null
	 */
	protected function delegateToLegacyPlugin( string $method, ...$args ) {
		return $this->delegateToLegacyClass( '\FP\Multilanguage\Core\Plugin', $method, ...$args );
	}

	/**
	 * Delegate to a legacy singleton class.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @param string $method    Method name to call.
	 * @param mixed  ...$args   Method arguments.
	 * @return mixed|null
	 */
	protected function delegateToLegacyClass( string $class_name, string $method, ...$args ) {
		if ( class_exists( $class_name ) ) {
			$instance = call_user_func( array( $class_name, 'instance' ) );
			if ( $instance && method_exists( $instance, $method ) ) {
				return call_user_func_array( array( $instance, $method ), $args );
			}
		}
		return null;
	}

	/**
	 * Check if should register hooks (not in assisted mode).
	 *
	 * @return bool
	 */
	protected function shouldRegister(): bool {
		return ! $this->getAssistedMode();
	}
}

