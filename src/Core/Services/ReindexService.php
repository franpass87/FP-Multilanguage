<?php
/**
 * Reindex Service.
 *
 * Handles content reindexing operations.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Core\Services;

use FP\Multilanguage\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for managing content reindexing.
 *
 * @since 1.0.0
 */
class ReindexService {
	/**
	 * Reindex all content.
	 *
	 * @return array|WP_Error Summary data.
	 */
	public function reindexAll(): array|\WP_Error {
		// Check assisted mode
		$assisted_mode_service = $this->getAssistedModeService();
		if ( $assisted_mode_service && $assisted_mode_service->isActive() ) {
			return new \WP_Error(
				'fpml_assisted_mode',
				__( 'La modalità assistita è attiva: la duplicazione e il reindex automatico sono disabilitati.', 'fp-multilanguage' )
			);
		}

		$indexer = $this->getIndexer();
		if ( ! $indexer ) {
			return new \WP_Error( 'no_indexer', __( 'Content indexer not available.', 'fp-multilanguage' ) );
		}

		if ( method_exists( $indexer, 'reindex_content' ) ) {
			return $indexer->reindex_content();
		}

		return new \WP_Error( 'no_method', __( 'Reindex method not available.', 'fp-multilanguage' ) );
	}

	/**
	 * Reindex specific post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return array Summary.
	 */
	public function reindexPostType( string $post_type ): array {
		// Check assisted mode
		$assisted_mode_service = $this->getAssistedModeService();
		if ( $assisted_mode_service && $assisted_mode_service->isActive() ) {
			return array();
		}

		$indexer = $this->getIndexer();
		if ( ! $indexer || ! method_exists( $indexer, 'reindex_post_type' ) ) {
			return array();
		}

		return $indexer->reindex_post_type( $post_type );
	}

	/**
	 * Reindex specific taxonomy.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return array Summary.
	 */
	public function reindexTaxonomy( string $taxonomy ): array {
		// Check assisted mode
		$assisted_mode_service = $this->getAssistedModeService();
		if ( $assisted_mode_service && $assisted_mode_service->isActive() ) {
			return array();
		}

		$indexer = $this->getIndexer();
		if ( ! $indexer || ! method_exists( $indexer, 'reindex_taxonomy' ) ) {
			return array();
		}

		return $indexer->reindex_taxonomy( $taxonomy );
	}

	/**
	 * Reindex single post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool Success.
	 */
	public function reindexSingle( int $post_id ): bool {
		// Check assisted mode
		$assisted_mode_service = $this->getAssistedModeService();
		if ( $assisted_mode_service && $assisted_mode_service->isActive() ) {
			return false;
		}

		$indexer = $this->getIndexer();
		if ( ! $indexer || ! method_exists( $indexer, 'reindex_post' ) ) {
			return false;
		}

		return $indexer->reindex_post( $post_id );
	}

	/**
	 * Get AssistedModeService instance.
	 *
	 * @return AssistedModeService|null
	 */
	protected function getAssistedModeService(): ?AssistedModeService {
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
			if ( $kernel ) {
				$container = $kernel->getContainer();
				if ( $container && $container->has( 'service.assisted_mode' ) ) {
					return $container->get( 'service.assisted_mode' );
				}
			}
		}
		
		if ( class_exists( '\FP\Multilanguage\Core\Services\AssistedModeService' ) ) {
			return new AssistedModeService();
		}
		
		return null;
	}

	/**
	 * Get ContentIndexer instance.
	 *
	 * @return object|null
	 */
	protected function getIndexer() {
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
			if ( $kernel ) {
				$container = $kernel->getContainer();
				if ( $container && $container->has( 'content.indexer' ) ) {
					return $container->get( 'content.indexer' );
				}
			}
		}
		
		$indexer = Container::get( 'content.indexer' );
		if ( $indexer ) {
			return $indexer;
		}
		
		if ( class_exists( '\FP\Multilanguage\Content\ContentIndexer' ) ) {
			return fpml_get_content_indexer();
		}
		
		return null;
	}
}








