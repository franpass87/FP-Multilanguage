<?php
/**
 * Polylang Migrator Post Migrator - Migrates posts from Polylang.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Migration\Polylang;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrates posts from Polylang.
 *
 * @since 0.10.0
 */
class PostMigrator {
	/**
	 * Language mapper instance.
	 *
	 * @var LanguageMapper
	 */
	protected LanguageMapper $language_mapper;

	/**
	 * Pair creator instance.
	 *
	 * @var PairCreator
	 */
	protected PairCreator $pair_creator;

	/**
	 * Constructor.
	 *
	 * @param LanguageMapper $language_mapper Language mapper instance.
	 * @param PairCreator     $pair_creator   Pair creator instance.
	 */
	public function __construct( LanguageMapper $language_mapper, PairCreator $pair_creator ) {
		$this->language_mapper = $language_mapper;
		$this->pair_creator = $pair_creator;
	}

	/**
	 * Migrate posts from Polylang.
	 *
	 * @since 0.10.0
	 *
	 * @param array $language_map Language mapping.
	 * @param array $options      Migration options.
	 * @return array Migration result.
	 */
	public function migrate_posts( array $language_map, array $options ): array {
		global $wpdb;

		$migrated = 0;
		$errors = array();

		// Check if Polylang function exists
		if ( ! function_exists( 'pll_get_post_translations' ) ) {
			return array(
				'migrated' => 0,
				'errors'   => array( __( 'Funzione Polylang pll_get_post_translations non disponibile.', 'fp-multilanguage' ) ),
			);
		}

		// Get all posts with Polylang translations
		$posts = $wpdb->get_results(
			"SELECT DISTINCT p.ID, p.post_type
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			WHERE tt.taxonomy = 'language'
			AND p.post_status = 'publish'
			LIMIT " . (int) $options['batch_size']
		);

		foreach ( $posts as $post ) {
			try {
				// Get Polylang translations
				$polylang_translations = pll_get_post_translations( $post->ID );

				if ( empty( $polylang_translations ) || count( $polylang_translations ) < 2 ) {
					continue; // Skip if no translations
				}

				// Find Italian source post (or first available)
				$source_post_id = null;
				$source_lang = 'it';

				foreach ( $polylang_translations as $lang_code => $post_id ) {
					if ( $lang_code === 'it' || strpos( $lang_code, 'it' ) === 0 ) {
						$source_post_id = $post_id;
						$source_lang = 'it';
						break;
					}
				}

				if ( ! $source_post_id ) {
					// Use first post as source
					$source_post_id = reset( $polylang_translations );
					$source_lang = key( $polylang_translations );
				}

				// Migrate each translation pair
				foreach ( $polylang_translations as $lang_code => $target_post_id ) {
					if ( $target_post_id == $source_post_id ) {
						continue; // Skip source post
					}

					// Map language code
					$target_lang = isset( $language_map[ $lang_code ] ) 
						? $language_map[ $lang_code ] 
						: $this->language_mapper->normalize_language_code( $lang_code );

					if ( $target_lang === $source_lang ) {
						continue; // Skip same language
					}

					// Create FP-Multilanguage translation relationship
					if ( ! $options['dry_run'] ) {
						$this->pair_creator->create_translation_pair( $source_post_id, $target_post_id, $source_lang, $target_lang );
					}

					$migrated++;
				}

			} catch ( \Exception $e ) {
				$errors[] = sprintf(
					__( 'Errore migrazione post %d: %s', 'fp-multilanguage' ),
					$post->ID,
					$e->getMessage()
				);
			}
		}

		return array(
			'migrated' => $migrated,
			'errors'   => $errors,
		);
	}
}















