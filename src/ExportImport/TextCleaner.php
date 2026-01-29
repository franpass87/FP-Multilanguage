<?php
/**
 * Export Import Text Cleaner - Cleans preview text.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\ExportImport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cleans preview text.
 *
 * @since 0.10.0
 */
class TextCleaner {
	/**
	 * Clean preview text to avoid leaking HTML.
	 *
	 * @since 0.10.0
	 *
	 * @param string $text Raw text.
	 *
	 * @return string
	 */
	public function clean_preview_text( string $text ): string {
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/\s+/u', ' ', $text );

		return trim( wp_trim_words( $text, 40, '…' ) );
	}
}
















