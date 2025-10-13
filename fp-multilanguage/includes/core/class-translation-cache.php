<?php
/**
 * Translation Cache - Reduce API costs by caching translations.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache translation results to avoid redundant API calls.
 *
 * Uses dual-layer caching: object cache + transients for persistence.
 *
 * @since 0.4.0
 */
class FPML_Translation_Cache {
	/**
	 * Cache group for object cache.
	 */
	const CACHE_GROUP = 'fpml_translations';

	/**
	 * Default cache TTL (1 day).
	 */
	const CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * Singleton instance.
	 *
	 * @var FPML_Translation_Cache|null
	 */
	protected static $instance = null;

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
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.0
	 *
	 * @return FPML_Translation_Cache
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		// Hook to clear cache when content changes
		add_action( 'save_post', array( $this, 'maybe_invalidate_on_post_save' ), 10, 1 );
		add_action( 'deleted_post', array( $this, 'maybe_invalidate_on_post_delete' ), 10, 1 );
		add_action( 'edited_term', array( $this, 'maybe_invalidate_on_term_edit' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'maybe_invalidate_on_term_delete' ), 10, 3 );
	}

	/**
	 * Maybe invalidate cache when post is saved.
	 *
	 * @since 0.4.1
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function maybe_invalidate_on_post_save( $post_id ) {
		// If this is a translation being updated, we might want to invalidate related cache
		// For now, we keep cache as translations are immutable once created
		// This is intentionally left as a hook point for future needs
	}

	/**
	 * Maybe invalidate cache when post is deleted.
	 *
	 * @since 0.4.1
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function maybe_invalidate_on_post_delete( $post_id ) {
		// Cache invalidation on delete - translations are text-based, not post-based
		// So no action needed here
	}

	/**
	 * Maybe invalidate cache when term is edited.
	 *
	 * @since 0.4.1
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return void
	 */
	public function maybe_invalidate_on_term_edit( $term_id, $tt_id, $taxonomy ) {
		// Similar to posts - text-based cache, not entity-based
	}

	/**
	 * Maybe invalidate cache when term is deleted.
	 *
	 * @since 0.4.1
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return void
	 */
	public function maybe_invalidate_on_term_delete( $term_id, $tt_id, $taxonomy ) {
		// No action needed - cache is text-based
	}

	/**
	 * Get cached translation.
	 *
	 * @since 0.4.0
	 *
	 * @param string $text     Text to translate.
	 * @param string $provider Provider slug.
	 * @param string $source   Source language.
	 * @param string $target   Target language.
	 *
	 * @return string|false Cached translation or false if not found.
	 */
	public function get( $text, $provider, $source = 'it', $target = 'en' ) {
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
	 * @param string $text        Text to translate.
	 * @param string $provider    Provider slug.
	 * @param string $translation Translated text.
	 * @param string $source      Source language.
	 * @param string $target      Target language.
	 *
	 * @return bool True on success.
	 */
	public function set( $text, $provider, $translation, $source = 'it', $target = 'en' ) {
		if ( empty( $translation ) ) {
			return false;
		}

		$key = $this->generate_key( $text, $provider, $source, $target );
		$ttl = (int) apply_filters( 'fpml_cache_ttl', self::CACHE_TTL );

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
	protected function generate_key( $text, $provider, $source, $target ) {
		// Use md5 for consistent key length
		$hash = md5( $text . $provider . $source . $target );

		return 'fpml_' . $hash;
	}

	/**
	 * Clear cache for specific provider or all.
	 *
	 * @since 0.4.0
	 *
	 * @param string|null $provider Provider slug or null for all.
	 *
	 * @return void
	 */
	public function clear( $provider = null ) {
		global $wpdb;

		if ( null === $provider ) {
			// Clear object cache
			wp_cache_flush();

			// Clear all transients
			$wpdb->query(
				"DELETE FROM {$wpdb->options} 
				 WHERE option_name LIKE '_transient_fpml_%' 
				 OR option_name LIKE '_transient_timeout_fpml_%'"
			);
		} else {
			// Clear specific provider (harder, would need to track keys)
			// For now, just flush all
			wp_cache_flush();
		}

		$this->reset_stats();
	}

	/**
	 * Get cache statistics.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public function get_stats() {
		$total     = $this->stats['hits'] + $this->stats['misses'];
		$hit_rate  = $total > 0 ? ( $this->stats['hits'] / $total ) * 100 : 0;

		return array(
			'hits'     => $this->stats['hits'],
			'misses'   => $this->stats['misses'],
			'total'    => $total,
			'hit_rate' => round( $hit_rate, 2 ),
		);
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

	/**
	 * Get approximate cache size (transients only).
	 *
	 * @since 0.4.0
	 *
	 * @return int Size in bytes.
	 */
	public function get_cache_size() {
		global $wpdb;

		$result = $wpdb->get_var(
			"SELECT SUM(LENGTH(option_value)) 
			 FROM {$wpdb->options} 
			 WHERE option_name LIKE '_transient_fpml_%'"
		);

		return $result ? (int) $result : 0;
	}

	/**
	 * Get number of cached items.
	 *
	 * @since 0.4.0
	 *
	 * @return int
	 */
	public function get_cache_count() {
		global $wpdb;

		$result = $wpdb->get_var(
			"SELECT COUNT(*) 
			 FROM {$wpdb->options} 
			 WHERE option_name LIKE '_transient_fpml_%' 
			 AND option_name NOT LIKE '_transient_timeout_%'"
		);

		return $result ? (int) $result : 0;
	}
}
