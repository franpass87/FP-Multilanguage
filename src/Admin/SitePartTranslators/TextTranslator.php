<?php
/**
 * Site Part Translators - Text Translator - Helper for translating text.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Admin\SitePartTranslators;

use FP\Multilanguage\Core\ContainerAwareTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class for translating text using the translation provider.
 *
 * @since 0.10.0
 */
class TextTranslator {
	use ContainerAwareTrait;
	/**
	 * Traduce un testo usando il translator direttamente.
	 *
	 * @param string $text Testo da tradurre.
	 * @return string|false Testo tradotto o false in caso di errore.
	 */
	public function translate_text( $text, $source = '', $target = '' ) {
		if ( empty( $text ) ) {
			return false;
		}

		$container = $this->getContainer();
		$settings  = $container && $container->has( 'options' ) ? $container->get( 'options' ) : ( class_exists( '\FPML_Settings' ) ? \FPML_Settings::instance() : null );

		if ( ! $settings ) {
			return false;
		}

		// Resolve source/target from settings when not explicitly provided.
		if ( empty( $source ) ) {
			$source = $settings->get( 'source_language', 'it' );
		}
		if ( empty( $target ) ) {
			$enabled = $settings->get( 'enabled_languages', array() );
			$target  = ! empty( $enabled ) ? $enabled[0] : 'en';
		}

		$provider_name = $settings->get( 'provider', 'openai' );
		$translator    = null;

		if ( 'openai' === $provider_name ) {
			$translator = \FP\Multilanguage\Providers\ProviderOpenAI::instance();
		} else {
			// Attempt to retrieve the translator via the processor's public method.
			$processor = function_exists( 'fpml_get_processor' ) ? fpml_get_processor() : null;
			if ( $processor && method_exists( $processor, 'get_translator_instance' ) ) {
				$translator = $processor->get_translator_instance();
				if ( is_wp_error( $translator ) ) {
					$translator = null;
				}
			}
		}

		if ( ! $translator || ! method_exists( $translator, 'translate' ) ) {
			return false;
		}

		$translated = $translator->translate( $text, $source, $target, 'general' );

		if ( is_wp_error( $translated ) || empty( $translated ) ) {
			return false;
		}

		return is_string( $translated ) ? $translated : false;
	}
}
















