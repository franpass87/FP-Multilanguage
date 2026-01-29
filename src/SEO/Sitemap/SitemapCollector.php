<?php
/**
 * SEO Sitemap Collector - Collects sitemap entries.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\SEO\Sitemap;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects sitemap entries (posts, terms, home).
 *
 * @since 0.10.0
 */
class SitemapCollector {
	/**
	 * Language helper instance.
	 *
	 * @var \FPML_Language
	 */
	protected $language;

	/**
	 * Sitemap config instance.
	 *
	 * @var SitemapConfig
	 */
	protected SitemapConfig $config;

	/**
	 * Constructor.
	 *
	 * @param \FPML_Language $language Language helper instance.
	 * @param SitemapConfig   $config   Sitemap config instance.
	 */
	public function __construct( $language, SitemapConfig $config ) {
		$this->language = $language;
		$this->config = $config;
	}

	/**
	 * Collect sitemap entries.
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	public function collect_entries(): array {
		$entries = array();

		// Home page
		$home = $this->language->get_url_for_language( \FPML_Language::TARGET );

		if ( $home ) {
			$entries[] = array(
				'loc'     => $home,
				'lastmod' => $this->get_front_page_lastmod(),
			);
		}

		// Posts
		$entries = array_merge( $entries, $this->collect_post_entries() );

		// Terms
		$entries = array_merge( $entries, $this->collect_term_entries() );

		return apply_filters( '\FPML_sitemap_entries', $entries );
	}

	/**
	 * Collect post entries.
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	protected function collect_post_entries(): array {
		$entries = array();
		$post_types = $this->config->get_sitemap_post_types();
		$post_status = array( 'publish' );

		if ( in_array( 'attachment', $post_types, true ) ) {
			$post_status[] = 'inherit';
		}

		if ( empty( $post_types ) ) {
			return $entries;
		}

		$args = array(
			'post_type'      => $post_types,
			'post_status'    => $post_status,
			'posts_per_page' => 200,
			'paged'          => 1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => '_fpml_is_translation',
					'value' => '1',
				),
			),
		);

		do {
			$query = new \WP_Query( $args );

			if ( empty( $query->posts ) ) {
				break;
			}

			foreach ( $query->posts as $post_id ) {
				$post = get_post( (int) $post_id );

				if ( ! $post instanceof \WP_Post ) {
					continue;
				}

				$url = get_permalink( $post );

				if ( ! $url ) {
					continue;
				}

				$entries[] = array(
					'loc'     => $url,
					'lastmod' => (int) get_post_modified_time( 'U', true, $post ),
				);
			}

			$args['paged']++;
		} while ( count( $query->posts ) === $args['posts_per_page'] );

		return $entries;
	}

	/**
	 * Collect term entries.
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	protected function collect_term_entries(): array {
		$entries = array();
		$taxonomies = $this->config->get_sitemap_taxonomies();

		if ( empty( $taxonomies ) ) {
			return $entries;
		}

		$term_query = new \WP_Term_Query(
			array(
				'taxonomy'   => $taxonomies,
				'hide_empty' => false,
				'meta_query' => array(
					array(
						'key'   => '_fpml_is_translation',
						'value' => '1',
					),
				),
			)
		);

		if ( ! is_wp_error( $term_query ) && ! empty( $term_query->terms ) ) {
			foreach ( $term_query->terms as $term ) {
				if ( ! $term instanceof \WP_Term ) {
					continue;
				}

				$link = get_term_link( $term );

				if ( is_wp_error( $link ) || ! $link ) {
					continue;
				}

				$entries[] = array(
					'loc'     => $link,
					'lastmod' => 0,
				);
			}
		}

		return $entries;
	}

	/**
	 * Get front page last modification timestamp.
	 *
	 * @since 0.10.0
	 *
	 * @return int
	 */
	protected function get_front_page_lastmod(): int {
		$front_id = (int) get_option( 'page_on_front' );

		if ( $front_id <= 0 ) {
			return 0;
		}

		$english_id = (int) get_post_meta( $front_id, '_fpml_pair_id', true );

		if ( $english_id > 0 ) {
			$front_id = $english_id;
		}

		$post = get_post( $front_id );

		if ( ! $post instanceof \WP_Post ) {
			return 0;
		}

		return (int) get_post_modified_time( 'U', true, $post );
	}

	/**
	 * Get sitemap last modification timestamp.
	 *
	 * @since 0.10.0
	 *
	 * @return int
	 */
	public function get_sitemap_lastmod_timestamp(): int {
		$post_types = $this->config->get_sitemap_post_types();
		$post_status = array( 'publish' );

		if ( in_array( 'attachment', $post_types, true ) ) {
			$post_status[] = 'inherit';
		}

		$timestamp = 0;

		if ( ! empty( $post_types ) ) {
			$latest = new \WP_Query(
				array(
					'post_type'      => $post_types,
					'post_status'    => $post_status,
					'posts_per_page' => 1,
					'orderby'        => 'modified',
					'order'          => 'DESC',
					'no_found_rows'  => true,
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'   => '_fpml_is_translation',
							'value' => '1',
						),
					),
				)
			);

			if ( ! empty( $latest->posts ) ) {
				$post      = get_post( (int) $latest->posts[0] );
				$timestamp = $post instanceof \WP_Post ? (int) get_post_modified_time( 'U', true, $post ) : 0;
			}
		}

		$front_timestamp = $this->get_front_page_lastmod();

		if ( $front_timestamp > $timestamp ) {
			$timestamp = $front_timestamp;
		}

		if ( $timestamp <= 0 ) {
			$timestamp = time();
		}

		return $timestamp;
	}
}















