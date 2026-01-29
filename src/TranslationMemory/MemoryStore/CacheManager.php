<?php
/**
 * Translation Memory Store Cache Manager - Manages translation cache.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\TranslationMemory\MemoryStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages translation cache (runtime, transient, persistent).
 *
 * @since 0.10.0
 */
class CacheManager {
	/**
	 * Cache TTL (7 days for stable translations).
	 *
	 * @var int
	 */
	const CACHE_TTL = WEEK_IN_SECONDS;

	/**
	 * In-memory cache for single request.
	 *
	 * @var array
	 */
	protected array $runtime_cache = array();

	/**
	 * Generate cache key for translation.
	 *
	 * @since 0.10.0
	 *
	 * @param string $source      Source text.
	 * @param string $source_lang Source language.
	 * @param string $target_lang Target language.
	 * @return string
	 */
	public function get_cache_key( string $source, string $source_lang, string $target_lang ): string {
		return 'fpml_tm_' . md5( $source . '|' . $source_lang . '|' . $target_lang );
	}

	/**
	 * Get from runtime cache.
	 *
	 * @since 0.10.0
	 *
	 * @param string $cache_key Cache key.
	 * @return string|null Translation or null if not found.
	 */
	public function get_runtime_cache( string $cache_key ): ?string {
		return $this->runtime_cache[ $cache_key ] ?? null;
	}

	/**
	 * Set runtime cache.
	 *
	 * @since 0.10.0
	 *
	 * @param string $cache_key  Cache key.
	 * @param string $translation Translation text.
	 * @return void
	 */
	public function set_runtime_cache( string $cache_key, string $translation ): void {
		$this->runtime_cache[ $cache_key ] = $translation;
	}

	/**
	 * Get from transient cache.
	 *
	 * @since 0.10.0
	 *
	 * @param string $cache_key Cache key.
	 * @return string|null Translation or null if not found.
	 */
	public function get_transient_cache( string $cache_key ): ?string {
		$cached = get_transient( $cache_key );
		return false !== $cached ? $cached : null;
	}

	/**
	 * Cache translation result.
	 *
	 * @since 0.10.0
	 *
	 * @param string $cache_key   Cache key.
	 * @param string $translation Translation text.
	 * @return void
	 */
	public function cache_translation( string $cache_key, string $translation ): void {
		// Runtime cache
		$this->runtime_cache[ $cache_key ] = $translation;

		// Transient cache (7 days for stable translations)
		set_transient( $cache_key, $translation, self::CACHE_TTL );

		// Persistent file cache (fallback)
		$this->write_persistent_cache( $cache_key, $translation );
	}

	/**
	 * Write to persistent file cache.
	 *
	 * @since 0.10.0
	 *
	 * @param string $cache_key   Cache key.
	 * @param string $translation Translation text.
	 * @return void
	 */
	protected function write_persistent_cache( string $cache_key, string $translation ): void {
		$upload_dir = wp_upload_dir();
		$cache_dir  = trailingslashit( $upload_dir['basedir'] ) . 'fpml-cache';

		// Create directory if it doesn't exist
		if ( ! file_exists( $cache_dir ) ) {
			wp_mkdir_p( $cache_dir );

			// Protect with .htaccess
			$htaccess = $cache_dir . '/.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				file_put_contents( $htaccess, "deny from all\n" );
			}
		}

		$cache_file = $cache_dir . '/' . $cache_key . '.txt';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $cache_file, $translation );
	}
}















