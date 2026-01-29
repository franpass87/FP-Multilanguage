<?php
/**
 * CLI Sync Status Checker - Checks synchronization status for posts and terms.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\CLI\Utility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks synchronization status for posts and terms.
 *
 * @since 0.10.0
 */
class SyncStatusChecker {
	/**
	 * Check synchronization status for posts and terms.
	 *
	 * @since 0.10.0
	 *
	 * @param string|null $post_type Filter by post type. Default: all.
	 * @param string|null $taxonomy  Filter by taxonomy. Default: all.
	 *
	 * @return void
	 */
	public function check_status( ?string $post_type = null, ?string $taxonomy = null ): void {
		\WP_CLI::line( __( 'Verifica status sincronizzazione...', 'fp-multilanguage' ) );

		$manager = fpml_get_translation_manager();

		// Check posts
		$post_types = $post_type ? array( $post_type ) : get_post_types( array( 'public' => true ) );
		$stats = array(
			'total' => 0,
			'translated' => 0,
			'pending' => 0,
		);

		foreach ( $post_types as $type ) {
			$query = new \WP_Query( array(
				'post_type' => $type,
				'posts_per_page' => -1,
				'fields' => 'ids',
				'post_status' => 'any',
			) );

			$stats['total'] += $query->found_posts;

			foreach ( $query->posts as $post_id ) {
				$translation_id = $manager->get_translation_id( $post_id, 'en' );
				if ( $translation_id ) {
					$stats['translated']++;
				} else {
					$stats['pending']++;
				}
			}
		}

		\WP_CLI::line( sprintf( __( 'Post totali: %d | Tradotti: %d | Pending: %d', 'fp-multilanguage' ), $stats['total'], $stats['translated'], $stats['pending'] ) );

		// Check terms if taxonomy specified
		if ( $taxonomy ) {
			$terms = get_terms( array(
				'taxonomy' => $taxonomy,
				'hide_empty' => false,
				'fields' => 'ids',
			) );

			if ( ! is_wp_error( $terms ) ) {
				$term_stats = array(
					'total' => 0,
					'translated' => 0,
				);

				foreach ( $terms as $term_id ) {
					$term_stats['total']++;
					// Check if term has translation via meta
					$translation_id = get_term_meta( $term_id, '_fpml_pair_id_en', true );
					if ( ! $translation_id ) {
						// Try legacy meta key
						$translation_id = get_term_meta( $term_id, '_fpml_pair_id', true );
					}
					if ( $translation_id ) {
						$term_stats['translated']++;
					}
				}

				\WP_CLI::line( sprintf( __( 'Termini %s: %d | Tradotti: %d', 'fp-multilanguage' ), $taxonomy, $term_stats['total'], $term_stats['translated'] ) );
			}
		}
	}
}
















