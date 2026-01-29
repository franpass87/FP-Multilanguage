<?php
/**
 * Auto Detection Storage - Manages detection state storage.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\AutoDetection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages detection state storage.
 *
 * @since 0.10.0
 */
class DetectionStorage {
	/**
	 * Opzione per memorizzare i post types rilevati.
	 */
	const OPTION_DETECTED_POST_TYPES = '\FPML_detected_post_types';

	/**
	 * Opzione per memorizzare le tassonomie rilevate.
	 */
	const OPTION_DETECTED_TAXONOMIES = '\FPML_detected_taxonomies';

	/**
	 * Opzione per memorizzare i post types ignorati dall'utente.
	 */
	const OPTION_IGNORED_POST_TYPES = '\FPML_ignored_post_types';

	/**
	 * Opzione per memorizzare le tassonomie ignorate dall'utente.
	 */
	const OPTION_IGNORED_TAXONOMIES = '\FPML_ignored_taxonomies';

	/**
	 * Get detected post types.
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	public function get_detected_post_types(): array {
		return get_option( self::OPTION_DETECTED_POST_TYPES, array() );
	}

	/**
	 * Update detected post types.
	 *
	 * @since 0.10.0
	 *
	 * @param array $detected Detected post types.
	 *
	 * @return void
	 */
	public function update_detected_post_types( array $detected ): void {
		update_option( self::OPTION_DETECTED_POST_TYPES, $detected, false );
	}

	/**
	 * Get detected taxonomies.
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	public function get_detected_taxonomies(): array {
		return get_option( self::OPTION_DETECTED_TAXONOMIES, array() );
	}

	/**
	 * Update detected taxonomies.
	 *
	 * @since 0.10.0
	 *
	 * @param array $detected Detected taxonomies.
	 *
	 * @return void
	 */
	public function update_detected_taxonomies( array $detected ): void {
		update_option( self::OPTION_DETECTED_TAXONOMIES, $detected, false );
	}

	/**
	 * Get ignored post types.
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	public function get_ignored_post_types(): array {
		return get_option( self::OPTION_IGNORED_POST_TYPES, array() );
	}

	/**
	 * Add ignored post type.
	 *
	 * @since 0.10.0
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return void
	 */
	public function add_ignored_post_type( string $post_type ): void {
		$ignored = $this->get_ignored_post_types();
		if ( ! in_array( $post_type, $ignored, true ) ) {
			$ignored[] = $post_type;
			update_option( self::OPTION_IGNORED_POST_TYPES, $ignored, false );
		}
	}

	/**
	 * Get ignored taxonomies.
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	public function get_ignored_taxonomies(): array {
		return get_option( self::OPTION_IGNORED_TAXONOMIES, array() );
	}

	/**
	 * Add ignored taxonomy.
	 *
	 * @since 0.10.0
	 *
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return void
	 */
	public function add_ignored_taxonomy( string $taxonomy ): void {
		$ignored = $this->get_ignored_taxonomies();
		if ( ! in_array( $taxonomy, $ignored, true ) ) {
			$ignored[] = $taxonomy;
			update_option( self::OPTION_IGNORED_TAXONOMIES, $ignored, false );
		}
	}
}
















