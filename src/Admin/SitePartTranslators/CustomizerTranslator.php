<?php
/**
 * Site Part Translators - Customizer Translator - Handles customizer options translation.
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
 * Handles customizer options translation.
 *
 * @since 0.10.0
 */
class CustomizerTranslator {
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
	 * Traduce le opzioni del customizer.
	 *
	 * @return array Risultato della traduzione.
	 */
	public function translate(): array {
		$translated_count = 0;

		// Ottieni tutte le theme mods
		$theme_mods = get_theme_mods();

		if ( is_array( $theme_mods ) ) {
			foreach ( $theme_mods as $name => $value ) {
				// Solo stringhe traducibili
				if ( is_string( $value ) && ! empty( $value ) && strlen( $value ) > 3 ) {
					// Salta valori che sembrano URL o percorsi
					$is_url = preg_match( '#^(https?://|/|#[a-f0-9]{3,6}$)#i', $value );

					// Check for regex errors
					if ( preg_last_error() !== PREG_NO_ERROR ) {
						\FP\Multilanguage\Logger::warning(
							'Regex error in CustomizerTranslator',
							array(
								'error'   => preg_last_error(),
								'pattern' => '#^(https?://|/|#[a-f0-9]{3,6}$)#i',
							)
						);
						$is_url = false; // Safe fallback
					}

					if ( $is_url ) {
						continue;
					}

					$translated = $this->text_translator->translate_text( $value );
					if ( $translated ) {
						update_option( '_fpml_en_theme_mod_' . $name, $translated );
						$translated_count++;
					}
				}
			}
		}

		return array(
			'message' => sprintf(
				/* translators: %d: number of translated customizer options */
				__( '%d opzioni customizer tradotte.', 'fp-multilanguage' ),
				$translated_count
			),
			'count' => $translated_count,
		);
	}
}
















