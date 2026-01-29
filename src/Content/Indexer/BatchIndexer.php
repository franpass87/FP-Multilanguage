<?php
/**
 * Batch Indexer - Handles incremental batch reindexing.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.10.0
 */

namespace FP\Multilanguage\Content\Indexer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles incremental batch reindexing for progress tracking.
 *
 * @since 0.10.0
 */
class BatchIndexer {
	/**
	 * Post type indexer instance.
	 *
	 * @var PostTypeIndexer
	 */
	protected $post_type_indexer;

	/**
	 * Taxonomy indexer instance.
	 *
	 * @var TaxonomyIndexer
	 */
	protected $taxonomy_indexer;

	/**
	 * Post type helper instance.
	 *
	 * @var PostTypeHelper
	 */
	protected $post_type_helper;

	/**
	 * Constructor.
	 *
	 * @param PostTypeIndexer $post_type_indexer Post type indexer instance.
	 * @param TaxonomyIndexer $taxonomy_indexer  Taxonomy indexer instance.
	 * @param PostTypeHelper  $post_type_helper   Post type helper instance.
	 */
	public function __construct( PostTypeIndexer $post_type_indexer, TaxonomyIndexer $taxonomy_indexer, PostTypeHelper $post_type_helper ) {
		$this->post_type_indexer = $post_type_indexer;
		$this->taxonomy_indexer  = $taxonomy_indexer;
		$this->post_type_helper  = $post_type_helper;
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
		$step = max( 0, (int) $step );

		// Recupera i post types e le tassonomie da processare
		$post_types = $this->post_type_helper->get_translatable_post_types();
		$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );
		$taxonomies = apply_filters( '\FPML_translatable_taxonomies', $taxonomies );

		// Calcola il totale degli step: post types + tassonomie + menu
		$total_steps = count( $post_types ) + count( $taxonomies ) + 1;

		// Recupera o inizializza il riepilogo dalla transient
		$transient_key = '\FPML_reindex_summary_' . get_current_user_id();
		$summary = get_transient( $transient_key );

		if ( false === $summary || 0 === $step ) {
			$summary = array(
				'posts_scanned'        => 0,
				'posts_enqueued'       => 0,
				'translations_created' => 0,
				'terms_scanned'        => 0,
				'menus_synced'         => 0,
			);
		}

		$current_task = '';
		$complete = false;

		// Determina cosa processare in base allo step
		if ( $step < count( $post_types ) ) {
			// Processa un post type
			$post_type = $post_types[ $step ];
			$current_task = sprintf( __( 'Scansione %s...', 'fp-multilanguage' ), $post_type );

			$result = $this->post_type_indexer->reindex_post_type( $post_type );
			$summary['posts_scanned']        += $result['posts_scanned'];
			$summary['posts_enqueued']       += $result['posts_enqueued'];
			$summary['translations_created'] += $result['translations_created'];

		} elseif ( $step < count( $post_types ) + count( $taxonomies ) ) {
			// Processa una tassonomia
			$taxonomy_index = $step - count( $post_types );
			$taxonomy_values = array_values( $taxonomies );
			$taxonomy = $taxonomy_values[ $taxonomy_index ];
			$current_task = sprintf( __( 'Scansione tassonomia %s...', 'fp-multilanguage' ), $taxonomy );

			$result = $this->taxonomy_indexer->reindex_taxonomy( $taxonomy );
			$summary['terms_scanned'] += $result['terms_scanned'];

		} else {
			// Ultimo step: sincronizza i menu
			$current_task = __( 'Sincronizzazione menu...', 'fp-multilanguage' );

			$menu_sync = function_exists( 'fpml_get_menu_sync' ) ? fpml_get_menu_sync() : ( function_exists( 'fpml_get_menu_sync' ) ? fpml_get_menu_sync() : \FPML_Menu_Sync::instance() );
			if ( $menu_sync instanceof \FPML_Menu_Sync ) {
				$summary['menus_synced'] = $menu_sync->resync_all();
			}

			$complete = true;
		}

		// Salva il riepilogo aggiornato
		if ( ! $complete ) {
			set_transient( $transient_key, $summary, HOUR_IN_SECONDS );
		} else {
			delete_transient( $transient_key );
		}

		$progress_percent = $total_steps > 0 ? round( ( ( $step + 1 ) / $total_steps ) * 100 ) : 100;

		return array(
			'success'          => true,
			'complete'         => $complete,
			'step'             => $step,
			'total_steps'      => $total_steps,
			'progress_percent' => min( 100, $progress_percent ),
			'current_task'     => $current_task,
			'summary'          => $summary,
		);
	}
}















