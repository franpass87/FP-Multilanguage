<?php
/**
 * REST Translation Handler - Handles translation-related REST endpoints.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Rest\Handlers;

use FP\Multilanguage\Logger;
use FP\Multilanguage\Processor;
use FP\Multilanguage\Content\TranslationManager;
use FP\Multilanguage\Core\TranslationVersioning;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles translation-related REST endpoints.
 *
 * @since 0.10.0
 */
class TranslationHandler {
	/**
	 * Handle GET /translations endpoint.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_get_translations( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$args = array(
			'post_type'      => $request->get_param( 'post_type' ) ?: 'any',
			'post_status'   => $request->get_param( 'status' ) ?: 'any',
			'posts_per_page' => $request->get_param( 'per_page' ) ?: 20,
			'paged'          => $request->get_param( 'page' ) ?: 1,
			'meta_query'    => array(
				array(
					'key'     => '_fpml_is_translation',
					'compare' => 'EXISTS',
				),
			),
		);

		$query = new \WP_Query( $args );
		$translations = array();

		foreach ( $query->posts as $post ) {
			$source_id = get_post_meta( $post->ID, '_fpml_pair_source_id', true );
			$translations[] = array(
				'id'          => $post->ID,
				'title'       => $post->post_title,
				'type'        => $post->post_type,
				'status'      => $post->post_status,
				'source_id'   => $source_id,
				'trans_status' => get_post_meta( $post->ID, '_fpml_translation_status', true ) ?: 'pending',
			);
		}

		return new \WP_REST_Response( array(
			'translations' => $translations,
			'total'       => $query->found_posts,
			'pages'       => $query->max_num_pages,
		), 200 );
	}

	/**
	 * Handle POST /translations/bulk endpoint.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_bulk_translate_rest( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_ids = $request->get_param( 'post_ids' );
		$action   = $request->get_param( 'action' ); // 'translate', 'regenerate', 'sync'

		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			return new \WP_Error( 'invalid_params', 'post_ids must be a non-empty array', array( 'status' => 400 ) );
		}

		$processor = class_exists( '\FP\Multilanguage\Processor' ) ? fpml_get_processor() : null;
		if ( ! $processor ) {
			return new \WP_Error( 'processor_error', 'Impossibile caricare il processore.', array( 'status' => 500 ) );
		}
		$results   = array();

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			$post    = get_post( $post_id );

			if ( ! $post ) {
				$results[ $post_id ] = array( 'success' => false, 'message' => 'Post not found' );
				continue;
			}

			switch ( $action ) {
				case 'translate':
					$result = $processor->translate_post_directly( $post_id );
					$results[ $post_id ] = array(
						'success' => 'completed' === $result,
						'message' => 'completed' === $result ? 'Translation completed' : 'Translation failed',
					);
					break;

				case 'regenerate':
					// Delete existing translation and retranslate
					// Backward compatibility: check legacy _fpml_pair_id for 'en'
					$translation_id = fpml_get_translation_id( $post_id, 'en' );
					if ( ! $translation_id ) {
						$translation_id = (int) get_post_meta( $post_id, '_fpml_pair_id', true );
					}
					if ( $translation_id ) {
						wp_delete_post( $translation_id, true );
						// Delete new meta keys
						delete_post_meta( $post_id, '_fpml_pair_id_en' );
						// Backward compatibility: also delete legacy
						delete_post_meta( $post_id, '_fpml_pair_id' );
					}
					$result = $processor->translate_post_directly( $post_id );
					$results[ $post_id ] = array(
						'success' => 'completed' === $result,
						'message' => 'completed' === $result ? 'Translation regenerated' : 'Regeneration failed',
					);
					break;

				case 'sync':
					// Backward compatibility: check legacy _fpml_pair_id for 'en'
					$translation_id = fpml_get_translation_id( $post_id, 'en' );
					if ( ! $translation_id ) {
						$translation_id = (int) get_post_meta( $post_id, '_fpml_pair_id', true );
					}
					if ( $translation_id ) {
						$translation_manager = class_exists( '\FP\Multilanguage\Content\TranslationManager' ) ? fpml_get_translation_manager() : null;
						if ( $translation_manager ) {
							$translation_manager->sync_post_translation( $post_id, $translation_id );
						}
						$results[ $post_id ] = array(
							'success' => true,
							'message' => 'Synchronization completed',
						);
					} else {
						$results[ $post_id ] = array(
							'success' => false,
							'message' => 'No translation found',
						);
					}
					break;

				default:
					$results[ $post_id ] = array(
						'success' => false,
						'message' => 'Invalid action',
					);
			}
		}

		return new \WP_REST_Response( array(
			'results' => $results,
			'total'   => count( $post_ids ),
		), 200 );
	}

	/**
	 * Handle POST /translations/{id}/regenerate endpoint.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_regenerate_translation( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error( 'not_found', 'Post not found', array( 'status' => 404 ) );
		}

		// Delete existing translation
		// Backward compatibility: check legacy _fpml_pair_id for 'en'
		$translation_id = fpml_get_translation_id( $post_id, 'en' );
		if ( ! $translation_id ) {
			$translation_id = (int) get_post_meta( $post_id, '_fpml_pair_id', true );
		}
		if ( $translation_id ) {
			wp_delete_post( $translation_id, true );
			// Delete new meta keys
			delete_post_meta( $post_id, '_fpml_pair_id_en' );
			// Backward compatibility: also delete legacy
			delete_post_meta( $post_id, '_fpml_pair_id' );
		}

		// Retranslate
		$processor = class_exists( '\FP\Multilanguage\Processor' ) ? fpml_get_processor() : null;
		if ( ! $processor ) {
			return new \WP_Error( 'processor_error', 'Impossibile caricare il processore.', array( 'status' => 500 ) );
		}
		$result = $processor->translate_post_directly( $post_id );

		if ( 'completed' === $result ) {
			return new \WP_REST_Response( array(
				'success' => true,
				'message' => 'Translation regenerated successfully',
			), 200 );
		} else {
			return new \WP_Error( 'translation_failed', 'Translation failed', array( 'status' => 500 ) );
		}
	}

	/**
	 * Handle GET /translations/{id}/versions endpoint.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_get_versions( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error( 'not_found', 'Post not found', array( 'status' => 404 ) );
		}

		$versioning = new TranslationVersioning();
		$versions   = $versioning->get_versions( $post_id );

		return new \WP_REST_Response( array(
			'versions' => $versions,
		), 200 );
	}

	/**
	 * Handle POST /translations/{id}/rollback endpoint.
	 *
	 * @since 0.10.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_rollback( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post_id  = (int) $request->get_param( 'id' );
		$version  = (int) $request->get_param( 'version' );
		$post     = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error( 'not_found', 'Post not found', array( 'status' => 404 ) );
		}

		$versioning = new TranslationVersioning();
		$result     = $versioning->rollback( $post_id, $version );

		if ( $result ) {
			return new \WP_REST_Response( array(
				'success' => true,
				'message' => 'Rollback completed successfully',
			), 200 );
		} else {
			return new \WP_Error( 'rollback_failed', 'Rollback failed', array( 'status' => 500 ) );
		}
	}
}
















