<?php
/**
 * Container Aware Trait.
 *
 * Provides a helper method to get the Kernel container instance.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait that provides container access.
 *
 * @since 1.0.0
 */
trait ContainerAwareTrait {
	/**
	 * Get container instance (new Kernel container if available, null otherwise).
	 *
	 * @return \FP\Multilanguage\Kernel\Container|null
	 */
	protected function getContainer() {
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
			if ( $kernel ) {
				return $kernel->getContainer();
			}
		}
		return null;
	}

	/**
	 * Get service from container with fallback.
	 *
	 * @param string $service_id Service ID.
	 * @param callable|null $fallback Fallback function if service not found.
	 * @return mixed
	 */
	protected function getService( string $service_id, ?callable $fallback = null ) {
		$container = $this->getContainer();
		if ( $container && $container->has( $service_id ) ) {
			return $container->get( $service_id );
		}
		if ( $fallback ) {
			return $fallback();
		}
		return null;
	}
}














