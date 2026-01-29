<?php
/**
 * Site Part Translators - NotFound Translator - Handles 404 page translation.
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
 * Handles 404 page translation.
 *
 * @since 0.10.0
 */
class NotFoundTranslator {
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
	 * Traduce i messaggi delle pagine 404.
	 *
	 * @return array Risultato della traduzione.
	 */
	public function translate(): array {
		$translated_count = 0;

		$messages_404 = array(
			'title' => __( 'Page Not Found', 'fp-multilanguage' ),
			'message' => __( 'It looks like nothing was found at this location. Maybe try a search?', 'fp-multilanguage' ),
			'heading' => __( '404', 'fp-multilanguage' ),
		);

		foreach ( $messages_404 as $key => $message ) {
			$translated = $this->text_translator->translate_text( $message );
			if ( $translated ) {
				update_option( '_fpml_en_404_' . $key, $translated );
				$translated_count++;
			}
		}

		// Verifica se esiste una pagina 404 personalizzata
		// Cerca pagina con slug '404' o 'not-found'
		$page_404 = get_page_by_path( '404' );
		if ( ! $page_404 ) {
			$page_404 = get_page_by_path( 'not-found' );
		}

		if ( $page_404 ) {
			$page = get_post( $page_404 );
			if ( $page ) {
				// Traduci titolo
				$translated = $this->text_translator->translate_text( $page->post_title );
				if ( $translated ) {
					update_option( '_fpml_en_404_page_title', $translated );
					$translated_count++;
				}

				// Traduci contenuto
				if ( ! empty( $page->post_content ) ) {
					$translated = $this->text_translator->translate_text( $page->post_content );
					if ( $translated ) {
						update_option( '_fpml_en_404_page_content', $translated );
						$translated_count++;
					}
				}
			}
		}

		return array(
			'message' => sprintf(
				/* translators: %d: number of translated 404 messages */
				__( '%d messaggi 404 tradotti.', 'fp-multilanguage' ),
				$translated_count
			),
			'count' => $translated_count,
		);
	}
}
















