<?php
/**
 * Auto Detection Ajax - Handles AJAX requests for detection.
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
 * Handles AJAX requests for detection.
 *
 * @since 0.10.0
 */
class DetectionAjax {
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
	 * AJAX: accetta un post type e avvia la traduzione.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function ajax_accept_post_type(): void {
		check_ajax_referer( '\FPML_auto_detection', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
		}

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : '';

		if ( ! $post_type ) {
			wp_send_json_error( array( 'message' => __( 'Post type non valido.', 'fp-multilanguage' ) ) );
		}

		// Rimuovi dai rilevati.
		$detected = $this->storage->get_detected_post_types();
		$data     = isset( $detected[ $post_type ] ) ? $detected[ $post_type ] : array();
		unset( $detected[ $post_type ] );
		$this->storage->update_detected_post_types( $detected );

		// Aggiungi ai translatable usando il filtro.
		add_filter(
			'\FPML_translatable_post_types',
			function( $types ) use ( $post_type ) {
				if ( ! in_array( $post_type, $types, true ) ) {
					$types[] = $post_type;
				}
				return $types;
			}
		);

		// Salva permanentemente nei settings personalizzati.
		$custom_post_types = get_option( '\FPML_custom_translatable_post_types', array() );
		if ( ! in_array( $post_type, $custom_post_types, true ) ) {
			$custom_post_types[] = $post_type;
			update_option( '\FPML_custom_translatable_post_types', $custom_post_types, false );
		}

		// Avvia reindex in background.
		wp_schedule_single_event( time() + 10, '\FPML_reindex_post_type', array( $post_type ) );

		$this->logger->log(
			'info',
			sprintf( 'Post type %s accettato per traduzione', $post_type ),
			array( 'post_type' => $post_type )
		);

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: label post type */
					__( 'Post type "%s" aggiunto alla traduzione. Reindex avviato in background.', 'fp-multilanguage' ),
					isset( $data['label'] ) ? $data['label'] : $post_type
				),
			)
		);
	}

	/**
	 * AJAX: ignora un post type.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function ajax_ignore_post_type(): void {
		check_ajax_referer( '\FPML_auto_detection', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
		}

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : '';

		if ( ! $post_type ) {
			wp_send_json_error( array( 'message' => __( 'Post type non valido.', 'fp-multilanguage' ) ) );
		}

		// Rimuovi dai rilevati.
		$detected = $this->storage->get_detected_post_types();
		unset( $detected[ $post_type ] );
		$this->storage->update_detected_post_types( $detected );

		// Aggiungi agli ignorati.
		$this->storage->add_ignored_post_type( $post_type );

		wp_send_json_success();
	}

	/**
	 * AJAX: accetta una tassonomia e avvia la traduzione.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function ajax_accept_taxonomy(): void {
		check_ajax_referer( '\FPML_auto_detection', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
		}

		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( $_POST['taxonomy'] ) : '';

		if ( ! $taxonomy ) {
			wp_send_json_error( array( 'message' => __( 'Tassonomia non valida.', 'fp-multilanguage' ) ) );
		}

		// Rimuovi dai rilevati.
		$detected = $this->storage->get_detected_taxonomies();
		$data     = isset( $detected[ $taxonomy ] ) ? $detected[ $taxonomy ] : array();
		unset( $detected[ $taxonomy ] );
		$this->storage->update_detected_taxonomies( $detected );

		// Salva nei settings personalizzati.
		$custom_taxonomies = get_option( '\FPML_custom_translatable_taxonomies', array() );
		if ( ! in_array( $taxonomy, $custom_taxonomies, true ) ) {
			$custom_taxonomies[] = $taxonomy;
			update_option( '\FPML_custom_translatable_taxonomies', $custom_taxonomies, false );
		}

		// Avvia reindex in background.
		wp_schedule_single_event( time() + 10, '\FPML_reindex_taxonomy', array( $taxonomy ) );

		$this->logger->log(
			'info',
			sprintf( 'Tassonomia %s accettata per traduzione', $taxonomy ),
			array( 'taxonomy' => $taxonomy )
		);

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: label tassonomia */
					__( 'Tassonomia "%s" aggiunta alla traduzione. Reindex avviato in background.', 'fp-multilanguage' ),
					isset( $data['label'] ) ? $data['label'] : $taxonomy
				),
			)
		);
	}

	/**
	 * AJAX: ignora una tassonomia.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function ajax_ignore_taxonomy(): void {
		check_ajax_referer( '\FPML_auto_detection', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
		}

		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( $_POST['taxonomy'] ) : '';

		if ( ! $taxonomy ) {
			wp_send_json_error( array( 'message' => __( 'Tassonomia non valida.', 'fp-multilanguage' ) ) );
		}

		// Rimuovi dai rilevati.
		$detected = $this->storage->get_detected_taxonomies();
		unset( $detected[ $taxonomy ] );
		$this->storage->update_detected_taxonomies( $detected );

		// Aggiungi agli ignorati.
		$this->storage->add_ignored_taxonomy( $taxonomy );

		wp_send_json_success();
	}
}
















