<?php
/**
 * SEO Sitemap Cache - Manages sitemap caching.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\SEO\Sitemap;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages sitemap caching.
 *
 * @since 0.10.0
 */
class SitemapCache {
	/**
	 * Get cache key.
	 *
	 * @since 0.10.0
	 *
	 * @return string
	 */
	public function get_cache_key(): string {
		return '\FPML_sitemap_en_cache';
	}

	/**
	 * Get cached sitemap.
	 *
	 * @since 0.10.0
	 *
	 * @return string|null
	 */
	public function get_cached(): ?string {
		$cache_key = $this->get_cache_key();
		$xml = get_transient( $cache_key );

		return is_string( $xml ) && '' !== $xml ? $xml : null;
	}

	/**
	 * Cache sitemap.
	 *
	 * @since 0.10.0
	 *
	 * @param string $xml Sitemap XML.
	 * @return void
	 */
	public function cache( string $xml ): void {
		$cache_key = $this->get_cache_key();
		set_transient( $cache_key, $xml, HOUR_IN_SECONDS );
	}

	/**
	 * Get lock for building sitemap.
	 *
	 * @since 0.10.0
	 *
	 * @return bool True if lock acquired, false otherwise.
	 */
	public function acquire_lock(): bool {
		$lock_key = $this->get_cache_key() . '_lock';
		$lock = get_transient( $lock_key );

		if ( false === $lock ) {
			set_transient( $lock_key, 1, 30 );
			return true;
		}

		return false;
	}

	/**
	 * Release lock.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function release_lock(): void {
		$lock_key = $this->get_cache_key() . '_lock';
		delete_transient( $lock_key );
	}

	/**
	 * Wait for lock and get cached result.
	 *
	 * @since 0.10.0
	 *
	 * @return string|null
	 */
	public function wait_for_lock(): ?string {
		usleep( 100000 ); // 0.1 seconds
		return $this->get_cached();
	}
}















