<?php
/**
 * Dependency Resolver Service.
 *
 * Provides a consistent pattern for resolving dependencies with fallback chain.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Core\Services;

use FP\Multilanguage\Kernel\Container;
use FP\Multilanguage\Core\Container as CoreContainer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for resolving dependencies with fallback chain.
 *
 * @since 1.0.0
 */
class DependencyResolver {
	/**
	 * Kernel container instance.
	 *
	 * @var Container|null
	 */
	protected $kernel_container;

	/**
	 * Constructor.
	 *
	 * @param Container|null $kernel_container Kernel container instance.
	 */
	public function __construct( ?Container $kernel_container = null ) {
		$this->kernel_container = $kernel_container;
	}

	/**
	 * Resolve a service with fallback chain.
	 *
	 * Tries in order:
	 * 1. Kernel container
	 * 2. Core container
	 * 3. Singleton instance (if class exists)
	 *
	 * @param string        $service_id Service ID.
	 * @param string|null   $class_name Class name for singleton fallback.
	 * @param callable|null $fallback   Custom fallback function.
	 * @return mixed|null
	 */
	public function resolve( string $service_id, ?string $class_name = null, ?callable $fallback = null ) {
		// Try Kernel container first
		if ( $this->kernel_container && $this->kernel_container->has( $service_id ) ) {
			return $this->kernel_container->get( $service_id );
		}

		// Try Core container
		if ( class_exists( '\FP\Multilanguage\Core\Container' ) ) {
			$core_container = CoreContainer::getInstance();
			if ( $core_container && $core_container->has( $service_id ) ) {
				return $core_container->get( $service_id );
			}
		}

		// Try singleton if class name provided
		if ( $class_name && class_exists( $class_name ) ) {
			if ( method_exists( $class_name, 'instance' ) ) {
				return call_user_func( array( $class_name, 'instance' ) );
			}
		}

		// Try custom fallback
		if ( $fallback ) {
			return $fallback();
		}

		return null;
	}

	/**
	 * Resolve with multiple fallback options.
	 *
	 * @param string   $service_id Service ID.
	 * @param array    $fallbacks  Array of fallback options: ['class' => ClassName, 'method' => 'methodName'] or callable.
	 * @return mixed|null
	 */
	public function resolveWithFallbacks( string $service_id, array $fallbacks = array() ) {
		// Try standard resolution first
		$result = $this->resolve( $service_id );
		if ( null !== $result ) {
			return $result;
		}

		// Try each fallback
		foreach ( $fallbacks as $fallback ) {
			if ( is_callable( $fallback ) ) {
				$result = $fallback();
				if ( null !== $result ) {
					return $result;
				}
			} elseif ( is_array( $fallback ) && isset( $fallback['class'] ) ) {
				$class_name = $fallback['class'];
				if ( class_exists( $class_name ) ) {
					if ( isset( $fallback['method'] ) && method_exists( $class_name, $fallback['method'] ) ) {
						$result = call_user_func( array( $class_name, $fallback['method'] ) );
						if ( null !== $result ) {
							return $result;
						}
					} elseif ( method_exists( $class_name, 'instance' ) ) {
						$result = call_user_func( array( $class_name, 'instance' ) );
						if ( null !== $result ) {
							return $result;
						}
					}
				}
			}
		}

		return null;
	}

	/**
	 * Set kernel container.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	public function setKernelContainer( Container $container ): void {
		$this->kernel_container = $container;
	}
}








