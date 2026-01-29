<?php
/**
 * Polylang Migrator Term Migrator - Migrates terms from Polylang.
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
 * Migrates terms from Polylang.
 *
 * @since 0.10.0
 */
class TermMigrator {
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
	 * Migrate terms from Polylang.
	 *
	 * @since 0.10.0
	 *
	 * @param array $language_map Language mapping.
	 * @param array $options      Migration options.
	 * @return array Migration result.
	 */
	public function migrate_terms( array $language_map, array $options ): array {
		global $wpdb;

		$migrated = 0;
		$errors = array();

		// Check if Polylang function exists
		if ( ! function_exists( 'pll_get_term_translations' ) ) {
			return array(
				'migrated' => 0,
				'errors'   => array( __( 'Funzione Polylang pll_get_term_translations non disponibile.', 'fp-multilanguage' ) ),
			);
		}

		// Get all terms with Polylang translations
		$terms = $wpdb->get_results(
			"SELECT DISTINCT t.term_id, tt.taxonomy
			FROM {$wpdb->terms} t
			INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			INNER JOIN {$wpdb->term_relationships} tr2 ON tt.term_taxonomy_id = tr2.term_taxonomy_id
			INNER JOIN {$wpdb->term_taxonomy} tt2 ON tr2.object_id = tt2.term_taxonomy_id
			WHERE tt2.taxonomy = 'language'
			LIMIT " . (int) $options['batch_size']
		);

		foreach ( $terms as $term ) {
			try {
				// Get Polylang translations
				$polylang_translations = pll_get_term_translations( $term->term_id );

				if ( empty( $polylang_translations ) || count( $polylang_translations ) < 2 ) {
					continue; // Skip if no translations
				}

				// Find Italian source term
				$source_term_id = null;
				$source_lang = 'it';

				foreach ( $polylang_translations as $lang_code => $term_id ) {
					if ( $lang_code === 'it' || strpos( $lang_code, 'it' ) === 0 ) {
						$source_term_id = $term_id;
						$source_lang = 'it';
						break;
					}
				}

				if ( ! $source_term_id ) {
					$source_term_id = reset( $polylang_translations );
					$source_lang = key( $polylang_translations );
				}

				// Migrate each translation pair
				foreach ( $polylang_translations as $lang_code => $target_term_id ) {
					if ( $target_term_id == $source_term_id ) {
						continue;
					}

					$target_lang = isset( $language_map[ $lang_code ] ) 
						? $language_map[ $lang_code ] 
						: $this->language_mapper->normalize_language_code( $lang_code );

					if ( $target_lang === $source_lang ) {
						continue;
					}

					// Create FP-Multilanguage translation relationship
					if ( ! $options['dry_run'] ) {
						$this->pair_creator->create_term_translation_pair( $source_term_id, $target_term_id, $term->taxonomy, $source_lang, $target_lang );
					}

					$migrated++;
				}

			} catch ( \Exception $e ) {
				$errors[] = sprintf(
					__( 'Errore migrazione termine %d: %s', 'fp-multilanguage' ),
					$term->term_id,
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















