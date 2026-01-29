<?php
/**
 * Content Type Service.
 *
 * Manages translatable content types (post types, taxonomies).
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Core\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for managing translatable content types.
 *
 * @since 1.0.0
 */
class ContentTypeService {

	/**
	 * Get translatable post types.
	 *
	 * @return array<string> Post type slugs.
	 */
	public function getTranslatablePostTypes(): array {
		if ( class_exists( '\FP\Multilanguage\Core\PostHandlers' ) ) {
			$post_handlers = function_exists( 'fpml_get_post_handlers' ) ? fpml_get_post_handlers() : \FP\Multilanguage\Core\PostHandlers::instance();
			if ( method_exists( $post_handlers, 'get_translatable_post_types' ) ) {
				return $post_handlers->get_translatable_post_types();
			}
		}
		return array();
	}

	/**
	 * Get translatable taxonomies.
	 *
	 * @return array<string> Taxonomy slugs.
	 */
	public function getTranslatableTaxonomies(): array {
		// Default translatable taxonomies
		$default_taxonomies = array( 'category', 'post_tag' );

		// Get custom taxonomies
		$custom_taxonomies = get_taxonomies(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'names'
		);

		return array_merge( $default_taxonomies, $custom_taxonomies );
	}

	/**
	 * Check if a post type is translatable.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	public function isTranslatablePostType( string $post_type ): bool {
		$translatable_types = $this->getTranslatablePostTypes();
		return in_array( $post_type, $translatable_types, true );
	}

	/**
	 * Check if a taxonomy is translatable.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return bool
	 */
	public function isTranslatableTaxonomy( string $taxonomy ): bool {
		$translatable_taxonomies = $this->getTranslatableTaxonomies();
		return in_array( $taxonomy, $translatable_taxonomies, true );
	}

	/**
	 * Check if content type is translatable.
	 *
	 * @param string $type Content type (post type or taxonomy).
	 * @param string $kind Kind of content ('post_type' or 'taxonomy').
	 * @return bool
	 */
	public function isTranslatable( string $type, string $kind = 'post_type' ): bool {
		if ( 'post_type' === $kind ) {
			return $this->isTranslatablePostType( $type );
		} elseif ( 'taxonomy' === $kind ) {
			return $this->isTranslatableTaxonomy( $type );
		}
		return false;
	}
}








