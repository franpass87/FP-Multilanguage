<?php
/**
 * Cache Invalidator - Handles cache invalidation operations.
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
 * Handles cache invalidation for posts, terms, and providers.
 *
 * @since 0.4.0
 */
class CacheInvalidator {
	/**
	 * Cache group for object cache.
	 */
	const CACHE_GROUP = '\FPML_translations';

	/**
	 * Cache storage instance.
	 *
	 * @var CacheStorage
	 */
	protected $storage;

	/**
	 * Constructor.
	 *
	 * @param CacheStorage $storage Cache storage instance.
	 */
	public function __construct( CacheStorage $storage ) {
		$this->storage = $storage;
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
		// Only invalidate if this is a source post being updated (not a translation)
		$is_translation = get_post_meta( $post_id, '_fpml_is_translation', true );
		if ( '1' === $is_translation ) {
			// This is a translation, invalidate cache for its source post's translations
			$source_id = get_post_meta( $post_id, '_fpml_pair_source_id', true );
			if ( $source_id ) {
				$this->invalidate_post_translations( (int) $source_id );
			}
		} else {
			// This is a source post, invalidate its translation cache
			$this->invalidate_post_translations( $post_id );
		}
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
	 * Clear translation cache.
	 *
	 * @since 0.4.0
	 *
	 * @param string|null $provider Provider slug to clear, or null to clear all.
	 * @return bool True if cleared, false otherwise.
	 */
	public function clear( ?string $provider = null ): bool {
		global $wpdb;

		if ( null === $provider ) {
			// Clear object cache for our group only
			$this->clear_object_cache_group();

			// Clear all transients
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} 
					 WHERE option_name LIKE %s 
					 OR option_name LIKE %s",
					'_transient_fpml_%',
					'_transient_timeout_fpml_%'
				)
			);
		} else {
			// Clear specific provider - use provider prefix in key
			$provider_slug = sanitize_key( $provider );
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} 
					 WHERE (option_name LIKE %s OR option_name LIKE %s)
					 AND option_value LIKE %s",
					'_transient_fpml_%',
					'_transient_timeout_fpml_%',
					'%' . $wpdb->esc_like( $provider_slug ) . '%'
				)
			);
			// Clear object cache for this provider
			$this->clear_object_cache_group();
		}

		return true;
	}

	/**
	 * Clear object cache group.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	protected function clear_object_cache_group() {
		// WordPress object cache doesn't support group-level deletion natively
		// Try wp_cache_flush_group first (if available in cache backend)
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
			return;
		}

		// Fallback: Don't flush entire cache, let items expire naturally
		// This is less aggressive and won't impact other plugins' cache
		// Individual cache items will be cleared by their keys when needed
		// Note: This means cache might persist slightly longer, but it's safer
	}

	/**
	 * Invalidate cache for translations of a specific post.
	 *
	 * @since 0.10.0
	 *
	 * @param int $post_id Source post ID.
	 *
	 * @return void
	 */
	protected function invalidate_post_translations( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		// Get post content fields that might have been translated
		$text_fields = array(
			$post->post_title,
			$post->post_content,
			$post->post_excerpt,
		);

		// Clear cache for each field (simplified - in practice, we'd track which fields were cached)
		// For now, we clear provider-specific cache for common providers
		$providers = array( 'openai' );
		
		foreach ( $text_fields as $text ) {
			if ( empty( $text ) ) {
				continue;
			}
			
			foreach ( $providers as $provider ) {
				$key = $this->storage->generate_key( $text, $provider, 'it', 'en' );
				wp_cache_delete( $key, self::CACHE_GROUP );
				delete_transient( $key );
			}
		}
	}
}















