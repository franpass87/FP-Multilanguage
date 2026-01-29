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
	public function translate_text( $text ) {
		if ( empty( $text ) ) {
			return false;
		}

		// Ottieni il translator dal Processor
		$processor = fpml_get_processor();

		if ( ! $processor ) {
			return false;
		}

		// Usa reflection per accedere al translator (metodo privato)
		// Alternativa: usa direttamente il provider
		$container = $this->getContainer();
		$settings = $container && $container->has( 'options' ) ? $container->get( 'options' ) : \FPML_Settings::instance();
		$provider_name = $settings->get( 'provider', 'openai' );

		// Carica il provider direttamente
		if ( 'openai' === $provider_name ) {
			$translator = \FP\Multilanguage\Providers\ProviderOpenAI::instance();
		} else {
			// Fallback: prova a ottenere il translator dal processor via reflection
			$reflection = new \ReflectionClass( $processor );
			$translator_property = $reflection->getProperty( 'translator' );
			$translator_property->setAccessible( true );
			$translator = $translator_property->getValue( $processor );
		}

		if ( ! $translator || ! method_exists( $translator, 'translate' ) ) {
			return false;
		}

		// Traduci il testo
		$translated = $translator->translate( $text, 'it', 'en', 'general' );

		if ( is_wp_error( $translated ) || empty( $translated ) ) {
			return false;
		}

		return is_string( $translated ) ? $translated : false;
	}
}
















