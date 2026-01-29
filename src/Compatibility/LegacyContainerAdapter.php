<?php
/**
 * Legacy Container Adapter.
 *
 * Provides backward compatibility for old Core\Container usage.
 * Delegates to new Kernel\Container system.
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
 * Adapter for legacy Core\Container class.
 *
 * @since 1.0.0
 * @deprecated Use Kernel\Container instead
 */
class LegacyContainerAdapter {
	/**
	 * Register a service.
	 *
	 * @param string   $name    Service name.
	 * @param callable $factory Factory callback.
	 * @return void
	 */
	public static function register( string $name, callable $factory ): void {
		// Try new container first
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
			if ( $kernel ) {
				$container = $kernel->getContainer();
				if ( $container && method_exists( $container, 'bind' ) ) {
					$container->bind( $name, $factory, true );
					return;
				}
			}
		}

		// Fallback to old container
		if ( class_exists( '\FP\Multilanguage\Core\Container' ) ) {
			\FP\Multilanguage\Core\Container::register( $name, $factory );
		}
	}

	/**
	 * Get a service.
	 *
	 * @param string $name Service name.
	 * @return mixed
	 */
	public static function get( string $name ) {
		// Try new container first
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
			if ( $kernel ) {
				$container = $kernel->getContainer();
				if ( $container && method_exists( $container, 'has' ) && $container->has( $name ) ) {
					return $container->get( $name );
				}
			}
		}

		// Fallback to old container
		if ( class_exists( '\FP\Multilanguage\Core\Container' ) ) {
			return \FP\Multilanguage\Core\Container::get( $name );
		}

		return null;
	}

	/**
	 * Check if service is registered.
	 *
	 * @param string $name Service name.
	 * @return bool
	 */
	public static function has( string $name ): bool {
		// Try new container first
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
			if ( $kernel ) {
				$container = $kernel->getContainer();
				if ( $container && method_exists( $container, 'has' ) ) {
					return $container->has( $name );
				}
			}
		}

		// Fallback to old container
		if ( class_exists( '\FP\Multilanguage\Core\Container' ) ) {
			return \FP\Multilanguage\Core\Container::has( $name );
		}

		return false;
	}
}














