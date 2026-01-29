<?php
/**
 * Container Bridge - Bridges old Container::get() calls to new Kernel\Container.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Core;

use FP\Multilanguage\Kernel\Container as KernelContainer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridge class to maintain backward compatibility with old Container::get() calls.
 *
 * This class provides a static interface that maps to the new Kernel\Container.
 *
 * @since 1.0.0
 */
class ContainerBridge {
	/**
	 * Get a service from the container.
	 *
	 * @param string $name Service name.
	 * @return mixed|null Service instance or null if not found.
	 */
	public static function get( string $name ) {
		// Try new Kernel container first
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			try {
				// Get container from Plugin kernel
				$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
				if ( $kernel && method_exists( $kernel, 'getContainer' ) ) {
					$container = $kernel->getContainer();
					if ( $container ) {
						try {
							if ( $container->has( $name ) ) {
								return $container->get( $name );
							}
						} catch ( \Throwable $e ) {
							// Service not found, continue to fallback
						}
					}
				}
			} catch ( \Throwable $e ) {
				// Fallback to old container
			}
		}

		// Fallback to old Container
		if ( class_exists( '\FP\Multilanguage\Core\Container' ) ) {
			return \FP\Multilanguage\Core\Container::get( $name );
		}

		return null;
	}

	/**
	 * Check if a service is registered.
	 *
	 * @param string $name Service name.
	 * @return bool
	 */
	public static function has( string $name ): bool {
		// Try new Kernel container first
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			try {
				$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
				if ( $kernel && method_exists( $kernel, 'getContainer' ) ) {
					$container = $kernel->getContainer();
					if ( $container ) {
						return $container->has( $name );
					}
				}
			} catch ( \Throwable $e ) {
				// Fallback to old container
			}
		}

		// Fallback to old Container
		if ( class_exists( '\FP\Multilanguage\Core\Container' ) ) {
			return \FP\Multilanguage\Core\Container::has( $name );
		}

		return false;
	}

	/**
	 * Register a service factory.
	 *
	 * @param string   $name    Service name.
	 * @param callable $factory Factory callback.
	 * @return void
	 */
	public static function register( string $name, callable $factory ): void {
		// Try new Kernel container first
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			try {
				$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
				if ( $kernel && method_exists( $kernel, 'getContainer' ) ) {
					$container = $kernel->getContainer();
					if ( $container && method_exists( $container, 'bind' ) ) {
						$container->bind( $name, $factory );
						return;
					}
				}
			} catch ( \Throwable $e ) {
				// Fallback to old container
			}
		}

		// Fallback to old Container
		if ( class_exists( '\FP\Multilanguage\Core\Container' ) ) {
			\FP\Multilanguage\Core\Container::register( $name, $factory );
		}
	}
}

