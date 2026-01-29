<?php
/**
 * Polylang Migrator Pair Creator - Creates translation pairs.
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
 * Creates translation pairs.
 *
 * @since 0.10.0
 */
class PairCreator {
	/**
	 * Create translation pair for posts.
	 *
	 * @since 0.10.0
	 *
	 * @param int    $source_post_id Source post ID.
	 * @param int    $target_post_id Target post ID.
	 * @param string $source_lang    Source language.
	 * @param string $target_lang    Target language.
	 * @return void
	 */
	public function create_translation_pair( int $source_post_id, int $target_post_id, string $source_lang, string $target_lang ): void {
		// Use TranslationManager to create translation pair
		// Set meta to link posts
		update_post_meta( $source_post_id, "_fpml_pair_id_{$target_lang}", $target_post_id );
		update_post_meta( $target_post_id, '_fpml_pair_source_id', $source_post_id );
		update_post_meta( $target_post_id, '_fpml_is_translation', '1' );
		update_post_meta( $target_post_id, '_fpml_target_language', $target_lang );

		// Backward compatibility for 'en'
		if ( 'en' === $target_lang ) {
			update_post_meta( $source_post_id, '_fpml_pair_id', $target_post_id );
		}
	}

	/**
	 * Create translation pair for terms.
	 *
	 * @since 0.10.0
	 *
	 * @param int    $source_term_id Source term ID.
	 * @param int    $target_term_id Target term ID.
	 * @param string $taxonomy       Taxonomy name.
	 * @param string $source_lang    Source language.
	 * @param string $target_lang    Target language.
	 * @return void
	 */
	public function create_term_translation_pair( int $source_term_id, int $target_term_id, string $taxonomy, string $source_lang, string $target_lang ): void {
		// Set meta to link terms
		update_term_meta( $source_term_id, "_fpml_pair_id_{$target_lang}", $target_term_id );
		update_term_meta( $target_term_id, '_fpml_pair_source_id', $source_term_id );
		update_term_meta( $target_term_id, '_fpml_is_translation', 1 );
		update_term_meta( $target_term_id, '_fpml_target_language', $target_lang );

		// Backward compatibility for 'en'
		if ( 'en' === $target_lang ) {
			update_term_meta( $source_term_id, '_fpml_pair_id', $target_term_id );
		}
	}
}















