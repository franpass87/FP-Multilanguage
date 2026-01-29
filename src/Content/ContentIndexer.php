<?php
/**
 * Content Indexer - Reindexes existing content for translation.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 * @since 0.10.0 Refactored to use modular components.
 */


namespace FP\Multilanguage\Content;

use FP\Multilanguage\Core\Container;
use FP\Multilanguage\Content\Indexer\BatchIndexer;
use FP\Multilanguage\Content\Indexer\PostTypeIndexer;
use FP\Multilanguage\Content\Indexer\TaxonomyIndexer;
use FP\Multilanguage\Content\Indexer\TimeoutManager;
use FP\Multilanguage\Content\Indexer\PostTypeHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles reindexing of posts, terms and menus.
 *
 * @since 0.4.0
 * @since 0.10.0 Refactored to use modular components.
 */
class ContentIndexer {
	/**
	 * Singleton instance (for backward compatibility).
	 *
	 * @var \FPML_Content_Indexer|null
	 * @deprecated 1.0.0 Use dependency injection instead
	 */
	protected static $instance = null;

	/**
	 * Translation manager.
	 *
	 * @var \FPML_Translation_Manager
	 */
	protected $translation_manager;

	/**
	 * Job enqueuer.
	 *
	 * @var \FPML_Job_Enqueuer
	 */
	protected $job_enqueuer;

	/**
	 * Batch indexer instance.
	 *
	 * @since 0.10.0
	 *
	 * @var BatchIndexer
	 */
	protected $batch_indexer;

	/**
	 * Post type indexer instance.
	 *
	 * @since 0.10.0
	 *
	 * @var PostTypeIndexer
	 */
	protected $post_type_indexer;

	/**
	 * Taxonomy indexer instance.
	 *
	 * @since 0.10.0
	 *
	 * @var TaxonomyIndexer
	 */
	protected $taxonomy_indexer;

	/**
	 * Timeout manager instance.
	 *
	 * @since 0.10.0
	 *
	 * @var TimeoutManager
	 */
	protected $timeout_manager;

	/**
	 * Post type helper instance.
	 *
	 * @since 0.10.0
	 *
	 * @var PostTypeHelper
	 */
	protected $post_type_helper;

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 * @since 1.0.0 Now public to support dependency injection
	 *
	 * @param \FPML_Translation_Manager|null $translation_manager Optional translation manager for DI.
	 * @param \FPML_Job_Enqueuer|null        $job_enqueuer        Optional job enqueuer for DI.
	 */
	public function __construct( $translation_manager = null, $job_enqueuer = null ) {
		// Use injected dependencies or get from container/singleton
		if ( null === $translation_manager ) {
			$this->translation_manager = Container::get( 'translation_manager' ) ?: \FPML_Translation_Manager::instance();
		} else {
			$this->translation_manager = $translation_manager;
		}
		
		if ( null === $job_enqueuer ) {
			$this->job_enqueuer = Container::get( 'job_enqueuer' ) ?: \FPML_Job_Enqueuer::instance();
		} else {
			$this->job_enqueuer = $job_enqueuer;
		}

		// Initialize modules
		$this->timeout_manager     = new TimeoutManager();
		$this->post_type_helper    = new PostTypeHelper();
		$this->post_type_indexer   = new PostTypeIndexer( $this->translation_manager, $this->job_enqueuer, $this->timeout_manager );
		$this->taxonomy_indexer    = new TaxonomyIndexer( $this->translation_manager, $this->job_enqueuer );
		$this->batch_indexer      = new BatchIndexer( $this->post_type_indexer, $this->taxonomy_indexer, $this->post_type_helper );
	}

	/**
	 * Retrieve singleton instance (for backward compatibility).
	 *
	 * @since 0.4.0
	 * @deprecated 1.0.0 Use dependency injection via container instead
	 *
	 * @return \FPML_Content_Indexer
	 */
	public static function instance() {
		_doing_it_wrong( 
			'FP\Multilanguage\Content\ContentIndexer::instance()', 
			'ContentIndexer::instance() is deprecated. Use dependency injection via container instead.', 
			'1.0.0' 
		);
		
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Esegue il reindex incrementale per step, permettendo la visualizzazione del progresso.
	 *
	 * @since 0.4.3
	 *
	 * @param int $step Numero dello step corrente (0 = inizio).
	 *
	 * @return array {
	 *     Informazioni sullo stato del reindex.
	 *
	 *     @type bool   $complete         Se il reindex è completato.
	 *     @type int    $step             Step corrente.
	 *     @type int    $total_steps      Totale degli step.
	 *     @type int    $progress_percent Percentuale di completamento (0-100).
	 *     @type string $current_task     Descrizione del task corrente.
	 *     @type array  $summary          Riepilogo cumulativo dei risultati.
	 * }
	 */
	public function reindex_batch( $step = 0 ) {
		return $this->batch_indexer->reindex_batch( $step );
	}

	/**
	 * Reindex existing content to ensure queue coverage and translations.
	 *
	 * @since 0.4.0
	 *
	 * @param array $post_types Optional. Specific post types to reindex.
	 *
	 * @return array Summary data.
	 */
	public function reindex_content( $post_types = array() ) {
		// Previeni timeout su grandi dataset - estende il tempo di esecuzione periodicamente
		$start_time = time();
		
		$summary = array(
			'posts_scanned'        => 0,
			'posts_enqueued'       => 0,
			'translations_created' => 0,
			'terms_scanned'        => 0,
			'menus_synced'         => 0,
		);

		if ( empty( $post_types ) ) {
			$post_types = $this->post_type_helper->get_translatable_post_types();
		}

		foreach ( $post_types as $post_type ) {
			// Estendi il timeout ogni post type per evitare scadenze
			$this->timeout_manager->maybe_extend_timeout( $start_time );
			
			$result = $this->post_type_indexer->reindex_post_type( $post_type );
			$summary['posts_scanned']        += $result['posts_scanned'];
			$summary['posts_enqueued']       += $result['posts_enqueued'];
			$summary['translations_created'] += $result['translations_created'];
		}

		$taxonomies = get_taxonomies(
			array(
				'public' => true,
			),
			'names'
		);

		$taxonomies = apply_filters( '\FPML_translatable_taxonomies', $taxonomies );

		foreach ( $taxonomies as $taxonomy ) {
			// Estendi il timeout ogni tassonomia
			$this->timeout_manager->maybe_extend_timeout( $start_time );
			
			$result = $this->taxonomy_indexer->reindex_taxonomy( $taxonomy );
			$summary['terms_scanned'] += $result['terms_scanned'];
		}

		// Estendi il timeout prima della sincronizzazione menu
		$this->timeout_manager->maybe_extend_timeout( $start_time );

		$menu_sync = function_exists( 'fpml_get_menu_sync' ) ? fpml_get_menu_sync() : ( function_exists( 'fpml_get_menu_sync' ) ? fpml_get_menu_sync() : \FPML_Menu_Sync::instance() );

		if ( $menu_sync instanceof \FPML_Menu_Sync ) {
			$summary['menus_synced'] = $menu_sync->resync_all();
		}

		/**
		 * Allow filtering of the reindex summary before returning.
		 *
		 * @since 0.2.0
		 *
		 * @param array $summary Summary data.
		 */
		return apply_filters( '\FPML_reindex_summary', $summary );
	}

	/**
	 * Estende il timeout di esecuzione PHP se necessario.
	 *
	 * @since 0.4.3
	 *
	 * @param int $start_time Timestamp di inizio dell'operazione.
	 *
	 * @return void
	 */
	protected function maybe_extend_timeout( $start_time ) {
		$this->timeout_manager->maybe_extend_timeout( $start_time );
	}

	/**
	 * Reindex specific post type.
	 *
	 * @since 0.4.0
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return array Summary.
	 */
	public function reindex_post_type( $post_type ) {
		return $this->post_type_indexer->reindex_post_type( $post_type );
	}

	/**
	 * Reindex specific taxonomy.
	 *
	 * @since 0.4.0
	 *
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return array Summary.
	 */
	public function reindex_taxonomy( $taxonomy ) {
		return $this->taxonomy_indexer->reindex_taxonomy( $taxonomy );
	}

	/**
	 * Retrieve allowed post types for translation.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	protected function get_translatable_post_types() {
		return $this->post_type_helper->get_translatable_post_types();
	}
}
