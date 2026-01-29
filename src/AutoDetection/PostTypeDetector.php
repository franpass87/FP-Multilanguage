<?php
/**
 * Auto Detection Post Type Detector - Detects new post types.
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
 * Detects new post types.
 *
 * @since 0.10.0
 */
class PostTypeDetector {
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
	 * Callback quando viene registrato un nuovo post type.
	 *
	 * @since 0.10.0
	 *
	 * @param string       $post_type Post type slug.
	 * @param \WP_Post_Type|array $args      Post type object or array.
	 *
	 * @return void
	 */
	public function on_post_type_registered( $post_type, $args ): void {
		// Gestisci $args come oggetto o array.
		$is_public = is_object( $args ) ? $args->public : ( isset( $args['public'] ) ? $args['public'] : false );
		
		// Ignora post types interni e non pubblici.
		if ( ! $is_public || in_array( $post_type, array( 'attachment', 'revision', 'nav_menu_item' ), true ) ) {
			return;
		}

		// Controlla se è già stato rilevato o ignorato.
		$detected = $this->storage->get_detected_post_types();
		$ignored  = $this->storage->get_ignored_post_types();

		if ( isset( $detected[ $post_type ] ) || in_array( $post_type, $ignored, true ) ) {
			return;
		}

		// Controlla se è già tra i translatable.
		$translatable = get_post_types( array( 'public' => true ), 'names' );
		
		// Aggiungi post types personalizzati accettati.
		$custom_post_types = get_option( '\FPML_custom_translatable_post_types', array() );
		if ( ! empty( $custom_post_types ) ) {
			$translatable = array_merge( $translatable, $custom_post_types );
		}
		
		$translatable = apply_filters( '\FPML_translatable_post_types', $translatable );

		if ( in_array( $post_type, $translatable, true ) ) {
			return;
		}

		// Rileva il nuovo post type.
		// Gestisci label e hierarchical sia per oggetti che array.
		$label = $post_type;
		if ( is_object( $args ) ) {
			$label = $args->labels->singular_name ?? $post_type;
		} elseif ( isset( $args['labels']['singular_name'] ) ) {
			$label = $args['labels']['singular_name'];
		}
		
		$hierarchical = is_object( $args ) ? $args->hierarchical : ( isset( $args['hierarchical'] ) ? $args['hierarchical'] : false );
		
		$detected[ $post_type ] = array(
			'label'        => $label,
			'detected_at'  => current_time( 'timestamp', true ),
			'post_count'   => wp_count_posts( $post_type )->publish ?? 0,
			'hierarchical' => $hierarchical,
		);

		$this->storage->update_detected_post_types( $detected );

		$this->logger->log(
			'info',
			sprintf( 'Nuovo post type rilevato: %s', $post_type ),
			array( 'post_type' => $post_type, 'label' => $detected[ $post_type ]['label'] )
		);
	}
}
















