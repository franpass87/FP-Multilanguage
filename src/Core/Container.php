<?php
/**
 * Simple Service Container for dependency management.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 */

namespace FP\Multilanguage\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lightweight service container to manage dependencies.
 *
 * Allows centralized registration and retrieval of service instances,
 * reducing coupling between components.
 *
 * @since 0.4.0
 * @deprecated 1.0.0 Use Kernel\Container instead. This class is kept for backward compatibility only.
 * 
 * This class now acts as an adapter that delegates to Kernel\Container.
 * It will be removed in version 1.1.0.
 */
class Container {
	/**
	 * Get the Kernel container instance.
	 *
	 * @return \FP\Multilanguage\Kernel\Container|null
	 */
	protected static function getKernelContainer() {
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
			if ( $kernel ) {
				return $kernel->getContainer();
			}
		}
		return null;
	}

	/**
	 * Registered service factories (legacy, for fallback only).
	 *
	 * @var array<string,callable>
	 */
	protected static $factories = array();

	/**
	 * Resolved service instances (legacy, for fallback only).
	 *
	 * @var array<string,object>
	 */
	protected static $instances = array();

	/**
	 * Register a service factory.
	 *
	 * @since 0.4.0
	 * @deprecated 1.0.0 Use Kernel\Container::bind() instead
	 *
	 * @param string   $name    Service name.
	 * @param callable $factory Factory callback that returns the service instance.
	 *
	 * @return void
	 */
	public static function register( $name, $factory ) {
		_doing_it_wrong( 
			'FP\Multilanguage\Core\Container::register()', 
			'Core\Container is deprecated. Use Kernel\Container::bind() instead.', 
			'1.0.0' 
		);
		
		// Try to register in Kernel container first
		$kernel_container = self::getKernelContainer();
		if ( $kernel_container ) {
			try {
				$kernel_container->bind( $name, $factory, true );
				return;
			} catch ( \Exception $e ) {
				// Fallback to legacy storage if Kernel fails
			}
		}
		
		// Legacy fallback
		if ( ! is_callable( $factory ) ) {
			return;
		}

		self::$factories[ $name ] = $factory;
	}

	/**
	 * Retrieve a service instance.
	 *
	 * @since 0.4.0
	 * @deprecated 1.0.0 Use Kernel\Container::get() instead
	 *
	 * @param string $name Service name.
	 *
	 * @return mixed|null Service instance or null if not found.
	 */
	public static function get( $name ) {
		// Try Kernel container first
		$kernel_container = self::getKernelContainer();
		if ( $kernel_container ) {
			try {
				if ( $kernel_container->has( $name ) ) {
					return $kernel_container->get( $name );
				}
			} catch ( \Exception $e ) {
				// Fallback to legacy if Kernel fails
			}
		}
		
		// Legacy fallback
		// Return cached instance if available.
		if ( isset( self::$instances[ $name ] ) ) {
			return self::$instances[ $name ];
		}

		// Check if factory exists.
		if ( ! isset( self::$factories[ $name ] ) ) {
			return null;
		}

		// Resolve and cache the instance.
		$instance = call_user_func( self::$factories[ $name ] );

		if ( null !== $instance ) {
			self::$instances[ $name ] = $instance;
		}

		return $instance;
	}

	/**
	 * Check if a service is registered.
	 *
	 * @since 0.4.0
	 * @deprecated 1.0.0 Use Kernel\Container::has() instead
	 *
	 * @param string $name Service name.
	 *
	 * @return bool
	 */
	public static function has( $name ) {
		// Try Kernel container first
		$kernel_container = self::getKernelContainer();
		if ( $kernel_container ) {
			try {
				return $kernel_container->has( $name );
			} catch ( \Exception $e ) {
				// Fallback to legacy if Kernel fails
			}
		}
		
		// Legacy fallback
		return isset( self::$factories[ $name ] );
	}

	/**
	 * Clear a specific service instance (forces re-resolution).
	 *
	 * @since 0.4.0
	 * @deprecated 1.0.0 Kernel\Container doesn't support clearing individual services
	 *
	 * @param string $name Service name.
	 *
	 * @return void
	 */
	public static function clear( $name ) {
		// Kernel container doesn't support clearing individual services
		// Only clear from legacy cache
		unset( self::$instances[ $name ] );
	}

	/**
	 * Clear all cached instances.
	 *
	 * Useful for testing or resetting the container state.
	 *
	 * @since 0.4.0
	 * @deprecated 1.0.0 Kernel\Container manages its own cache
	 *
	 * @return void
	 */
	public static function clear_all() {
		// Only clear legacy cache
		self::$instances = array();
	}

	/**
	 * Reset the entire container (factories and instances).
	 *
	 * @since 0.4.0
	 * @deprecated 1.0.0 Kernel\Container manages its own state
	 *
	 * @return void
	 */
	public static function reset() {
		// Only reset legacy state
		self::$factories = array();
		self::$instances = array();
	}
}

