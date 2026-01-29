<?php
/**
 * Translation Cache - Reduce API costs by caching translations.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 */

namespace FP\Multilanguage\Core;

use FP\Multilanguage\Core\Cache\CacheStorage;
use FP\Multilanguage\Core\Cache\CacheInvalidator;
use FP\Multilanguage\Core\Cache\CacheStats;
use FP\Multilanguage\Core\Cache\CacheInfo;
use FP\Multilanguage\Core\Cache\CacheWarmer;

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
class TranslationCache {
	/**
	 * Cache group for object cache.
	 */
	const CACHE_GROUP = '\FPML_translations';

	/**
	 * Default cache TTL (1 day).
	 */
	const CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * Singleton instance.
	 *
	 * @var \FPML_Translation_Cache|null
	 */
	protected static $instance = null;

	/**
	 * Cache storage instance.
	 *
	 * @var CacheStorage
	 */
	protected $storage;

	/**
	 * Cache invalidator instance.
	 *
	 * @var CacheInvalidator
	 */
	protected $invalidator;

	/**
	 * Cache stats instance.
	 *
	 * @var CacheStats
	 */
	protected $stats;

	/**
	 * Cache info instance.
	 *
	 * @var CacheInfo
	 */
	protected $info;

	/**
	 * Cache warmer instance.
	 *
	 * @var CacheWarmer
	 */
	protected $warmer;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.0
	 *
	 * @return \FPML_Translation_Cache
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
		$this->storage = new CacheStorage();
		$this->invalidator = new CacheInvalidator( $this->storage );
		$this->stats = new CacheStats( $this->storage );
		$this->info = new CacheInfo();
		$this->warmer = new CacheWarmer( $this->storage );

		// Hook to clear cache when content changes
		add_action( 'save_post', array( $this->invalidator, 'maybe_invalidate_on_post_save' ), 10, 1 );
		add_action( 'deleted_post', array( $this->invalidator, 'maybe_invalidate_on_post_delete' ), 10, 1 );
		add_action( 'edited_term', array( $this->invalidator, 'maybe_invalidate_on_term_edit' ), 10, 3 );
		add_action( 'delete_term', array( $this->invalidator, 'maybe_invalidate_on_term_delete' ), 10, 3 );
	}

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
		return $this->storage->get( $text, $provider, $source, $target );
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
		return $this->storage->set( $text, $provider, $translation, $source, $target );
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
		return $this->storage->generate_key( $text, $provider, $source, $target );
	}

	/**
	 * Clear translation cache.
	 *
	 * @since 0.4.0
	 *
	 * @param string|null $provider Provider slug to clear, or null to clear all.
	 * @return bool True if cleared, false otherwise.
	 */
	public function clear( ?string $provider = null ): bool {
		$result = $this->invalidator->clear( $provider );
		if ( $result ) {
			$this->stats->reset_stats();
		}
		return $result;
	}

	/**
	 * Maybe invalidate cache when post is saved.
	 *
	 * @since 0.4.1
	 * @since 0.10.0 Improved granular invalidation based on post type and content changes.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function maybe_invalidate_on_post_save( $post_id ) {
		$this->invalidator->maybe_invalidate_on_post_save( $post_id );
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
		$this->invalidator->maybe_invalidate_on_post_delete( $post_id );
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
		$this->invalidator->maybe_invalidate_on_term_edit( $term_id, $tt_id, $taxonomy );
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
		$this->invalidator->maybe_invalidate_on_term_delete( $term_id, $tt_id, $taxonomy );
	}

	/**
	 * Warm cache for frequently accessed translations.
	 *
	 * @since 0.10.0
	 *
	 * @param array  $texts  Array of texts to warm cache for.
	 * @param string $source Source language. Default 'it'.
	 * @param string $target Target language. Default 'en'.
	 *
	 * @return int Number of items cached.
	 */
	public function warm_cache( $texts, $source = 'it', $target = 'en' ) {
		return $this->warmer->warm_cache( $texts, $source, $target );
	}

	/**
	 * Get cache statistics.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public function get_stats() {
		return $this->stats->get_stats();
	}

	/**
	 * Reset statistics.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function reset_stats() {
		$this->stats->reset_stats();
	}

	/**
	 * Get approximate cache size (transients only).
	 *
	 * @since 0.4.0
	 *
	 * @return int Size in bytes.
	 */
	public function get_cache_size() {
		return $this->info->get_cache_size();
	}

	/**
	 * Get number of cached items.
	 *
	 * @since 0.4.0
	 *
	 * @return int
	 */
	public function get_cache_count() {
		return $this->info->get_cache_count();
	}
}
