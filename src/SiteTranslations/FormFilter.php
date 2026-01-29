<?php
/**
 * Site Translations Form Filter - Handles form translations.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\SiteTranslations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles form translations (Contact Form 7, WPForms).
 *
 * @since 0.10.0
 */
class FormFilter {
	/**
	 * Filter Contact Form 7 forms.
	 *
	 * @param string $form Original form HTML.
	 * @return string Filtered form HTML.
	 */
	public function filter_cf7_form( $form ) {
		// Search for labels and placeholders in form HTML
		// Pattern: [label] or placeholder="..."
		preg_match_all( '/\[label[^\]]*\](.*?)\[\/label\]/i', $form, $label_matches );
		preg_match_all( '/placeholder=["\']([^"\']+)["\']/i', $form, $placeholder_matches );

		// Translate labels
		if ( ! empty( $label_matches[1] ) ) {
			foreach ( $label_matches[1] as $label ) {
				$translated = get_option( '_fpml_en_cf7_label_' . md5( $label ) );
				if ( $translated ) {
					$form = str_replace( $label, $translated, $form );
				}
			}
		}

		// Translate placeholders
		if ( ! empty( $placeholder_matches[1] ) ) {
			foreach ( $placeholder_matches[1] as $placeholder ) {
				$translated = get_option( '_fpml_en_cf7_placeholder_' . md5( $placeholder ) );
				if ( $translated ) {
					$form = str_replace( 'placeholder="' . $placeholder . '"', 'placeholder="' . $translated . '"', $form );
					$form = str_replace( "placeholder='" . $placeholder . "'", "placeholder='" . $translated . "'", $form );
				}
			}
		}

		return $form;
	}

	/**
	 * Filter WPForms fields.
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Field.
	 * @param array $form_data  Form data.
	 * @return array Filtered properties.
	 */
	public function filter_wpforms_fields( $properties, $field, $form_data ) {
		// Translate label
		if ( ! empty( $properties['label']['value'] ) ) {
			$label      = $properties['label']['value'];
			$translated = get_option( '_fpml_en_wpforms_label_' . md5( $label ) );
			if ( $translated ) {
				$properties['label']['value'] = $translated;
			}
		}

		// Translate placeholder
		if ( ! empty( $properties['inputs']['primary']['attr']['placeholder'] ) ) {
			$placeholder = $properties['inputs']['primary']['attr']['placeholder'];
			$translated  = get_option( '_fpml_en_wpforms_placeholder_' . md5( $placeholder ) );
			if ( $translated ) {
				$properties['inputs']['primary']['attr']['placeholder'] = $translated;
			}
		}

		return $properties;
	}
}
















