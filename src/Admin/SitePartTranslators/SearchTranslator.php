<?php
/**
 * Site Part Translators - Search Translator - Handles search messages translation.
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
 * Handles search messages translation.
 *
 * @since 0.10.0
 */
class SearchTranslator {
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
	 * Traduce i messaggi di ricerca.
	 *
	 * @return array Risultato della traduzione.
	 */
	public function translate(): array {
		$translated_count = 0;

		$search_messages = array(
			'no_results' => __( 'Nothing Found', 'fp-multilanguage' ),
			'no_results_desc' => __( 'Sorry, but nothing matched your search terms. Please try again with some different keywords.', 'fp-multilanguage' ),
			'results_found' => __( 'Search Results for: %s', 'fp-multilanguage' ),
			'search_results_found' => __( 'Search Results for: %s', 'fp-multilanguage' ),
			'results_count' => __( '%d results found', 'fp-multilanguage' ),
		);

		foreach ( $search_messages as $key => $message ) {
			$translated = $this->text_translator->translate_text( $message );
			if ( $translated ) {
				update_option( '_fpml_en_search_' . $key, $translated );
				$translated_count++;
			}
		}

		return array(
			'message' => sprintf(
				/* translators: %d: number of translated search messages */
				__( '%d messaggi ricerca tradotti.', 'fp-multilanguage' ),
				$translated_count
			),
			'count' => $translated_count,
		);
	}
}
















