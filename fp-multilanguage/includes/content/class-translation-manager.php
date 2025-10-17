<?php
/**
 * Translation Manager - Handles creation and synchronization of translated content.
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
 * Manages creation and pairing of translated posts and terms.
 *
 * @since 0.4.0
 */
class FPML_Translation_Manager {
	/**
	 * Singleton instance.
	 *
	 * @var FPML_Translation_Manager|null
	 */
	protected static $instance = null;

	/**
	 * Logger instance.
	 *
	 * @var FPML_Logger
	 */
	protected $logger;

	/**
	 * Flag to avoid recursion while creating translations.
	 *
	 * @var bool
	 */
	protected $creating_translation = false;

	/**
	 * Flag to avoid recursion while creating term translations.
	 *
	 * @var bool
	 */
	protected $creating_term_translation = false;

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$this->logger = FPML_Container::get( 'logger' ) ?: FPML_Logger::instance();
	}

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.0
	 *
	 * @return FPML_Translation_Manager
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Ensure a translation post exists and return it.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $post Source post.
	 *
	 * @return WP_Post|false
	 */
	public function ensure_post_translation( $post ) {
		$target_id = (int) get_post_meta( $post->ID, '_fpml_pair_id', true );

		if ( $target_id ) {
			$target_post = get_post( $target_id );

			if ( $target_post instanceof WP_Post ) {
				update_post_meta( $target_post->ID, '_fpml_pair_source_id', $post->ID );
				update_post_meta( $target_post->ID, '_fpml_is_translation', 1 );

				return $target_post;
			}
		}

	$this->creating_translation = true;

	// Map parent to its translation if exists
	$translated_parent = 0;
	if ( $post->post_parent > 0 ) {
		$parent_translation_id = get_post_meta( $post->post_parent, '_fpml_pair_id', true );
		if ( $parent_translation_id ) {
			$translated_parent = (int) $parent_translation_id;
		}
	}

	// Genera titolo e slug per la pagina tradotta
	// Usa il titolo originale invece di aggiungere "(EN - Translation in progress)"
	$translation_title = $post->post_title;
	$temp_slug = $this->generate_translation_slug( $post->post_name ? $post->post_name : sanitize_title( $post->post_title ) );

	// Crea contenuto placeholder più appropriato
	$placeholder_content = sprintf(
		'<!-- FPML Translation Placeholder -->\n<p>This page is being translated from Italian. Content will be updated automatically.</p>\n<p><em>Original page: <a href="%s">%s</a></em></p>',
		get_permalink( $post->ID ),
		$post->post_title
	);

	$postarr = array(
		'post_type'      => $post->post_type,
		'post_status'    => $post->post_status,
		'post_author'    => $post->post_author,
		'post_parent'    => $translated_parent,
		'menu_order'     => $post->menu_order,
		'post_password'  => $post->post_password,
		'comment_status' => $post->comment_status,
		'ping_status'    => $post->ping_status,
		'post_title'     => $translation_title,
		'post_content'   => $placeholder_content,
		'post_excerpt'   => '',
		'post_name'      => $temp_slug,
		'meta_input'     => array(
			'_fpml_is_translation'  => 1,
			'_fpml_pair_source_id' => $post->ID,
			'_fpml_translation_status' => 'pending',
		),
	);

	$target_id = wp_insert_post( $postarr, true );

	if ( is_wp_error( $target_id ) ) {
		$this->creating_translation = false;
		$this->logger->log(
			'error',
			sprintf( 'Impossibile creare la traduzione per il post #%d: %s', $post->ID, $target_id->get_error_message() ),
			array(
				'post_id' => $post->ID,
			)
		);

		return false;
	}

	// Update source post meta BEFORE releasing lock to prevent race condition
	update_post_meta( $post->ID, '_fpml_pair_id', $target_id );

	$this->creating_translation = false;

		$target_post = get_post( $target_id );

		if ( $target_post instanceof WP_Post ) {
			update_post_meta( $target_post->ID, '_fpml_pair_source_id', $post->ID );
			update_post_meta( $target_post->ID, '_fpml_is_translation', 1 );

			return $target_post;
		}

		return false;
	}

	/**
	 * Ensure taxonomy terms have an English counterpart.
	 *
	 * @since 0.4.0
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return WP_Term|false Target term or false on failure.
	 */
	public function sync_term_translation( $term_id, $taxonomy ) {
		if ( $this->creating_term_translation ) {
			return false;
		}

		$term = get_term( $term_id, $taxonomy );

		if ( ! $term || is_wp_error( $term ) ) {
			return false;
		}

		if ( get_term_meta( $term_id, '_fpml_is_translation', true ) ) {
			return false;
		}

		$target_id = (int) get_term_meta( $term_id, '_fpml_pair_id', true );

		if ( $target_id ) {
			$target_term = get_term( $target_id, $taxonomy );
		} else {
			$target_term = $this->create_term_translation( $term );

			if ( ! $target_term ) {
				return false;
			}

			$target_id = (int) $target_term->term_id;
			update_term_meta( $term_id, '_fpml_pair_id', $target_id );
		}

		if ( ! $target_term || is_wp_error( $target_term ) ) {
			return false;
		}

		update_term_meta( $target_term->term_id, '_fpml_pair_source_id', $term->term_id );
		update_term_meta( $target_term->term_id, '_fpml_is_translation', 1 );

		return $target_term;
	}

	/**
	 * Create a translated term shell.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Term $term Source term.
	 *
	 * @return WP_Term|false
	 */
	public function create_term_translation( $term ) {
		$this->creating_term_translation = true;

		// Map parent to its translation if exists
		$translated_parent = 0;
		if ( $term->parent > 0 ) {
			$parent_translation_id = get_term_meta( $term->parent, '_fpml_pair_id', true );
			if ( $parent_translation_id ) {
				$translated_parent = (int) $parent_translation_id;
			}
		}

		$args = array(
			'slug'        => $this->generate_translation_slug( $term->slug ),
			'parent'      => $translated_parent,
			'description' => '',
			'meta_input'  => array(
				'_fpml_is_translation'  => 1,
				'_fpml_pair_source_id' => $term->term_id,
			),
		);

	$result = wp_insert_term( $term->name, $term->taxonomy, $args );

	if ( is_wp_error( $result ) ) {
		$this->creating_term_translation = false;
		$this->logger->log(
			'error',
			sprintf( 'Impossibile creare la traduzione del termine #%d: %s', $term->term_id, $result->get_error_message() ),
			array(
				'term_id'  => $term->term_id,
				'taxonomy' => $term->taxonomy,
			)
		);

		return false;
	}

	if ( empty( $result['term_id'] ) ) {
		$this->creating_term_translation = false;
		return false;
	}

	update_term_meta( $result['term_id'], '_fpml_pair_source_id', $term->term_id );
	update_term_meta( $result['term_id'], '_fpml_is_translation', 1 );

	$this->creating_term_translation = false;

		return get_term( (int) $result['term_id'], $term->taxonomy );
	}

	/**
	 * Generate a slug candidate for the translated entity.
	 *
	 * @since 0.4.0
	 *
	 * @param string $slug Source slug.
	 *
	 * @return string
	 */
	protected function generate_translation_slug( $slug ) {
		$slug = sanitize_title( $slug );

		if ( '' === $slug ) {
			$slug = uniqid( 'fpml-en-', true );
			return $slug;
		}

		// Per le traduzioni, aggiungiamo un prefisso per evitare conflitti
		// Questo evita che WordPress aggiunga -2, -3, etc.
		$translation_slug = 'en-' . $slug;
		
		// Verifica se lo slug esiste già
		$existing_post = get_page_by_path( $translation_slug, OBJECT, array( 'page', 'post' ) );
		
		if ( $existing_post ) {
			// Se esiste già, aggiungi un timestamp per renderlo unico
			$translation_slug = 'en-' . $slug . '-' . time();
		}

		return $translation_slug;
	}

	/**
	 * Check if currently creating a translation (to prevent recursion).
	 *
	 * @since 0.4.0
	 *
	 * @return bool
	 */
	public function is_creating_translation() {
		return $this->creating_translation;
	}

	/**
	 * Check if currently creating a term translation (to prevent recursion).
	 *
	 * @since 0.4.0
	 *
	 * @return bool
	 */
	public function is_creating_term_translation() {
		return $this->creating_term_translation;
	}
}
