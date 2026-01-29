<?php
/**
 * Auto Detection Taxonomy Detector - Detects new taxonomies.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\AutoDetection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects new taxonomies.
 *
 * @since 0.10.0
 */
class TaxonomyDetector {
	/**
	 * Logger instance.
	 *
	 * @var \FPML_Logger
	 */
	protected $logger;

	/**
	 * Storage instance.
	 *
	 * @var DetectionStorage
	 */
	protected DetectionStorage $storage;

	/**
	 * Constructor.
	 *
	 * @param \FPML_Logger     $logger  Logger instance.
	 * @param DetectionStorage $storage Storage instance.
	 */
	public function __construct( $logger, DetectionStorage $storage ) {
		$this->logger = $logger;
		$this->storage = $storage;
	}

	/**
	 * Callback quando viene registrata una nuova tassonomia.
	 *
	 * @since 0.10.0
	 *
	 * @param string       $taxonomy Taxonomy slug.
	 * @param array        $object_type Post types associati.
	 * @param \WP_Taxonomy|array $args      Taxonomy object or array.
	 *
	 * @return void
	 */
	public function on_taxonomy_registered( $taxonomy, $object_type, $args ): void {
		// Gestisci $args come oggetto o array.
		$is_public = is_object( $args ) ? $args->public : ( isset( $args['public'] ) ? $args['public'] : false );
		
		// Ignora tassonomie interne e non pubbliche.
		if ( ! $is_public || in_array( $taxonomy, array( 'nav_menu', 'link_category', 'post_format' ), true ) ) {
			return;
		}

		// Controlla se è già stata rilevata o ignorata.
		$detected = $this->storage->get_detected_taxonomies();
		$ignored  = $this->storage->get_ignored_taxonomies();

		if ( isset( $detected[ $taxonomy ] ) || in_array( $taxonomy, $ignored, true ) ) {
			return;
		}

		// Controlla se è già tra i translatable.
		$translatable = get_taxonomies( array( 'public' => true ), 'names' );
		
		// Aggiungi tassonomie personalizzate accettate.
		$custom_taxonomies = get_option( '\FPML_custom_translatable_taxonomies', array() );
		if ( ! empty( $custom_taxonomies ) ) {
			$translatable = array_merge( $translatable, $custom_taxonomies );
		}
		
		$translatable = apply_filters( '\FPML_translatable_taxonomies', $translatable );

		if ( in_array( $taxonomy, $translatable, true ) ) {
			return;
		}

		// Rileva la nuova tassonomia.
		$term_count = wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
		
		// Gestisci label e hierarchical sia per oggetti che array.
		$label = $taxonomy;
		if ( is_object( $args ) ) {
			$label = $args->labels->singular_name ?? $taxonomy;
		} elseif ( isset( $args['labels']['singular_name'] ) ) {
			$label = $args['labels']['singular_name'];
		}
		
		$hierarchical = is_object( $args ) ? $args->hierarchical : ( isset( $args['hierarchical'] ) ? $args['hierarchical'] : false );

		$detected[ $taxonomy ] = array(
			'label'        => $label,
			'detected_at'  => current_time( 'timestamp', true ),
			'term_count'   => is_wp_error( $term_count ) ? 0 : $term_count,
			'hierarchical' => $hierarchical,
		);

		$this->storage->update_detected_taxonomies( $detected );

		$this->logger->log(
			'info',
			sprintf( 'Nuova tassonomia rilevata: %s', $taxonomy ),
			array( 'taxonomy' => $taxonomy, 'label' => $detected[ $taxonomy ]['label'] )
		);
	}
}
















