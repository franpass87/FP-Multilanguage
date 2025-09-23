<?php
namespace FPMultilanguage\Support;

use InvalidArgumentException;

class Container {

	/**
	 * @var array<string, callable|object>
	 */
	private array $bindings = array();

	/**
	 * @var array<string, mixed>
	 */
	private array $resolved = array();

	public function set( string $id, $concrete ): void {
		$this->bindings[ $id ] = $concrete;
	}

	public function has( string $id ): bool {
		return array_key_exists( $id, $this->bindings ) || array_key_exists( $id, $this->resolved );
	}

	/**
	 * @template T
	 *
	 * @param string $id
	 *
	 * @return mixed
	 */
	public function get( string $id ) {
		if ( array_key_exists( $id, $this->resolved ) ) {
			return $this->resolved[ $id ];
		}

		if ( ! array_key_exists( $id, $this->bindings ) ) {
			throw new InvalidArgumentException( sprintf( 'Service "%s" is not registered in the container.', $id ) );
		}

		$concrete = $this->bindings[ $id ];

		if ( is_callable( $concrete ) ) {
			$object = $concrete( $this );
		} else {
			$object = $concrete;
		}

		$this->resolved[ $id ] = $object;

		return $object;
	}

	public function forget( string $id ): void {
		unset( $this->bindings[ $id ], $this->resolved[ $id ] );
	}
}
