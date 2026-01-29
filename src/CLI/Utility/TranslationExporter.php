<?php
/**
 * CLI Translation Exporter - Exports translations to JSON file.
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
 * Exports translations to JSON file.
 *
 * @since 0.10.0
 */
class TranslationExporter {
	/**
	 * Export translations to JSON file.
	 *
	 * @since 0.10.0
	 *
	 * @param string|null $output_file    Output file path. Default: translations-export-{timestamp}.json.
	 * @param string|null $post_type      Filter by post type. Default: all.
	 * @param bool        $include_content Include post content in export. Default: false.
	 *
	 * @return void
	 */
	public function export_translations( ?string $output_file = null, ?string $post_type = null, bool $include_content = false ): void {
		if ( ! $output_file ) {
			$output_file = 'translations-export-' . current_time( 'Y-m-d-His' ) . '.json';
		}

		\WP_CLI::line( __( 'Esportazione traduzioni...', 'fp-multilanguage' ) );

		$manager = fpml_get_translation_manager();
		$export_data = array(
			'version' => \FPML_PLUGIN_VERSION,
			'export_date' => current_time( 'mysql' ),
			'translations' => array(),
		);

		$post_types = $post_type ? array( $post_type ) : get_post_types( array( 'public' => true ) );

		foreach ( $post_types as $type ) {
			$query = new \WP_Query( array(
				'post_type' => $type,
				'posts_per_page' => -1,
				'fields' => 'ids',
				'post_status' => 'any',
			) );

			foreach ( $query->posts as $post_id ) {
				$translations = $manager->get_all_translations( $post_id );

				if ( ! empty( $translations ) ) {
					$post_data = array(
						'post_id' => $post_id,
						'post_type' => $type,
						'translations' => $translations,
					);

					if ( $include_content ) {
						$post = get_post( $post_id );
						if ( $post ) {
							$post_data['source_title'] = $post->post_title;
							$post_data['source_content'] = $post->post_content;
						}
					}

					$export_data['translations'][] = $post_data;
				}
			}
		}

		$json = wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		$written = file_put_contents( $output_file, $json );

		if ( false === $written ) {
			\WP_CLI::error( sprintf( __( 'Impossibile scrivere file: %s', 'fp-multilanguage' ), $output_file ) );
		}

		\WP_CLI::success( sprintf( __( 'Esportati %d post con traduzioni in: %s', 'fp-multilanguage' ), count( $export_data['translations'] ), $output_file ) );
	}
}
















