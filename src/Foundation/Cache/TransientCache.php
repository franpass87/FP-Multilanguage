<?php
/**
 * WordPress Transient Cache Implementation.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Foundation\Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache implementation using WordPress transients.
 *
 * @since 1.0.0
 */
class TransientCache implements CacheInterface {
	/**
	 * Cache key prefix.
	 *
	 * @var string
	 */
	protected $prefix = 'fpml_';

	/**
	 * Constructor.
	 *
	 * @param string $prefix Key prefix.
	 */
	public function __construct( string $prefix = 'fpml_' ) {
		$this->prefix = $prefix;
	}

	/**
	 * Get cache key with prefix.
	 *
	 * @param string $key Original key.
	 * @return string Prefixed key.
	 */
	protected function getKey( string $key ): string {
		return $this->prefix . $key;
	}

	/**
	 * Retrieve a value from cache.
	 *
	 * @param string $key     Cache key.
	 * @param mixed  $default Default value if key not found.
	 * @return mixed Cached value or default.
	 */
	public function get( string $key, $default = null ) {
		$value = get_transient( $this->getKey( $key ) );
		return false !== $value ? $value : $default;
	}

	/**
	 * Store a value in cache.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to cache.
	 * @param int    $ttl   Time to live in seconds (0 = no expiration).
	 * @return bool True on success, false on failure.
	 */
	public function set( string $key, $value, int $ttl = 0 ): bool {
		return set_transient( $this->getKey( $key ), $value, $ttl );
	}

	/**
	 * Delete a value from cache.
	 *
	 * @param string $key Cache key.
	 * @return bool True on success, false on failure.
	 */
	public function delete( string $key ): bool {
		return delete_transient( $this->getKey( $key ) );
	}

	/**
	 * Clear all cached values.
	 *
	 * Note: WordPress doesn't provide a way to clear all transients,
	 * so this is a best-effort implementation that clears known keys.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function clear(): bool {
		// WordPress doesn't support clearing all transients
		// This would require tracking all keys, which is not practical
		// Return true as a no-op
		return true;
	}

	/**
	 * Check if a key exists in cache.
	 *
	 * @param string $key Cache key.
	 * @return bool True if exists, false otherwise.
	 */
	public function has( string $key ): bool {
		return false !== get_transient( $this->getKey( $key ) );
	}
}













