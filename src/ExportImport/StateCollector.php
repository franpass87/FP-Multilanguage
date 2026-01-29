<?php
/**
 * Export Import State Collector - Collects translation state entries.
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
 * Collects translation state entries.
 *
 * @since 0.10.0
 */
class StateCollector {
	/**
	 * Text cleaner instance.
	 *
	 * @var TextCleaner
	 */
	protected TextCleaner $text_cleaner;

	/**
	 * Constructor.
	 *
	 * @param TextCleaner $text_cleaner Text cleaner instance.
	 */
	public function __construct( TextCleaner $text_cleaner ) {
		$this->text_cleaner = $text_cleaner;
	}

	/**
	 * Retrieve translation state entries for posts, terms and menus.
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	public function get_translation_state_entries(): array {
		$entries = array();

		$entries = array_merge( $entries, $this->collect_post_status_rows() );
		$entries = array_merge( $entries, $this->collect_term_status_rows() );
		$entries = array_merge( $entries, $this->collect_menu_status_rows() );

		return $entries;
	}

	/**
	 * Collect post status rows.
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	protected function collect_post_status_rows(): array {
		$rows = array();

		$args = array(
			'post_type'      => 'any',
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'meta_key'       => '_fpml_is_translation',
			'meta_value'     => 1,
			'fields'         => 'ids',
		);

		$ids = get_posts( $args );

		foreach ( $ids as $translation_id ) {
			$translation_id = (int) $translation_id;
			$post           = get_post( $translation_id );

			if ( ! $post ) {
				continue;
			}

			$meta      = get_post_meta( $translation_id );
			$source_id = (int) get_post_meta( $translation_id, '_fpml_pair_source_id', true );
			$source    = $source_id ? get_permalink( $source_id ) : '';
			$target    = get_permalink( $translation_id );

			foreach ( $meta as $meta_key => $values ) {
				if ( 0 !== strpos( $meta_key, '_fpml_status_' ) ) {
					continue;
				}

				$status = end( $values );
				$status = sanitize_key( $status );
				$field  = substr( $meta_key, 13 );

				$rows[] = array(
					'object_type'      => 'post',
					'object_subtype'   => $post->post_type,
					'source_id'        => $source_id,
					'translation_id'   => $translation_id,
					'field'            => $field,
					'status'           => $status,
					'source_url'       => $source ? esc_url_raw( $source ) : '',
					'translation_url'  => $target ? esc_url_raw( $target ) : '',
					'status_date'      => $this->get_status_timestamp( $meta, $meta_key, get_post_modified_gmt( $translation_id ) ),
					'title'            => $this->text_cleaner->clean_preview_text( get_the_title( $translation_id ) ),
				);
			}
		}

		return $rows;
	}

	/**
	 * Collect term status rows.
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	protected function collect_term_status_rows(): array {
		$rows       = array();
		$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'meta_query' => array(
						array(
							'key'   => '_fpml_is_translation',
							'value' => 1,
						),
					),
				)
			);

			if ( is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$meta      = get_term_meta( $term->term_id );
				$source_id = (int) get_term_meta( $term->term_id, '_fpml_pair_source_id', true );
				$source    = $source_id ? get_term_link( (int) $source_id, $taxonomy ) : '';
				$target    = get_term_link( $term, $taxonomy );

				foreach ( $meta as $meta_key => $values ) {
					if ( 0 !== strpos( $meta_key, '_fpml_status_' ) ) {
						continue;
					}

					$status = end( $values );
					$status = sanitize_key( $status );
					$field  = substr( $meta_key, 13 );

					$rows[] = array(
						'object_type'      => 'term',
						'object_subtype'   => $taxonomy,
						'source_id'        => $source_id,
						'translation_id'   => (int) $term->term_id,
						'field'            => $field,
						'status'           => $status,
						'source_url'       => ! is_wp_error( $source ) ? esc_url_raw( $source ) : '',
						'translation_url'  => ! is_wp_error( $target ) ? esc_url_raw( $target ) : '',
						'status_date'      => $this->get_status_timestamp( $meta, $meta_key, gmdate( 'Y-m-d H:i:s' ) ),
						'title'            => $this->text_cleaner->clean_preview_text( $term->name ),
					);
				}
			}
		}

		return $rows;
	}

	/**
	 * Collect menu status rows.
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	protected function collect_menu_status_rows(): array {
		$rows = array();

		$items = get_posts(
			array(
				'post_type'      => 'nav_menu_item',
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'meta_key'       => '_fpml_is_translation',
				'meta_value'     => 1,
				'fields'         => 'ids',
			)
		);

		foreach ( $items as $item_id ) {
			$item_id    = (int) $item_id;
			$meta       = get_post_meta( $item_id );
			$source_id  = (int) get_post_meta( $item_id, '_fpml_pair_source_id', true );
			$source_url = $source_id ? get_post_meta( $source_id, '_menu_item_url', true ) : '';
			$target_url = get_post_meta( $item_id, '_menu_item_url', true );
			$title      = get_post_meta( $item_id, '_menu_item_title', true );

			foreach ( $meta as $meta_key => $values ) {
				if ( 0 !== strpos( $meta_key, '_fpml_status_' ) ) {
					continue;
				}

				$status = end( $values );
				$status = sanitize_key( $status );
				$field  = substr( $meta_key, 13 );

				$rows[] = array(
					'object_type'      => 'menu',
					'object_subtype'   => 'nav_menu_item',
					'source_id'        => $source_id,
					'translation_id'   => $item_id,
					'field'            => $field,
					'status'           => $status,
					'source_url'       => $source_url ? esc_url_raw( $source_url ) : '',
					'translation_url'  => $target_url ? esc_url_raw( $target_url ) : '',
					'status_date'      => get_post_modified_gmt( $item_id ),
					'title'            => $this->text_cleaner->clean_preview_text( $title ? $title : get_the_title( $item_id ) ),
				);
			}
		}

		return $rows;
	}

	/**
	 * Extract timestamp from meta or fallback.
	 *
	 * @since 0.10.0
	 *
	 * @param array  $meta       Meta array.
	 * @param string $meta_key   Status meta key.
	 * @param string $fallback   Fallback timestamp.
	 *
	 * @return string
	 */
	protected function get_status_timestamp( array $meta, string $meta_key, string $fallback ): string {
		$suffix = $meta_key . '_updated_at';

		if ( isset( $meta[ $suffix ] ) ) {
			$value = end( $meta[ $suffix ] );
			$value = sanitize_text_field( $value );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return $fallback;
	}
}
















