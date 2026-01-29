<?php
/**
 * Cache Storage - Handles storing and retrieving translations from cache.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 */

namespace FP\Multilanguage\Core\Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles cache storage operations (get, set, key generation).
 *
 * @since 0.4.0
 */
class CacheStorage {
	/**
	 * Cache group for object cache.
	 */
	const CACHE_GROUP = '\FPML_translations';

	/**
	 * Default cache TTL (1 day).
	 */
	const CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * Cache statistics.
	 *
	 * @var array
	 */
	protected $stats = array(
		'hits'   => 0,
		'misses' => 0,
	);

	/**
	 * Get translation from cache.
	 *
	 * @since 0.4.0
	 *
	 * @param string $text     Text to translate.
	 * @param string $provider Provider slug.
	 * @param string $source   Source language code. Default 'it'.
	 * @param string $target   Target language code. Default 'en'.
	 * @return string|false Cached translation or false if not found.
	 */
	public function get( string $text, string $provider, string $source = 'it', string $target = 'en' ): string|false {
		$key = $this->generate_key( $text, $provider, $source, $target );

		// Try object cache first (fastest)
		$cached = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			$this->stats['hits']++;
			return $cached;
		}

		// Try transient (persistent)
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			// Populate object cache for next request
			wp_cache_set( $key, $cached, self::CACHE_GROUP, self::CACHE_TTL );
			$this->stats['hits']++;
			return $cached;
		}

		$this->stats['misses']++;
		return false;
	}

	/**
	 * Store translation in cache.
	 *
	 * @since 0.4.0
	 *
	 * @param string $text        Original text.
	 * @param string $provider    Provider slug.
	 * @param string $translation Translated text.
	 * @param string $source      Source language code. Default 'it'.
	 * @param string $target      Target language code. Default 'en'.
	 * @return bool True if cached, false otherwise.
	 */
	public function set( string $text, string $provider, string $translation, string $source = 'it', string $target = 'en' ): bool {
		if ( empty( $translation ) ) {
			return false;
		}

		$key = $this->generate_key( $text, $provider, $source, $target );
		$ttl = (int) apply_filters( '\FPML_cache_ttl', self::CACHE_TTL );

		// Store in both layers
		wp_cache_set( $key, $translation, self::CACHE_GROUP, $ttl );
		set_transient( $key, $translation, $ttl );

		return true;
	}

	/**
	 * Generate cache key from parameters.
	 *
	 * @since 0.4.0
	 *
	 * @param string $text     Text to translate.
	 * @param string $provider Provider slug.
	 * @param string $source   Source language.
	 * @param string $target   Target language.
	 *
	 * @return string
	 */
	public function generate_key( $text, $provider, $source, $target ) {
		// Normalize input for consistent hashing
		$normalized = trim( (string) $text );
		$provider = sanitize_key( $provider );
		$source = strtolower( sanitize_text_field( $source ) );
		$target = strtolower( sanitize_text_field( $target ) );

		// Use md5 for consistent key length
		$hash = md5( $normalized . $provider . $source . $target );

		return 'fpml_' . $provider . '_' . $hash;
	}

	/**
	 * Get cache statistics.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public function get_stats() {
		return $this->stats;
	}

	/**
	 * Reset statistics.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function reset_stats() {
		$this->stats = array(
			'hits'   => 0,
			'misses' => 0,
		);
	}
}















