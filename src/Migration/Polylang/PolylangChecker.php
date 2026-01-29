<?php
/**
 * Polylang Migrator Polylang Checker - Checks Polylang installation and data.
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
 * Checks Polylang installation and data.
 *
 * @since 0.10.0
 */
class PolylangChecker {
	/**
	 * Check if Polylang is installed.
	 *
	 * @since 0.10.0
	 *
	 * @return bool
	 */
	public function is_polylang_installed(): bool {
		return function_exists( 'pll_get_post_translations' ) ||
		       function_exists( 'PLL' ) ||
		       class_exists( 'Polylang' ) ||
		       defined( 'POLYLANG_VERSION' );
	}

	/**
	 * Check if Polylang data exists in database.
	 *
	 * @since 0.10.0
	 *
	 * @return bool
	 */
	public function has_polylang_data(): bool {
		global $wpdb;

		// Check if Polylang tables exist
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.tables 
				WHERE table_schema = %s 
				AND table_name IN (%s, %s)",
				DB_NAME,
				$wpdb->prefix . 'term_relationships',
				$wpdb->prefix . 'term_taxonomy'
			)
		);

		if ( ! $table_exists ) {
			return false;
		}

		// Check if there are Polylang language relationships
		$polylang_relationships = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			WHERE tt.taxonomy = 'language'"
		);

		return (int) $polylang_relationships > 0;
	}
}















