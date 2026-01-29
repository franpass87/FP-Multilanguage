<?php
/**
 * Translation Service Interface.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Domain\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for translation services.
 *
 * @since 1.0.0
 */
interface TranslationServiceInterface {
	/**
	 * Translate content from source language to target language.
	 *
	 * @param string $content        Content to translate.
	 * @param string $source_language Source language code.
	 * @param string $target_language Target language code.
	 * @param array  $context        Additional context.
	 * @return string Translated content.
	 */
	public function translate( string $content, string $source_language, string $target_language, array $context = array() ): string;

	/**
	 * Check if translation service is available.
	 *
	 * @return bool
	 */
	public function isAvailable(): bool;
}














