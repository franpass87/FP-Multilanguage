<?php
/**
 * Site Translations Widget Filter - Handles widget translations.
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
 * Handles widget translations.
 *
 * @since 0.10.0
 */
class WidgetFilter {
	/**
	 * Filter widget title.
	 *
	 * @param string $title    Original title.
	 * @param array  $instance Widget instance.
	 * @param string $id_base Widget ID base.
	 * @return string Translated title.
	 */
	public function filter_widget_title( $title, $instance, $id_base ) {
		if ( empty( $title ) ) {
			return $title;
		}

		// Find translation for this widget
		global $wp_registered_widgets;

		foreach ( $wp_registered_widgets as $widget_id => $widget ) {
			if ( $widget['callback'][0]->id_base === $id_base ) {
				$translated_title = get_option( '_fpml_en_widget_' . $widget_id . '_title' );

				if ( $translated_title ) {
					return $translated_title;
				}
			}
		}

		return $title;
	}

	/**
	 * Filter widget text.
	 *
	 * @param string $text     Original text.
	 * @param array  $instance Widget instance.
	 * @param object $widget   Widget object.
	 * @return string Translated text.
	 */
	public function filter_widget_text( $text, $instance, $widget ) {
		if ( empty( $text ) ) {
			return $text;
		}

		// Find translation for this widget
		global $wp_registered_widgets;

		foreach ( $wp_registered_widgets as $widget_id => $registered_widget ) {
			if ( isset( $registered_widget['callback'][0] ) &&
				 $registered_widget['callback'][0] === $widget ) {
				$translated_text = get_option( '_fpml_en_widget_' . $widget_id . '_text' );

				if ( $translated_text ) {
					return $translated_text;
				}
			}
		}

		return $text;
	}
}
















