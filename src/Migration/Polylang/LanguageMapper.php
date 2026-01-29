<?php
/**
 * Polylang Migrator Language Mapper - Maps Polylang languages to FP-Multilanguage.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Migration\Polylang;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps Polylang languages to FP-Multilanguage.
 *
 * @since 0.10.0
 */
class LanguageMapper {
	/**
	 * Map Polylang languages to FP-Multilanguage.
	 *
	 * @since 0.10.0
	 *
	 * @return array Language mapping (polylang_code => fpml_code).
	 */
	public function map_languages(): array {
		global $wpdb;

		$language_map = array();

		// Get Polylang languages
		$polylang_languages = $wpdb->get_results(
			"SELECT term_id, slug FROM {$wpdb->terms} t
			INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			WHERE tt.taxonomy = 'language'"
		);

		if ( empty( $polylang_languages ) ) {
			return array();
		}

		// Map Polylang language codes to FP-Multilanguage
		foreach ( $polylang_languages as $lang ) {
			$polylang_code = $lang->slug;
			
			// Default mapping: Polylang codes are often ISO codes
			// Map common codes (it -> it, en -> en, etc.)
			$fpml_code = $this->normalize_language_code( $polylang_code );
			
			$language_map[ $polylang_code ] = $fpml_code;
		}

		return $language_map;
	}

	/**
	 * Normalize language code from Polylang to FP-Multilanguage format.
	 *
	 * @since 0.10.0
	 *
	 * @param string $code Language code.
	 * @return string Normalized code.
	 */
	public function normalize_language_code( string $code ): string {
		// Common mappings
		$mappings = array(
			'it_IT' => 'it',
			'en_US' => 'en',
			'en_GB' => 'en',
			'fr_FR' => 'fr',
			'es_ES' => 'es',
			'de_DE' => 'de',
		);

		// Check if we have a direct mapping
		if ( isset( $mappings[ $code ] ) ) {
			return $mappings[ $code ];
		}

		// Extract base language code (e.g., 'it' from 'it_IT')
		if ( strpos( $code, '_' ) !== false ) {
			return strtolower( explode( '_', $code )[0] );
		}

		// Return lowercase code
		return strtolower( $code );
	}
}















