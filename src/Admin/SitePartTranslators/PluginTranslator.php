<?php
/**
 * Site Part Translators - Plugin Translator - Handles plugin strings translation.
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
 * Handles plugin strings translation.
 *
 * @since 0.10.0
 */
class PluginTranslator {
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
	 * Traduce stringhe di plugin comuni.
	 *
	 * @return array Risultato della traduzione.
	 */
	public function translate(): array {
		$translated_count = 0;

		// WooCommerce
		if ( class_exists( 'WooCommerce' ) ) {
			$woo_strings = array(
				'woocommerce_shop_page_title' => get_option( 'woocommerce_shop_page_title' ),
				'woocommerce_cart_page_title' => get_option( 'woocommerce_cart_page_title' ),
				'woocommerce_checkout_page_title' => get_option( 'woocommerce_checkout_page_title' ),
			);

			foreach ( $woo_strings as $key => $value ) {
				if ( ! empty( $value ) ) {
					$translated = $this->text_translator->translate_text( $value );

					if ( $translated ) {
						update_option( '_fpml_en_' . $key, $translated );
						$translated_count++;
					}
				}
			}
		}

		// Contact Form 7
		if ( class_exists( 'WPCF7' ) ) {
			$forms = get_posts( array(
				'post_type' => 'wpcf7_contact_form',
				'posts_per_page' => -1,
			) );

			foreach ( $forms as $form ) {
				// Traduci titolo del form
				$translated_title = $this->text_translator->translate_text( $form->post_title );

				if ( $translated_title ) {
					update_option( '_fpml_en_cf7_form_' . $form->ID . '_title', $translated_title );
					$translated_count++;
				}
			}
		}

		return array(
			'message' => sprintf(
				/* translators: %d: number of translated plugin strings */
				__( '%d stringhe plugin tradotte.', 'fp-multilanguage' ),
				$translated_count
			),
			'count' => $translated_count,
		);
	}
}
















