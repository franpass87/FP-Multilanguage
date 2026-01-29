<?php
/**
 * Site Part Translators - Author Translator - Handles author bio translation.
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
 * Handles author bio translation.
 *
 * @since 0.10.0
 */
class AuthorTranslator {
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
	 * Traduce le biografie degli autori.
	 *
	 * @return array Risultato della traduzione.
	 */
	public function translate(): array {
		$translated_count = 0;

		$authors = get_users( array(
			'who' => 'authors',
			'has_published_posts' => true,
		) );

		foreach ( $authors as $author ) {
			$bio = get_user_meta( $author->ID, 'description', true );

			if ( ! empty( $bio ) ) {
				$translated = $this->text_translator->translate_text( $bio );
				if ( $translated ) {
					update_user_meta( $author->ID, '_fpml_en_bio', $translated );
					$translated_count++;
				}
			}
		}

		return array(
			'message' => sprintf(
				/* translators: %d: number of translated author bios */
				__( '%d biografie autori tradotte.', 'fp-multilanguage' ),
				$translated_count
			),
			'count' => $translated_count,
		);
	}
}
















