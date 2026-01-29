<?php
/**
 * Site Part Translators - Theme Options Translator - Handles theme options translation.
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
 * Handles theme options translation.
 *
 * @since 0.10.0
 */
class ThemeOptionsTranslator {
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
	 * Traduce le opzioni del tema Salient.
	 *
	 * @return array Risultato della traduzione.
	 */
	public function translate(): array {
		$translated_count = 0;

		// Verifica se Salient è attivo
		if ( ! function_exists( 'get_nectar_theme_options' ) ) {
			return array(
				'message' => __( 'Tema Salient non rilevato. Le opzioni del tema verranno tradotte automaticamente quando disponibili.', 'fp-multilanguage' ),
				'count' => 0,
			);
		}

		$options = get_option( 'salient' );

		if ( ! is_array( $options ) ) {
			return array(
				'message' => __( 'Nessuna opzione tema trovata.', 'fp-multilanguage' ),
				'count' => 0,
			);
		}

		// Campi da tradurre (esempi comuni)
		$fields_to_translate = array(
			'header_text',
			'footer_text',
			'copyright_text',
			'call_to_action_text',
		);

		foreach ( $fields_to_translate as $field ) {
			if ( ! empty( $options[ $field ] ) && is_string( $options[ $field ] ) ) {
				$translated = $this->text_translator->translate_text( $options[ $field ] );

				if ( $translated ) {
					update_option( '_fpml_en_theme_option_' . $field, $translated );
					$translated_count++;
				}
			}
		}

		return array(
			'message' => sprintf(
				/* translators: %d: number of translated options */
				__( '%d opzioni tema tradotte.', 'fp-multilanguage' ),
				$translated_count
			),
			'count' => $translated_count,
		);
	}
}
















