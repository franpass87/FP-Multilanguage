<?php
/**
 * Site Part Translators - Site Settings Translator - Handles site settings translation.
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
 * Handles site settings translation.
 *
 * @since 0.10.0
 */
class SiteSettingsTranslator {
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
	 * Traduce le impostazioni generali del sito (Site Title, Tagline).
	 *
	 * @return array Risultato della traduzione.
	 */
	public function translate(): array {
		$translated_count = 0;

		// Site title
		$site_title = get_option( 'blogname' );
		if ( $site_title ) {
			$translated = $this->text_translator->translate_text( $site_title );
			if ( $translated ) {
				update_option( '_fpml_en_option_blogname', $translated );
				$translated_count++;
			}
		}

		// Tagline
		$tagline = get_option( 'blogdescription' );
		if ( $tagline ) {
			$translated = $this->text_translator->translate_text( $tagline );
			if ( $translated ) {
				update_option( '_fpml_en_option_blogdescription', $translated );
				$translated_count++;
			}
		}

		return array(
			'message' => sprintf(
				/* translators: %d: number of translated settings */
				__( '%d impostazioni sito tradotte.', 'fp-multilanguage' ),
				$translated_count
			),
			'count' => $translated_count,
		);
	}
}
















