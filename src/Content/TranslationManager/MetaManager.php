<?php
/**
 * Translation Manager Meta Manager - Manages meta updates without hooks.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Content\TranslationManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages meta updates without hooks.
 *
 * @since 0.10.0
 */
class MetaManager {
	/**
	 * Translation cache instance.
	 *
	 * @var TranslationCache|null
	 */
	protected ?TranslationCache $cache = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Cache will be set via setCache() to avoid circular dependency
	}

	/**
	 * Set cache instance.
	 *
	 * @param TranslationCache $cache Cache instance.
	 *
	 * @return void
	 */
	public function set_cache( TranslationCache $cache ): void {
		$this->cache = $cache;
	}

	/**
	 * Update post meta directly in database without triggering hooks.
	 *
	 * @since 0.10.0
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param string $meta_value Meta value.
	 * @return void
	 */
	public function update_meta_directly( int $post_id, string $meta_key, string $meta_value ): void {
		global $wpdb;
		
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
			$post_id,
			$meta_key
		) );
		
		if ( $existing ) {
			$wpdb->update(
				$wpdb->postmeta,
				array( 'meta_value' => $meta_value ),
				array( 'post_id' => $post_id, 'meta_key' => $meta_key ),
				array( '%s' ),
				array( '%d', '%s' )
			);
		} else {
			$wpdb->insert(
				$wpdb->postmeta,
				array(
					'post_id' => $post_id,
					'meta_key' => $meta_key,
					'meta_value' => $meta_value,
				),
				array( '%d', '%s', '%s' )
			);
		}

		// Clear translation cache if this is a translation pair meta key
		if ( $this->cache && preg_match( '/^_fpml_pair_id(_|$)/', $meta_key ) ) {
			// Extract language from meta key if it's language-specific
			if ( preg_match( '/^_fpml_pair_id_(.+)$/', $meta_key, $matches ) ) {
				$lang = $matches[1];
				$this->cache->clear_translation_id_cache( $post_id, $lang );
			} else {
				// Legacy _fpml_pair_id - clear for 'en'
				$this->cache->clear_translation_id_cache( $post_id, 'en' );
			}
		}
	}
}

