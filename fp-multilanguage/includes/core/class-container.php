<?php
/**
 * Simple Service Container for dependency management.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 */

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
 */
class FPML_Container {
	/**
	 * Registered service factories.
	 *
	 * @var array<string,callable>
	 */
	protected static $factories = array();

	/**
	 * Resolved service instances (singletons).
	 *
	 * @var array<string,object>
	 */
	protected static $instances = array();

	/**
	 * Register a service factory.
	 *
	 * @since 0.4.0
	 *
	 * @param string   $name    Service name.
	 * @param callable $factory Factory callback that returns the service instance.
	 *
	 * @return void
	 */
	public static function register( $name, $factory ) {
		if ( ! is_callable( $factory ) ) {
			return;
		}

		self::$factories[ $name ] = $factory;
	}

	/**
	 * Retrieve a service instance.
	 *
	 * @since 0.4.0
	 *
	 * @param string $name Service name.
	 *
	 * @return mixed|null Service instance or null if not found.
	 */
	public static function get( $name ) {
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
	 *
	 * @param string $name Service name.
	 *
	 * @return bool
	 */
	public static function has( $name ) {
		return isset( self::$factories[ $name ] );
	}

	/**
	 * Clear a specific service instance (forces re-resolution).
	 *
	 * @since 0.4.0
	 *
	 * @param string $name Service name.
	 *
	 * @return void
	 */
	public static function clear( $name ) {
		unset( self::$instances[ $name ] );
	}

	/**
	 * Clear all cached instances.
	 *
	 * Useful for testing or resetting the container state.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public static function clear_all() {
		self::$instances = array();
	}

	/**
	 * Reset the entire container (factories and instances).
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public static function reset() {
		self::$factories = array();
		self::$instances = array();
	}
}
