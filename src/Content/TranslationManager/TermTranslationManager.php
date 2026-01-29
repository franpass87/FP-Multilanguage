<?php
/**
 * Translation Manager Term Translation Manager - Manages term translations.
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
 * Manages term translations.
 *
 * @since 0.10.0
 */
class TermTranslationManager {
	/**
	 * Logger instance.
	 *
	 * @var \FP\Multilanguage\Logger
	 */
	protected $logger;

	/**
	 * Creating translation flag.
	 *
	 * @var bool
	 */
	protected bool $creating_translation = false;

	/**
	 * Constructor.
	 *
	 * @param \FP\Multilanguage\Logger $logger Logger instance.
	 */
	public function __construct( $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Ensure a term has a translation.
	 *
	 * @since 0.10.0
	 *
	 * @param int    $term_id Term ID.
	 * @param string $taxonomy Taxonomy name.
	 * @param string $target_lang Target language code. Default 'en'.
	 * @return \WP_Term|false Translated term object, or false on failure.
	 */
	public function ensure_term_translation( int $term_id, string $taxonomy, string $target_lang = 'en' ): \WP_Term|false {
		// Validate target language
		$language_manager = fpml_get_language_manager();
		$available_languages = array_keys( $language_manager->get_all_languages() );
		if ( ! in_array( $target_lang, $available_languages, true ) ) {
			return false;
		}

		// Try language-specific meta key
		$meta_key = '_fpml_pair_id_' . $target_lang;
		$target_id = (int) get_term_meta( $term_id, $meta_key, true );

		// Backward compatibility: check legacy _fpml_pair_id for 'en'
		if ( ! $target_id && 'en' === $target_lang ) {
			$target_id = (int) get_term_meta( $term_id, '_fpml_pair_id', true );
			// Migrate if found
			if ( $target_id ) {
				update_term_meta( $term_id, '_fpml_pair_id_en', $target_id );
				update_term_meta( $target_id, '_fpml_target_language', 'en' );
			}
		}

		if ( $target_id ) {
			$target_term = get_term( $target_id, $taxonomy );

			if ( $target_term instanceof \WP_Term && ! is_wp_error( $target_term ) ) {
				update_term_meta( $target_term->term_id, '_fpml_pair_source_id', $term_id );
				update_term_meta( $target_term->term_id, '_fpml_is_translation', 1 );
				update_term_meta( $target_term->term_id, '_fpml_target_language', $target_lang );

				return $target_term->term_id;
			}
		}

		$term = get_term( $term_id, $taxonomy );

		if ( ! $term instanceof \WP_Term || is_wp_error( $term ) ) {
			return false;
		}

		$this->creating_translation = true;

		// Il nome iniziale è lo stesso, verrà tradotto successivamente
		$translation_name = $term->name;
		
		// Lo slug iniziale è basato sullo slug italiano senza prefisso en-
		$base_slug = $term->slug ? $term->slug : sanitize_title( $term->name );
		// Rimuovi eventuali prefissi esistenti
		$base_slug = preg_replace( '/^(it|en)[-_]/i', '', $base_slug );
		// Usa lo slug base senza prefisso (il routing aggiungerà /en/)
		$translation_slug = $base_slug;

		$parent_translation_id = 0;
		if ( $term->parent > 0 ) {
			// Try language-specific meta key
			$parent_meta_key = '_fpml_pair_id_' . $target_lang;
			$parent_translation_id = get_term_meta( $term->parent, $parent_meta_key, true );
			
			// Backward compatibility: check legacy _fpml_pair_id for 'en'
			if ( ! $parent_translation_id && 'en' === $target_lang ) {
				$parent_translation_id = get_term_meta( $term->parent, '_fpml_pair_id', true );
			}
			
			if ( $parent_translation_id ) {
				$parent_translation_id = (int) $parent_translation_id;
			}
		}

		$inserted = \fpml_safe_insert_term(
			$translation_name,
			$taxonomy,
			array(
				'slug'        => $translation_slug,
				'description' => $term->description,
				'parent'      => $parent_translation_id,
			)
		);

		if ( is_wp_error( $inserted ) ) {
			$this->creating_translation = false;
			if ( $this->logger ) {
				$this->logger->log(
					'error',
					sprintf( 'Impossibile creare la traduzione per il termine #%d: %s', $term_id, $inserted->get_error_message() ),
					array(
						'term_id' => $term_id,
					)
				);
			} else {
				error_log( sprintf( 'FPML: Impossibile creare la traduzione per il termine #%d: %s', $term_id, $inserted->get_error_message() ) );
			}

			return false;
		}

		$target_id = (int) $inserted['term_id'];

		update_term_meta( $target_id, '_fpml_is_translation', 1 );
		update_term_meta( $target_id, '_fpml_pair_source_id', $term_id );
		update_term_meta( $target_id, '_fpml_target_language', $target_lang );
		
		// Store translation ID with language-specific meta key
		$meta_key = '_fpml_pair_id_' . $target_lang;
		update_term_meta( $term_id, $meta_key, $target_id );
		
		// Backward compatibility: also set _fpml_pair_id for 'en'
		if ( 'en' === $target_lang ) {
			update_term_meta( $term_id, '_fpml_pair_id', $target_id );
		}

		$this->creating_translation = false;

		return $target_id;
	}
}
















