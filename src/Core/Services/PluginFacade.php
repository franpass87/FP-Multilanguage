<?php
/**
 * Plugin Facade - Provides simplified interface for Plugin operations.
 *
 * Encapsulates delegations to specialized services, reducing Plugin.php complexity.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 1.0.0
 */

namespace FP\Multilanguage\Core\Services;

use FP\Multilanguage\Core\Container;
use FP\Multilanguage\Core\ContainerAwareTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Facade for Plugin operations.
 *
 * Provides simplified interface for common plugin operations,
 * delegating to specialized services.
 *
 * @since 1.0.0
 */
class PluginFacade {
	use ContainerAwareTrait;

	/**
	 * Check if plugin is in assisted mode.
	 *
	 * @return bool
	 */
	public function is_assisted_mode(): bool {
		$reason = $this->detect_external_multilingual();
		return ! empty( $reason );
	}

	/**
	 * Detect active multilingual plugins that require assisted mode.
	 *
	 * @return string Empty string when no external plugin is detected, otherwise the identifier.
	 */
	protected function detect_external_multilingual() {
		if ( defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'icl_object_id' ) ) {
			return 'wpml';
		}

		if ( defined( 'POLYLANG_VERSION' ) || function_exists( 'pll_current_language' ) ) {
			return 'polylang';
		}

		return '';
	}

	/**
	 * Reindex all content.
	 *
	 * @return array|WP_Error Summary data.
	 */
	public function reindex_content() {
		$reindex_service = $this->getReindexService();
		if ( $reindex_service ) {
			return $reindex_service->reindexAll();
		}

		// Fallback to old logic
		if ( $this->is_assisted_mode() ) {
			return new \WP_Error(
				'\FPML_assisted_mode',
				__( 'La modalità assistita è attiva: la duplicazione e il reindex automatico sono disabilitati.', 'fp-multilanguage' )
			);
		}

		$indexer = Container::get( 'content.indexer' );
		if ( ! $indexer && class_exists( '\FP\Multilanguage\Content\ContentIndexer' ) ) {
			$indexer = fpml_get_content_indexer();
		}

		if ( ! $indexer ) {
			return new \WP_Error( 'no_indexer', __( 'Content indexer not available.', 'fp-multilanguage' ) );
		}

		return $indexer->reindex_content();
	}

	/**
	 * Reindex specific post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return array Summary.
	 */
	public function reindex_post_type( $post_type ) {
		$reindex_service = $this->getReindexService();
		if ( $reindex_service ) {
			return $reindex_service->reindexPostType( $post_type );
		}

		// Fallback to old logic
		if ( $this->is_assisted_mode() ) {
			return array();
		}

		$indexer = Container::get( 'content.indexer' );
		if ( ! $indexer && class_exists( '\FP\Multilanguage\Content\ContentIndexer' ) ) {
			$indexer = fpml_get_content_indexer();
		}

		if ( ! $indexer ) {
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
	public function reindex_taxonomy( $taxonomy ) {
		$reindex_service = $this->getReindexService();
		if ( $reindex_service ) {
			return $reindex_service->reindexTaxonomy( $taxonomy );
		}

		// Fallback to old logic
		if ( $this->is_assisted_mode() ) {
			return array();
		}

		$indexer = Container::get( 'content.indexer' );
		if ( ! $indexer && class_exists( '\FP\Multilanguage\Content\ContentIndexer' ) ) {
			$indexer = fpml_get_content_indexer();
		}

		if ( ! $indexer ) {
			return array();
		}

		return $indexer->reindex_taxonomy( $taxonomy );
	}

	/**
	 * Get ReindexService instance.
	 *
	 * @return \FP\Multilanguage\Core\Services\ReindexService|null
	 */
	protected function getReindexService() {
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
			if ( $kernel ) {
				$container = $kernel->getContainer();
				if ( $container && $container->has( 'service.reindex' ) ) {
					return $container->get( 'service.reindex' );
				}
			}
		}
		
		if ( class_exists( '\FP\Multilanguage\Core\Services\ReindexService' ) ) {
			return new \FP\Multilanguage\Core\Services\ReindexService();
		}
		
		return null;
	}

	/**
	 * Get diagnostics snapshot.
	 *
	 * @return array<string,mixed>
	 */
	public function get_diagnostics_snapshot() {
		$diagnostics_service = $this->getDiagnosticsService();
		if ( $diagnostics_service ) {
			return $diagnostics_service->getSnapshot();
		}

		// Fallback to old logic
		$diagnostics = Container::get( 'diagnostics' );
		if ( ! $diagnostics && class_exists( '\FP\Multilanguage\Diagnostics\Diagnostics' ) ) {
			$diagnostics = fpml_get_diagnostics();
		}

		if ( ! $diagnostics ) {
			return array();
		}

		$assisted_mode = $this->is_assisted_mode();
		$assisted_reason = $this->detect_external_multilingual();

		return $diagnostics->get_snapshot( $assisted_mode, $assisted_reason );
	}

	/**
	 * Get DiagnosticsService instance.
	 *
	 * @return \FP\Multilanguage\Core\Services\DiagnosticsService|null
	 */
	protected function getDiagnosticsService() {
		if ( class_exists( '\FP\Multilanguage\Kernel\Plugin' ) ) {
			$kernel = \FP\Multilanguage\Kernel\Plugin::getInstance();
			if ( $kernel ) {
				$container = $kernel->getContainer();
				if ( $container && $container->has( 'service.diagnostics' ) ) {
					return $container->get( 'service.diagnostics' );
				}
			}
		}
		
		if ( class_exists( '\FP\Multilanguage\Core\Services\DiagnosticsService' ) ) {
			return new \FP\Multilanguage\Core\Services\DiagnosticsService();
		}
		
		return null;
	}

	/**
	 * Estimate queue cost.
	 *
	 * @param array<string>|null $states   Queue states to inspect.
	 * @param int                $max_jobs Maximum number of jobs to analyse.
	 * @return array<string,float|int>|WP_Error
	 */
	public function estimate_queue_cost( $states = null, $max_jobs = 500 ) {
		if ( $this->is_assisted_mode() ) {
			return new \WP_Error(
				'\FPML_assisted_mode',
				__( 'La modalità assistita è attiva: la coda è gestita esternamente, nessuna stima disponibile.', 'fp-multilanguage' )
			);
		}

		$cost_estimator = Container::get( 'cost_estimator' );
		if ( ! $cost_estimator && class_exists( '\FP\Multilanguage\Diagnostics\CostEstimator' ) ) {
			$cost_estimator = fpml_get_cost_estimator();
		}

		if ( ! $cost_estimator ) {
			return new \WP_Error( 'no_estimator', __( 'Cost estimator not available.', 'fp-multilanguage' ) );
		}

		return $cost_estimator->estimate( $states, $max_jobs );
	}

	/**
	 * Get queue job text.
	 *
	 * @param object $job Queue job entry.
	 * @return string
	 */
	public function get_queue_job_text( $job ) {
		$cost_estimator = Container::get( 'cost_estimator' );
		if ( ! $cost_estimator && class_exists( '\FP\Multilanguage\Diagnostics\CostEstimator' ) ) {
			$cost_estimator = fpml_get_cost_estimator();
		}

		if ( ! $cost_estimator ) {
			return '';
		}

		return $cost_estimator->get_queue_job_text( $job );
	}

	/**
	 * Get queue cleanup states.
	 *
	 * @return array
	 */
	public function get_queue_cleanup_states() {
		$states = apply_filters( '\FPML_queue_cleanup_states', array( 'done', 'skipped', 'error' ) );
		$states = array_filter( array_map( 'sanitize_key', (array) $states ) );

		return array_values( array_unique( $states ) );
	}

	/**
	 * Get queue age summary.
	 *
	 * @return array
	 */
	public function get_queue_age_summary() {
		if ( $this->is_assisted_mode() ) {
			return array();
		}

		$diagnostics = Container::get( 'diagnostics' );
		if ( ! $diagnostics && class_exists( '\FP\Multilanguage\Diagnostics\Diagnostics' ) ) {
			$diagnostics = fpml_get_diagnostics();
		}

		if ( ! $diagnostics ) {
			return array();
		}

		return $diagnostics->get_queue_age_summary();
	}
}

