<?php
/**
 * Site Part Translators - Media Translator - Handles media translation.
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
 * Handles media translation (alt text, captions, descriptions).
 *
 * @since 0.10.0
 */
class MediaTranslator {
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
	 * Traduce media (alt text, captions, descriptions).
	 *
	 * @return array Risultato della traduzione.
	 */
	public function translate(): array {
		$translated_count = 0;

		// Ottieni tutti gli attachment
		$attachments = get_posts( array(
			'post_type' => 'attachment',
			'post_mime_type' => 'image',
			'posts_per_page' => -1,
			'post_status' => 'any',
		) );

		foreach ( $attachments as $attachment ) {
			// Traduci alt text
			$alt_text = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
			if ( $alt_text ) {
				$translated = $this->text_translator->translate_text( $alt_text );
				if ( $translated ) {
					update_post_meta( $attachment->ID, '_fpml_en_alt_text', $translated );
					$translated_count++;
				}
			}

			// Traduci caption (post_excerpt)
			if ( ! empty( $attachment->post_excerpt ) ) {
				$translated = $this->text_translator->translate_text( $attachment->post_excerpt );
				if ( $translated ) {
					update_post_meta( $attachment->ID, '_fpml_en_caption', $translated );
					$translated_count++;
				}
			}

			// Traduci description (post_content)
			if ( ! empty( $attachment->post_content ) ) {
				$translated = $this->text_translator->translate_text( $attachment->post_content );
				if ( $translated ) {
					update_post_meta( $attachment->ID, '_fpml_en_description', $translated );
					$translated_count++;
				}
			}
		}

		return array(
			'message' => sprintf(
				/* translators: %d: number of translated media items */
				__( '%d elementi media tradotti.', 'fp-multilanguage' ),
				$translated_count
			),
			'count' => $translated_count,
		);
	}
}
















