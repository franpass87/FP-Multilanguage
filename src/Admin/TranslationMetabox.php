<?php
/**
 * Translation Metabox in Post Editor.
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

namespace FP\Multilanguage\Admin;

use FP\Multilanguage\Queue;
use FP\Multilanguage\Content\TranslationManager;
use FP\Multilanguage\Admin\Metabox\MetaboxRenderer;
use FP\Multilanguage\Admin\Metabox\MetaboxAjax;
use FP\Multilanguage\Admin\Metabox\MetaboxAssets;
use FP\Multilanguage\Admin\Metabox\MetaboxNotices;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clear metabox showing translation status and actions.
 *
 * @since 0.5.0
 */
class TranslationMetabox {
	protected static $instance = null;

	/**
	 * Metabox renderer instance.
	 *
	 * @since 0.10.0
	 *
	 * @var MetaboxRenderer
	 */
	protected $renderer;

	/**
	 * Metabox AJAX handler instance.
	 *
	 * @since 0.10.0
	 *
	 * @var MetaboxAjax
	 */
	protected $ajax_handler;

	/**
	 * Metabox assets manager instance.
	 *
	 * @since 0.10.0
	 *
	 * @var MetaboxAssets
	 */
	protected $assets;

	/**
	 * Metabox notices manager instance.
	 *
	 * @since 0.10.0
	 *
	 * @var MetaboxNotices
	 */
	protected $notices;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		// Initialize modules
		$this->renderer = new MetaboxRenderer();
		$this->ajax_handler = new MetaboxAjax();
		$this->assets = new MetaboxAssets();
		$this->notices = new MetaboxNotices();

		// Register AJAX hooks
		add_action( 'wp_ajax_fpml_force_translate_now', array( $this->ajax_handler, 'ajax_force_translate' ) );
		add_action( 'wp_ajax_fpml_get_translate_nonce', array( $this->ajax_handler, 'ajax_get_translate_nonce' ) );
		add_action( 'wp_ajax_fpml_save_translation_provider', array( $this, 'ajax_save_translation_provider' ) );
		
		// Admin hooks
		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
			add_action( 'admin_enqueue_scripts', array( $this->assets, 'enqueue_scripts' ) );
			add_action( 'admin_notices', array( $this->notices, 'show_translate_notice_after_save' ) );
			add_action( 'save_post', array( $this->notices, 'set_translate_notice_flag' ), 10, 2 );
			add_action( 'save_post', array( $this, 'save_translation_provider' ), 10, 2 );
			
			// Log successful initialization for debugging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( 'FP Multilanguage: TranslationMetabox initialized successfully.' );
			}
		}
	}

	/**
	 * Add meta box to post editor.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		// Default post types - can be extended via filter
		$default_post_types = array( 'post', 'page' );
		
		/**
		 * Filter the post types where translation metabox should appear.
		 *
		 * @since 0.9.2
		 *
		 * @param array $post_types Array of post type slugs.
		 */
		$post_types = apply_filters( 'fpml_metabox_post_types', $default_post_types );
		
		// Log for debugging if WP_DEBUG is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( 'FP Multilanguage: Registering translation metabox for post types: ' . implode( ', ', $post_types ) );
		}
		
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'fpml_translation_status',
				'🌍 ' . __( 'Traduzioni', 'fp-multilanguage' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	/**
	 * Render meta box content.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		try {
			$this->renderer->render_meta_box( $post );
		} catch ( \Throwable $e ) {
			// Log error but don't break the page
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FP Multilanguage: Error rendering translation metabox: ' . $e->getMessage() );
			}
			// Show user-friendly error message
			echo '<div class="notice notice-error inline"><p>';
			echo esc_html__( 'Errore nel caricamento del pannello Traduzioni. Controlla i log per i dettagli.', 'fp-multilanguage' );
			echo '</p></div>';
		}
	}

	/**
	 * Imposta flag per mostrare notice dopo salvataggio.
	 *
	 * @since 0.9.4
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public function set_translate_notice_flag( int $post_id, \WP_Post $post ): void {
		$this->notices->set_translate_notice_flag( $post_id, $post );
	}

	/**
	 * Mostra admin notice con bottone "Traduci ora" dopo salvataggio.
	 *
	 * @since 0.9.4
	 *
	 * @return void
	 */
	public function show_translate_notice_after_save(): void {
		$this->notices->show_translate_notice_after_save();
	}

	/**
	 * Enqueue scripts for post editor.
	 *
	 * @param string $hook Current admin page hook.
	 *
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		$this->assets->enqueue_scripts( $hook );
	}

	/**
	 * AJAX handler per ottenere un nonce fresco per la traduzione.
	 *
	 * @since 0.9.5
	 *
	 * @return void
	 */
	public function ajax_get_translate_nonce(): void {
		$this->ajax_handler->ajax_get_translate_nonce();
	}

	/**
	 * AJAX handler for force translate action.
	 *
	 * @return void
	 */
	public function ajax_force_translate(): void {
		$this->ajax_handler->ajax_force_translate();
	}

	/**
	 * Salva il provider di traduzione scelto per il post.
	 *
	 * @since 0.10.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public function save_translation_provider( int $post_id, \WP_Post $post ): void {
		// Verifica permessi
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Verifica autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Verifica revisioni
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Salva il provider se presente
		if ( isset( $_POST['fpml_translation_provider'] ) ) {
			$provider = sanitize_text_field( $_POST['fpml_translation_provider'] );
			// Valida il valore
			if ( in_array( $provider, array( 'auto', 'wpml', 'fpml' ), true ) ) {
				update_post_meta( $post_id, '_fpml_translation_provider', $provider );
			}
		}
	}

	/**
	 * AJAX handler per salvare il provider di traduzione.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function ajax_save_translation_provider(): void {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : '';

		// Verifica nonce
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( $_POST['_wpnonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'fpml_save_translation_provider_' . get_current_user_id() ) ) {
			wp_send_json_error( array( 'message' => __( 'Errore di sicurezza.', 'fp-multilanguage' ) ) );
		}

		// Verifica permessi
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
		}

		// Valida il provider
		if ( ! in_array( $provider, array( 'auto', 'wpml', 'fpml' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Provider non valido.', 'fp-multilanguage' ) ) );
		}

		// Salva il provider
		update_post_meta( $post_id, '_fpml_translation_provider', $provider );

		$provider_names = array(
			'auto' => __( 'Automatico', 'fp-multilanguage' ),
			'wpml' => __( 'WPML', 'fp-multilanguage' ),
			'fpml' => __( 'FP Multilanguage', 'fp-multilanguage' ),
		);

		wp_send_json_success( array( 
			'message' => sprintf( __( 'Provider salvato: %s', 'fp-multilanguage' ), $provider_names[ $provider ] )
		) );
	}
}
