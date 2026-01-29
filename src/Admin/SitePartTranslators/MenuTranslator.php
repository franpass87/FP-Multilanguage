<?php
/**
 * Site Part Translators - Menu Translator - Handles menu translation.
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
 * Handles menu translation.
 *
 * @since 0.10.0
 */
class MenuTranslator {
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
	 * Traduce tutti i menu del sito.
	 *
	 * @return array Risultato della traduzione.
	 */
	public function translate(): array {
		$menus = wp_get_nav_menus();
		$translated_count = 0;

		foreach ( $menus as $menu ) {
			$items = wp_get_nav_menu_items( $menu->term_id );

			if ( empty( $items ) ) {
				continue;
			}

			foreach ( $items as $item ) {
				// Salva traduzione del titolo del menu
				$translated_title = $this->text_translator->translate_text( $item->title );

				if ( $translated_title ) {
					// Salva come opzione con prefisso _fpml_en_
					update_option( '_fpml_en_menu_item_' . $item->ID . '_title', $translated_title );
					$translated_count++;
				}
			}
		}

		return array(
			'message' => sprintf(
				/* translators: %d: number of translated items */
				__( '%d elementi menu tradotti.', 'fp-multilanguage' ),
				$translated_count
			),
			'count' => $translated_count,
		);
	}
}
















