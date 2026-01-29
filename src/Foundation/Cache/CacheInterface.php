<?php
/**
 * Cache Interface.
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
 * Cache interface for storing and retrieving cached data.
 *
 * @since 1.0.0
 */
interface CacheInterface {
	/**
	 * Retrieve a value from cache.
	 *
	 * @param string $key     Cache key.
	 * @param mixed  $default Default value if key not found.
	 * @return mixed Cached value or default.
	 */
	public function get( string $key, $default = null );

	/**
	 * Store a value in cache.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to cache.
	 * @param int    $ttl   Time to live in seconds (0 = no expiration).
	 * @return bool True on success, false on failure.
	 */
	public function set( string $key, $value, int $ttl = 0 ): bool;

	/**
	 * Delete a value from cache.
	 *
	 * @param string $key Cache key.
	 * @return bool True on success, false on failure.
	 */
	public function delete( string $key ): bool;

	/**
	 * Clear all cached values.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function clear(): bool;

	/**
	 * Check if a key exists in cache.
	 *
	 * @param string $key Cache key.
	 * @return bool True if exists, false otherwise.
	 */
	public function has( string $key ): bool;
}













