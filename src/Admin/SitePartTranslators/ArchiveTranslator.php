<?php
/**
 * Site Part Translators - Archive Translator - Handles archive translation.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Admin\SitePartTranslators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles archive translation.
 *
 * @since 0.10.0
 */
class ArchiveTranslator {
	/**
	 * Text translator instance.
	 *
	 * @var TextTranslator
	 */
	protected TextTranslator $text_translator;

	/**
	 * Constructor.
	 *
	 * @param TextTranslator $text_translator Text translator instance.
	 */
	public function __construct( TextTranslator $text_translator ) {
		$this->text_translator = $text_translator;
	}

	/**
	 * Traduce i titoli e descrizioni degli archivi.
	 *
	 * @return array Risultato della traduzione.
	 */
	public function translate(): array {
		$translated_count = 0;

		// Categorie
		$categories = get_categories( array( 'hide_empty' => false ) );
		foreach ( $categories as $category ) {
			// Titolo archivio categoria
			$title = sprintf( __( 'Category: %s', 'fp-multilanguage' ), $category->name );
			$translated = $this->text_translator->translate_text( $title );
			if ( $translated ) {
				update_option( '_fpml_en_archive_category_' . $category->term_id . '_title', $translated );
				$translated_count++;
			}

			// Descrizione categoria (se presente)
			if ( ! empty( $category->description ) ) {
				$translated = $this->text_translator->translate_text( $category->description );
				if ( $translated ) {
					update_option( '_fpml_en_archive_category_' . $category->term_id . '_description', $translated );
					$translated_count++;
				}
			}
		}

		// Tag
		$tags = get_tags( array( 'hide_empty' => false ) );
		foreach ( $tags as $tag ) {
			// Titolo archivio tag
			$title = sprintf( __( 'Tag: %s', 'fp-multilanguage' ), $tag->name );
			$translated = $this->text_translator->translate_text( $title );
			if ( $translated ) {
				update_option( '_fpml_en_archive_tag_' . $tag->term_id . '_title', $translated );
				$translated_count++;
			}

			// Descrizione tag (se presente)
			if ( ! empty( $tag->description ) ) {
				$translated = $this->text_translator->translate_text( $tag->description );
				if ( $translated ) {
					update_option( '_fpml_en_archive_tag_' . $tag->term_id . '_description', $translated );
					$translated_count++;
				}
			}
		}

		// Custom Taxonomies
		$taxonomies = get_taxonomies( array( 'public' => true, '_builtin' => false ), 'objects' );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms( array(
				'taxonomy' => $taxonomy->name,
				'hide_empty' => false,
			) );

			foreach ( $terms as $term ) {
				$title = sprintf( __( '%s: %s', 'fp-multilanguage' ), $taxonomy->labels->singular_name, $term->name );
				$translated = $this->text_translator->translate_text( $title );
				if ( $translated ) {
					update_option( '_fpml_en_archive_taxonomy_' . $term->term_id . '_title', $translated );
					$translated_count++;
				}

				if ( ! empty( $term->description ) ) {
					$translated = $this->text_translator->translate_text( $term->description );
					if ( $translated ) {
						update_option( '_fpml_en_archive_taxonomy_' . $term->term_id . '_description', $translated );
						$translated_count++;
					}
				}
			}
		}

		return array(
			'message' => sprintf(
				/* translators: %d: number of translated archives */
				__( '%d archivi tradotti.', 'fp-multilanguage' ),
				$translated_count
			),
			'count' => $translated_count,
		);
	}
}
















