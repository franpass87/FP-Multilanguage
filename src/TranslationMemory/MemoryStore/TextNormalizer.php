<?php
/**
 * Translation Memory Store Text Normalizer - Normalizes text for comparison.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\TranslationMemory\MemoryStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes text for comparison.
 *
 * @since 0.10.0
 */
class TextNormalizer {
	/**
	 * Normalize text for comparison.
	 *
	 * @since 0.10.0
	 *
	 * @param string $text Text to normalize.
	 * @return string Normalized text.
	 */
	public function normalize( string $text ): string {
		// Convert to lowercase
		$normalized = mb_strtolower( $text, 'UTF-8' );

		// Remove extra whitespace
		$normalized = preg_replace( '/\s+/', ' ', $normalized );

		// Trim
		$normalized = trim( $normalized );

		// Remove punctuation for better matching (optional - can be adjusted)
		// $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', '', $normalized);

		return $normalized;
	}
}















