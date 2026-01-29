<?php
/**
 * Site Part Translators - Widget Translator - Handles widget translation.
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
 * Handles widget translation.
 *
 * @since 0.10.0
 */
class WidgetTranslator {
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
	 * Traduce tutti i widget attivi.
	 *
	 * @return array Risultato della traduzione.
	 */
	public function translate(): array {
		global $wp_registered_widgets;

		$translated_count = 0;
		$sidebars = wp_get_sidebars_widgets();

		foreach ( $sidebars as $sidebar_id => $widgets ) {
			if ( ! is_array( $widgets ) ) {
				continue;
			}

			foreach ( $widgets as $widget_id ) {
				if ( ! isset( $wp_registered_widgets[ $widget_id ] ) ) {
					continue;
				}

				$widget = $wp_registered_widgets[ $widget_id ];
				$widget_instance = get_option( 'widget_' . $widget['callback'][0]->id_base );

				if ( ! is_array( $widget_instance ) ) {
					continue;
				}

				// Trova l'istanza del widget
				$widget_number = $widget['params'][0]['number'];

				if ( isset( $widget_instance[ $widget_number ] ) ) {
					$instance = $widget_instance[ $widget_number ];

					// Traduci titolo se presente
					if ( ! empty( $instance['title'] ) ) {
						$translated_title = $this->text_translator->translate_text( $instance['title'] );

						if ( $translated_title ) {
							update_option( '_fpml_en_widget_' . $widget_id . '_title', $translated_title );
							$translated_count++;
						}
					}

					// Traduci testo se presente (per widget di testo)
					if ( ! empty( $instance['text'] ) ) {
						$translated_text = $this->text_translator->translate_text( $instance['text'] );

						if ( $translated_text ) {
							update_option( '_fpml_en_widget_' . $widget_id . '_text', $translated_text );
							$translated_count++;
						}
					}
				}
			}
		}

		return array(
			'message' => sprintf(
				/* translators: %d: number of translated widgets */
				__( '%d widget tradotti.', 'fp-multilanguage' ),
				$translated_count
			),
			'count' => $translated_count,
		);
	}
}
















