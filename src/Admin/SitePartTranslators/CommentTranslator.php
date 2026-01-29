<?php
/**
 * Site Part Translators - Comment Translator - Handles comment translation.
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
 * Handles comment translation.
 *
 * @since 0.10.0
 */
class CommentTranslator {
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
	 * Traduce i commenti.
	 *
	 * @return array Risultato della traduzione.
	 */
	public function translate(): array {
		$translated_count = 0;

		// Ottieni tutti i commenti approvati
		$comments = get_comments( array(
			'status' => 'approve',
			'number' => 0, // Tutti i commenti
		) );

		foreach ( $comments as $comment ) {
			if ( ! empty( $comment->comment_content ) ) {
				$translated = $this->text_translator->translate_text( $comment->comment_content );
				if ( $translated ) {
					update_comment_meta( $comment->comment_ID, '_fpml_en_content', $translated );
					$translated_count++;
				}
			}
		}

		return array(
			'message' => sprintf(
				/* translators: %d: number of translated comments */
				__( '%d commenti tradotti.', 'fp-multilanguage' ),
				$translated_count
			),
			'count' => $translated_count,
		);
	}
}
















