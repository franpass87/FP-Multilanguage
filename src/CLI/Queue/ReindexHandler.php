<?php
/**
 * CLI Reindex Handler - Handles content reindexing.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\CLI\Queue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles content reindexing.
 *
 * @since 0.10.0
 */
class ReindexHandler {
	/**
	 * Reindex posts, terms and menus ensuring queue coverage.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function reindex(): void {
		$plugin = function_exists( 'fpml_get_plugin' ) ? fpml_get_plugin() : \FPML_Plugin::instance();
		$summary = $plugin->reindex_content();

		if ( is_wp_error( $summary ) ) {
			\WP_CLI::error( $summary->get_error_message() );
		}

		\WP_CLI::success(
			sprintf(
				/* translators: 1: posts scanned, 2: translations created, 3: posts enqueued, 4: terms scanned, 5: menus synced */
				__( 'Reindex completato: %1$d post analizzati (%2$d nuove traduzioni, %3$d enqueued), %4$d termini e %5$d menu sincronizzati.', 'fp-multilanguage' ),
				isset( $summary['posts_scanned'] ) ? (int) $summary['posts_scanned'] : 0,
				isset( $summary['translations_created'] ) ? (int) $summary['translations_created'] : 0,
				isset( $summary['posts_enqueued'] ) ? (int) $summary['posts_enqueued'] : 0,
				isset( $summary['terms_scanned'] ) ? (int) $summary['terms_scanned'] : 0,
				isset( $summary['menus_synced'] ) ? (int) $summary['menus_synced'] : 0
			)
		);
	}
}
















