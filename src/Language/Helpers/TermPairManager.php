<?php
/**
 * Term Pair Manager - Manages term translation pairs.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.10.0
 */

namespace FP\Multilanguage\Language\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages term translation pairs (source <-> target mappings).
 *
 * @since 0.10.0
 */
class TermPairManager {
	/**
	 * Cached term pairs map.
	 *
	 * @var array|null
	 */
	protected $term_pairs = null;

	/**
	 * Retrieve cached term pairs from storage.
	 *
	 * @since 0.3.0
	 *
	 * @return array
	 */
	public function get_term_pairs() {
		if ( null !== $this->term_pairs ) {
			return $this->term_pairs;
		}

		$stored = get_option( '\FPML_term_pairs', array() );
		$pairs  = array(
			'source_to_target' => array(),
			'target_to_source' => array(),
		);

		if ( is_array( $stored ) ) {
			if ( isset( $stored['source_to_target'] ) && is_array( $stored['source_to_target'] ) ) {
				foreach ( $stored['source_to_target'] as $source_id => $target_id ) {
					$source = absint( $source_id );
					$target = absint( $target_id );

					if ( $source && $target ) {
						$pairs['source_to_target'][ $source ] = $target;
						$pairs['target_to_source'][ $target ] = $source;
					}
				}
			}

			if ( isset( $stored['target_to_source'] ) && is_array( $stored['target_to_source'] ) ) {
				foreach ( $stored['target_to_source'] as $target_id => $source_id ) {
					$target = absint( $target_id );
					$source = absint( $source_id );

					if ( $target && $source ) {
						$pairs['target_to_source'][ $target ] = $source;

						if ( ! isset( $pairs['source_to_target'][ $source ] ) ) {
							$pairs['source_to_target'][ $source ] = $target;
						}
					}
				}
			}
		}

		$this->term_pairs = $pairs;

		return $this->term_pairs;
	}

	/**
	 * Retrieve the translated term ID for a given source term.
	 *
	 * @since 0.3.0
	 *
	 * @param int $source_id Source term ID.
	 *
	 * @return int
	 */
	public function get_term_translation_id( $source_id ) {
		$source_id = absint( $source_id );

		if ( ! $source_id ) {
			return 0;
		}

		// Check cache first
		$cache_key = '\FPML_term_trans_' . $source_id;
		$cached = wp_cache_get( $cache_key, '\FPML_terms' );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$pairs = $this->get_term_pairs();
		$result = isset( $pairs['source_to_target'][ $source_id ] ) ? (int) $pairs['source_to_target'][ $source_id ] : 0;

		// Cache for 1 hour
		wp_cache_set( $cache_key, $result, '\FPML_terms', HOUR_IN_SECONDS );

		return $result;
	}

	/**
	 * Retrieve the source term ID for a translated term.
	 *
	 * @since 0.3.0
	 *
	 * @param int $target_id Target term ID.
	 *
	 * @return int
	 */
	public function get_term_source_id( $target_id ) {
		$target_id = absint( $target_id );

		if ( ! $target_id ) {
			return 0;
		}

		// Check cache first
		$cache_key = '\FPML_term_source_' . $target_id;
		$cached = wp_cache_get( $cache_key, '\FPML_terms' );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$pairs = $this->get_term_pairs();
		$result = isset( $pairs['target_to_source'][ $target_id ] ) ? (int) $pairs['target_to_source'][ $target_id ] : 0;

		// Cache for 1 hour
		wp_cache_set( $cache_key, $result, '\FPML_terms', HOUR_IN_SECONDS );

		return $result;
	}

	/**
	 * Persist the mapping between source and translated terms.
	 *
	 * @since 0.3.0
	 *
	 * @param int $source_id Source term ID.
	 * @param int $target_id Target term ID.
	 *
	 * @return bool
	 */
	public function set_term_pair( $source_id, $target_id ) {
		$source_id = absint( $source_id );
		$target_id = absint( $target_id );

		if ( ! $source_id || ! $target_id ) {
			return false;
		}

		$pairs       = $this->get_term_pairs();
		$source_map  = $pairs['source_to_target'];
		$target_map  = $pairs['target_to_source'];
		$has_changed = false;

		if ( isset( $source_map[ $source_id ] ) ) {
			if ( (int) $source_map[ $source_id ] !== $target_id ) {
				$previous_target = (int) $source_map[ $source_id ];
				unset( $target_map[ $previous_target ] );
				$source_map[ $source_id ] = $target_id;
				$has_changed              = true;
			}
		} else {
			$source_map[ $source_id ] = $target_id;
			$has_changed              = true;
		}

		if ( isset( $target_map[ $target_id ] ) ) {
			if ( (int) $target_map[ $target_id ] !== $source_id ) {
				$previous_source = (int) $target_map[ $target_id ];
				unset( $source_map[ $previous_source ] );
				$target_map[ $target_id ] = $source_id;
				$has_changed              = true;
			}
		} else {
			$target_map[ $target_id ] = $source_id;
			$has_changed              = true;
		}

		if ( ! $has_changed ) {
			return true;
		}

		$this->term_pairs = array(
			'source_to_target' => $source_map,
			'target_to_source' => $target_map,
		);

		update_option( '\FPML_term_pairs', $this->term_pairs, false );

		// Invalidate cache
		wp_cache_delete( '\FPML_term_trans_' . $source_id, '\FPML_terms' );
		wp_cache_delete( '\FPML_term_source_' . $target_id, '\FPML_terms' );

		return true;
	}
}















