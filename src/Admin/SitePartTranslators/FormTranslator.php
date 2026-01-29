<?php
/**
 * Site Part Translators - Form Translator - Handles form translation.
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
 * Handles form translation (labels and placeholders).
 *
 * @since 0.10.0
 */
class FormTranslator {
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
	 * Traduce i form (labels e placeholders).
	 *
	 * @return array Risultato della traduzione.
	 */
	public function translate(): array {
		$translated_count = 0;

		// Contact Form 7
		if ( class_exists( 'WPCF7' ) ) {
			$forms = get_posts( array(
				'post_type' => 'wpcf7_contact_form',
				'posts_per_page' => -1,
			) );

			foreach ( $forms as $form ) {
				$form_content = get_post_meta( $form->ID, '_form', true );

				if ( $form_content ) {
					// Estrai labels
					preg_match_all( '/\[label[^\]]*\](.*?)\[\/label\]/i', $form_content, $label_matches );
					if ( ! empty( $label_matches[1] ) ) {
						foreach ( $label_matches[1] as $label ) {
							$translated = $this->text_translator->translate_text( $label );
							if ( $translated ) {
								update_option( '_fpml_en_cf7_label_' . md5( $label ), $translated );
								$translated_count++;
							}
						}
					}

					// Estrai placeholders
					preg_match_all( '/placeholder=["\']([^"\']+)["\']/i', $form_content, $placeholder_matches );
					if ( ! empty( $placeholder_matches[1] ) ) {
						foreach ( $placeholder_matches[1] as $placeholder ) {
							$translated = $this->text_translator->translate_text( $placeholder );
							if ( $translated ) {
								update_option( '_fpml_en_cf7_placeholder_' . md5( $placeholder ), $translated );
								$translated_count++;
							}
						}
					}
				}
			}
		}

		// WPForms
		if ( class_exists( 'WPForms' ) ) {
			$forms = get_posts( array(
				'post_type' => 'wpforms',
				'posts_per_page' => -1,
			) );

			foreach ( $forms as $form ) {
				$form_data = get_post_meta( $form->ID, 'wpforms_fields', true );

				if ( is_array( $form_data ) ) {
					foreach ( $form_data as $field ) {
						// Label
						if ( ! empty( $field['label'] ) ) {
							$translated = $this->text_translator->translate_text( $field['label'] );
							if ( $translated ) {
								update_option( '_fpml_en_wpforms_label_' . md5( $field['label'] ), $translated );
								$translated_count++;
							}
						}

						// Placeholder
						if ( ! empty( $field['placeholder'] ) ) {
							$translated = $this->text_translator->translate_text( $field['placeholder'] );
							if ( $translated ) {
								update_option( '_fpml_en_wpforms_placeholder_' . md5( $field['placeholder'] ), $translated );
								$translated_count++;
							}
						}
					}
				}
			}
		}

		return array(
			'message' => sprintf(
				/* translators: %d: number of translated form elements */
				__( '%d elementi form tradotti.', 'fp-multilanguage' ),
				$translated_count
			),
			'count' => $translated_count,
		);
	}
}
















