<?php
/**
 * Translation Manager Translation Cache - Manages translation ID caching.
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
 * Manages translation ID caching.
 *
 * @since 0.10.0
 */
class TranslationCache {
	/**
	 * Meta manager instance.
	 *
	 * @var MetaManager
	 */
	protected MetaManager $meta_manager;

	/**
	 * Constructor.
	 *
	 * @param MetaManager $meta_manager Meta manager instance.
	 */
	public function __construct( MetaManager $meta_manager ) {
		$this->meta_manager = $meta_manager;
	}

	/**
	 * Get translation ID for a specific language.
	 *
	 * @since 0.10.0
	 *
	 * @param int    $post_id Source post ID.
	 * @param string $target_lang Target language code.
	 * @return int|false Translation post ID, or false if not found.
	 */
	public function get_translation_id( int $post_id, string $target_lang = 'en' ): int|false {
		// Validate input
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return false;
		}

		// Validate target language
		$language_manager = fpml_get_language_manager();
		$available_languages = array_keys( $language_manager->get_all_languages() );
		if ( ! in_array( $target_lang, $available_languages, true ) ) {
			return false;
		}

		// Cache key for this lookup
		$cache_key = 'translation_id_' . $post_id . '_' . $target_lang;
		$cache_group = 'fpml_translations';

		// Try cache first
		$cached = wp_cache_get( $cache_key, $cache_group );
		if ( false !== $cached ) {
			// Return false as int 0 if not found, or the ID
			return ( 0 === $cached ) ? false : (int) $cached;
		}

		// Try language-specific meta key first
		$meta_key = '_fpml_pair_id_' . $target_lang;
		$translation_id = (int) get_post_meta( $post_id, $meta_key, true );

		// Backward compatibility: if 'en' and no language-specific meta, check legacy _fpml_pair_id
		if ( ! $translation_id && 'en' === $target_lang ) {
			$translation_id = (int) get_post_meta( $post_id, '_fpml_pair_id', true );
			// If found, migrate it to the new format
			if ( $translation_id ) {
				$this->meta_manager->update_meta_directly( $post_id, '_fpml_pair_id_en', (string) $translation_id );
				$this->meta_manager->update_meta_directly( $translation_id, '_fpml_target_language', 'en' );
			}
		}

		// Cache result (store 0 for false to distinguish from not cached)
		$result = $translation_id ? $translation_id : 0;
		wp_cache_set( $cache_key, $result, $cache_group, 5 * MINUTE_IN_SECONDS );

		return $translation_id ? $translation_id : false;
	}

	/**
	 * Get all translations for a post.
	 *
	 * @since 0.10.0
	 *
	 * @param int $post_id Source post ID.
	 * @return array Array of [language_code => translation_id] pairs.
	 */
	public function get_all_translations( int $post_id ): array {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return array();
		}

		// Cache key
		$cache_key = 'all_translations_' . $post_id;
		$cache_group = 'fpml_translations';

		// Try cache first
		$cached = wp_cache_get( $cache_key, $cache_group );
		if ( false !== $cached ) {
			return (array) $cached;
		}

		$language_manager = fpml_get_language_manager();
		$available_languages = array_keys( $language_manager->get_all_languages() );
		$translations = array();

		foreach ( $available_languages as $lang ) {
			$translation_id = $this->get_translation_id( $post_id, $lang );
			if ( $translation_id ) {
				$translations[ $lang ] = $translation_id;
			}
		}

		// Cache result
		wp_cache_set( $cache_key, $translations, $cache_group, 5 * MINUTE_IN_SECONDS );

		return $translations;
	}

	/**
	 * Clear translation ID cache for a specific post and language.
	 *
	 * @since 0.10.0
	 *
	 * @param int    $post_id Source post ID.
	 * @param string $target_lang Target language code.
	 * @return void
	 */
	public function clear_translation_id_cache( int $post_id, string $target_lang ): void {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		$cache_group = 'fpml_translations';

		// Clear specific translation ID cache
		$cache_key = 'translation_id_' . $post_id . '_' . $target_lang;
		wp_cache_delete( $cache_key, $cache_group );

		// Clear all translations cache for this post
		$all_cache_key = 'all_translations_' . $post_id;
		wp_cache_delete( $all_cache_key, $cache_group );
	}
}
















