<?php
/**
 * Content Indexer - Reindexes existing content for translation.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles reindexing of posts, terms and menus.
 *
 * @since 0.4.0
 */
class FPML_Content_Indexer {
	/**
	 * Singleton instance.
	 *
	 * @var FPML_Content_Indexer|null
	 */
	protected static $instance = null;

	/**
	 * Translation manager.
	 *
	 * @var FPML_Translation_Manager
	 */
	protected $translation_manager;

	/**
	 * Job enqueuer.
	 *
	 * @var FPML_Job_Enqueuer
	 */
	protected $job_enqueuer;

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$this->translation_manager = FPML_Container::get( 'translation_manager' ) ?: FPML_Translation_Manager::instance();
		$this->job_enqueuer        = FPML_Container::get( 'job_enqueuer' ) ?: FPML_Job_Enqueuer::instance();
	}

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.0
	 *
	 * @return FPML_Content_Indexer
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
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
			$post_types = $this->get_translatable_post_types();
		}

		foreach ( $post_types as $post_type ) {
			// Estendi il timeout ogni post type per evitare scadenze
			$this->maybe_extend_timeout( $start_time );
			
			$result = $this->reindex_post_type( $post_type );
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

		$taxonomies = apply_filters( 'fpml_translatable_taxonomies', $taxonomies );

		foreach ( $taxonomies as $taxonomy ) {
			// Estendi il timeout ogni tassonomia
			$this->maybe_extend_timeout( $start_time );
			
			$result = $this->reindex_taxonomy( $taxonomy );
			$summary['terms_scanned'] += $result['terms_scanned'];
		}

		// Estendi il timeout prima della sincronizzazione menu
		$this->maybe_extend_timeout( $start_time );

		$menu_sync = FPML_Menu_Sync::instance();

		if ( $menu_sync instanceof FPML_Menu_Sync ) {
			$summary['menus_synced'] = $menu_sync->resync_all();
		}

		/**
		 * Allow filtering of the reindex summary before returning.
		 *
		 * @since 0.2.0
		 *
		 * @param array $summary Summary data.
		 */
		return apply_filters( 'fpml_reindex_summary', $summary );
	}

	/**
	 * Estende il timeout di esecuzione PHP se necessario.
	 *
	 * Controlla se siamo vicini al limite di tempo di esecuzione e lo estende
	 * per evitare che il reindex vada in timeout su grandi dataset.
	 *
	 * @since 0.4.3
	 *
	 * @param int $start_time Timestamp di inizio dell'operazione.
	 *
	 * @return void
	 */
	protected function maybe_extend_timeout( $start_time ) {
		// Verifica se set_time_limit è disponibile
		if ( ! function_exists( 'set_time_limit' ) || false !== strpos( ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
			return;
		}

		$max_execution_time = (int) ini_get( 'max_execution_time' );
		
		// Se il timeout è 0 (illimitato), non serve fare nulla
		if ( 0 === $max_execution_time ) {
			return;
		}

		$elapsed_time = time() - $start_time;
		$remaining_time = $max_execution_time - $elapsed_time;

		// Se rimangono meno di 60 secondi, estendi il timeout di altri 5 minuti
		if ( $remaining_time < 60 ) {
			@set_time_limit( 300 );
		}
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
		$summary = array(
			'posts_scanned'        => 0,
			'posts_enqueued'       => 0,
			'translations_created' => 0,
		);

		$paged = 1;
		$start_time = time();

		do {
			// Estendi il timeout ogni 5 pagine per evitare scadenze su grandi dataset
			if ( 0 === ( $paged % 5 ) ) {
				$this->maybe_extend_timeout( $start_time );
			}

			$query = new WP_Query(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'any',
					'posts_per_page' => 100,
					'paged'          => $paged,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			);

			if ( ! $query->have_posts() ) {
				break;
			}

			// Pre-load all post meta to avoid N+1 queries.
			update_meta_cache( 'post', $query->posts );

			foreach ( $query->posts as $post_id ) {
				$post = get_post( $post_id );

				if ( ! $post instanceof WP_Post ) {
					continue;
				}

				if ( get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
					continue;
				}

				$summary['posts_scanned']++;

				$existing_target = (int) get_post_meta( $post->ID, '_fpml_pair_id', true );
				$target_post     = $this->translation_manager->ensure_post_translation( $post );

				if ( ! $target_post ) {
					continue;
				}

				if ( ! $existing_target ) {
					$summary['translations_created']++;
				}

				$this->job_enqueuer->enqueue_post_jobs( $post, $target_post, true );
				$summary['posts_enqueued']++;
			}

			$paged++;
		} while ( $paged <= $query->max_num_pages );

		wp_reset_postdata();

		return $summary;
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
		$summary = array(
			'terms_scanned' => 0,
		);

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return $summary;
		}

		// Pre-load term meta to avoid N+1 queries.
		if ( ! empty( $terms ) ) {
			update_meta_cache( 'term', $terms );
		}

		foreach ( $terms as $term_id ) {
			if ( get_term_meta( $term_id, '_fpml_is_translation', true ) ) {
				continue;
			}

			$summary['terms_scanned']++;
			
			$target_term = $this->translation_manager->sync_term_translation( $term_id, $taxonomy );
			
			if ( $target_term ) {
				$term = get_term( $term_id, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$this->job_enqueuer->enqueue_term_jobs( $term, $target_term );
				}
			}
		}

		return $summary;
	}

	/**
	 * Retrieve allowed post types for translation.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	protected function get_translatable_post_types() {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'names'
		);

		if ( ! in_array( 'attachment', $post_types, true ) ) {
			$post_types[] = 'attachment';
		}

		// Aggiungi post types personalizzati accettati.
		$custom_post_types = get_option( 'fpml_custom_translatable_post_types', array() );
		if ( ! empty( $custom_post_types ) ) {
			$post_types = array_merge( $post_types, $custom_post_types );
		}

		$post_types = apply_filters( 'fpml_translatable_post_types', $post_types );

		return array_filter( array_map( 'sanitize_key', $post_types ) );
	}
}
