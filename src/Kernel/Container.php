<?php
/**
 * PSR-11 Compatible Dependency Injection Container.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Kernel;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PSR-11 compatible service container.
 *
 * @since 1.0.0
 */
class Container implements ContainerInterface {
	/**
	 * Registered service factories.
	 *
	 * @var array<string,callable>
	 */
	protected $factories = array();

	/**
	 * Resolved service instances (singletons).
	 *
	 * @var array<string,object>
	 */
	protected $instances = array();

	/**
	 * Aliases for service IDs.
	 *
	 * @var array<string,string>
	 */
	protected $aliases = array();

	/**
	 * Services being resolved (for circular dependency detection).
	 *
	 * @var array<string,bool>
	 */
	protected $resolving = array();

	/**
	 * Finds an entry of the container by its identifier and returns it.
	 *
	 * @param string $id Identifier of the entry to look for.
	 * @return mixed Entry.
	 * @throws NotFoundExceptionInterface  No entry was found for this identifier.
	 * @throws ContainerExceptionInterface Error while retrieving the entry.
	 */
	public function get( $id ) {
		// Resolve alias if present
		$id = $this->resolveAlias( $id );

		// Return cached instance if available
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		// Check for circular dependency
		if ( isset( $this->resolving[ $id ] ) ) {
			throw new ContainerException( "Circular dependency detected for service: {$id}" );
		}

		// Check if factory exists
		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new NotFoundException( "Service not found: {$id}" );
		}

		// Mark as resolving
		$this->resolving[ $id ] = true;

		try {
			// Resolve the instance
			$factory = $this->factories[ $id ];
			$instance = is_callable( $factory ) ? call_user_func( $factory, $this ) : $factory;

			if ( null === $instance ) {
				throw new ContainerException( "Factory for {$id} returned null" );
			}

			// Cache the instance
			$this->instances[ $id ] = $instance;

			return $instance;
		} finally {
			// Remove from resolving
			unset( $this->resolving[ $id ] );
		}
	}

	/**
	 * Returns true if the container can return an entry for the given identifier.
	 *
	 * @param string $id Identifier of the entry to look for.
	 * @return bool
	 */
	public function has( string $id ): bool {
		$id = $this->resolveAlias( $id );
		return isset( $this->factories[ $id ] ) || isset( $this->instances[ $id ] );
	}

	/**
	 * Register a service factory.
	 *
	 * @param string   $id      Service identifier.
	 * @param callable $factory Factory callback that receives Container and returns service.
	 * @param bool     $shared  Whether to share the instance (singleton).
	 * @return void
	 */
	public function bind( string $id, $factory, bool $shared = true ): void {
		if ( ! is_callable( $factory ) && ! is_object( $factory ) ) {
			throw new ContainerException( "Invalid factory for service: {$id}" );
		}

		$this->factories[ $id ] = $factory;

		// If not shared, clear any existing instance
		if ( ! $shared && isset( $this->instances[ $id ] ) ) {
			unset( $this->instances[ $id ] );
		}
	}

	/**
	 * Register a singleton instance.
	 *
	 * @param string $id       Service identifier.
	 * @param object $instance Service instance.
	 * @return void
	 */
	public function singleton( string $id, $instance ): void {
		if ( ! is_object( $instance ) ) {
			throw new ContainerException( "Instance must be an object for service: {$id}" );
		}

		$this->instances[ $id ] = $instance;
		$this->factories[ $id ] = function() use ( $instance ) {
			return $instance;
		};
	}

	/**
	 * Register an alias for a service ID.
	 *
	 * @param string $alias Alias name.
	 * @param string $id    Actual service ID.
	 * @return void
	 */
	public function alias( string $alias, string $id ): void {
		$this->aliases[ $alias ] = $id;
	}

	/**
	 * Resolve an alias to the actual service ID.
	 *
	 * @param string $id Service ID or alias.
	 * @return string Actual service ID.
	 */
	protected function resolveAlias( string $id ): string {
		return $this->aliases[ $id ] ?? $id;
	}

	/**
	 * Clear a specific service instance.
	 *
	 * @param string $id Service identifier.
	 * @return void
	 */
	public function forget( string $id ): void {
		unset( $this->instances[ $id ] );
	}

	/**
	 * Clear all cached instances.
	 *
	 * @return void
	 */
	public function flush(): void {
		$this->instances = array();
		$this->resolving = array();
	}

	/**
	 * Reset the entire container.
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->factories = array();
		$this->instances = array();
		$this->aliases = array();
		$this->resolving = array();
	}
}

/**
 * Exception thrown when a service is not found.
 *
 * @since 1.0.0
 */
class NotFoundException extends \Exception implements NotFoundExceptionInterface {
}

/**
 * Exception thrown when there's an error in the container.
 *
 * @since 1.0.0
 */
class ContainerException extends \Exception implements ContainerExceptionInterface {
}













