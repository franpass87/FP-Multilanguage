<?php
/**
 * Auto-detection di nuovi post types e tassonomie.
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
 * Rileva automaticamente nuovi contenuti e suggerisce la traduzione.
 *
 * @since 0.4.0
 */
class FPML_Auto_Detection {
	/**
	 * Opzione per memorizzare i post types rilevati.
	 */
	const OPTION_DETECTED_POST_TYPES = 'fpml_detected_post_types';

	/**
	 * Opzione per memorizzare le tassonomie rilevate.
	 */
	const OPTION_DETECTED_TAXONOMIES = 'fpml_detected_taxonomies';

	/**
	 * Opzione per memorizzare i post types ignorati dall'utente.
	 */
	const OPTION_IGNORED_POST_TYPES = 'fpml_ignored_post_types';

	/**
	 * Opzione per memorizzare le tassonomie ignorate dall'utente.
	 */
	const OPTION_IGNORED_TAXONOMIES = 'fpml_ignored_taxonomies';

	/**
	 * Singleton instance.
	 *
	 * @var FPML_Auto_Detection|null
	 */
	protected static $instance = null;

	/**
	 * Logger reference.
	 *
	 * @var FPML_Logger
	 */
	protected $logger;

	/**
	 * Plugin reference.
	 *
	 * @var FPML_Plugin
	 */
	protected $plugin;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.0
	 *
	 * @return FPML_Auto_Detection
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$this->logger = FPML_Logger::instance();
		// Rimossa dipendenza da FPML_Plugin::instance() - non utilizzata

		// Hook su registrazione post types e tassonomie.
		add_action( 'registered_post_type', array( $this, 'on_post_type_registered' ), 10, 2 );
		add_action( 'registered_taxonomy', array( $this, 'on_taxonomy_registered' ), 10, 3 );

		// Scan giornaliero per nuovi contenuti.
		add_action( 'init', array( $this, 'schedule_daily_scan' ) );
		add_action( 'fpml_daily_content_scan', array( $this, 'run_daily_scan' ) );

		// Admin notice per nuovi contenuti rilevati.
		add_action( 'admin_notices', array( $this, 'show_detection_notices' ) );

		// AJAX per accettare/ignorare suggerimenti.
		add_action( 'wp_ajax_fpml_accept_post_type', array( $this, 'ajax_accept_post_type' ) );
		add_action( 'wp_ajax_fpml_ignore_post_type', array( $this, 'ajax_ignore_post_type' ) );
		add_action( 'wp_ajax_fpml_accept_taxonomy', array( $this, 'ajax_accept_taxonomy' ) );
		add_action( 'wp_ajax_fpml_ignore_taxonomy', array( $this, 'ajax_ignore_taxonomy' ) );
	}

	/**
	 * Schedula lo scan giornaliero.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function schedule_daily_scan() {
		if ( ! wp_next_scheduled( 'fpml_daily_content_scan' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'fpml_daily_content_scan' );
		}
	}

	/**
	 * Callback quando viene registrato un nuovo post type.
	 *
	 * @since 0.4.0
	 *
	 * @param string       $post_type Post type slug.
	 * @param WP_Post_Type $args      Post type object.
	 *
	 * @return void
	 */
	public function on_post_type_registered( $post_type, $args ) {
		// Ignora post types interni e non pubblici.
		if ( ! $args->public || in_array( $post_type, array( 'attachment', 'revision', 'nav_menu_item' ), true ) ) {
			return;
		}

		// Controlla se è già stato rilevato o ignorato.
		$detected = get_option( self::OPTION_DETECTED_POST_TYPES, array() );
		$ignored  = get_option( self::OPTION_IGNORED_POST_TYPES, array() );

		if ( isset( $detected[ $post_type ] ) || in_array( $post_type, $ignored, true ) ) {
			return;
		}

		// Controlla se è già tra i translatable.
		$translatable = apply_filters( 'fpml_translatable_post_types', array() );

		if ( in_array( $post_type, $translatable, true ) ) {
			return;
		}

		// Rileva il nuovo post type.
		$detected[ $post_type ] = array(
			'label'        => $args->labels->singular_name ?? $post_type,
			'detected_at'  => current_time( 'timestamp', true ),
			'post_count'   => wp_count_posts( $post_type )->publish ?? 0,
			'hierarchical' => $args->hierarchical,
		);

		update_option( self::OPTION_DETECTED_POST_TYPES, $detected, false );

		$this->logger->log(
			'info',
			sprintf( 'Nuovo post type rilevato: %s', $post_type ),
			array( 'post_type' => $post_type, 'label' => $detected[ $post_type ]['label'] )
		);
	}

	/**
	 * Callback quando viene registrata una nuova tassonomia.
	 *
	 * @since 0.4.0
	 *
	 * @param string      $taxonomy Taxonomy slug.
	 * @param array       $object_type Post types associati.
	 * @param WP_Taxonomy $args      Taxonomy object.
	 *
	 * @return void
	 */
	public function on_taxonomy_registered( $taxonomy, $object_type, $args ) {
		// Ignora tassonomie interne e non pubbliche.
		if ( ! $args->public || in_array( $taxonomy, array( 'nav_menu', 'link_category', 'post_format' ), true ) ) {
			return;
		}

		// Controlla se è già stata rilevata o ignorata.
		$detected = get_option( self::OPTION_DETECTED_TAXONOMIES, array() );
		$ignored  = get_option( self::OPTION_IGNORED_TAXONOMIES, array() );

		if ( isset( $detected[ $taxonomy ] ) || in_array( $taxonomy, $ignored, true ) ) {
			return;
		}

		// Controlla se è già tra i translatable.
		$translatable = apply_filters( 'fpml_translatable_taxonomies', array() );

		if ( in_array( $taxonomy, $translatable, true ) ) {
			return;
		}

		// Rileva la nuova tassonomia.
		$term_count = wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );

		$detected[ $taxonomy ] = array(
			'label'        => $args->labels->singular_name ?? $taxonomy,
			'detected_at'  => current_time( 'timestamp', true ),
			'term_count'   => is_wp_error( $term_count ) ? 0 : $term_count,
			'hierarchical' => $args->hierarchical,
		);

		update_option( self::OPTION_DETECTED_TAXONOMIES, $detected, false );

		$this->logger->log(
			'info',
			sprintf( 'Nuova tassonomia rilevata: %s', $taxonomy ),
			array( 'taxonomy' => $taxonomy, 'label' => $detected[ $taxonomy ]['label'] )
		);
	}

	/**
	 * Esegue uno scan giornaliero per rilevare nuovi contenuti.
	 *
	 * @since 0.4.0
	 *
	 * @return array Summary dello scan.
	 */
	public function run_daily_scan() {
		$summary = array(
			'post_types_found' => 0,
			'taxonomies_found' => 0,
			'posts_to_translate' => 0,
		);

		// Scan post types.
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		foreach ( $post_types as $post_type => $args ) {
			$this->on_post_type_registered( $post_type, $args );
		}

		// Scan tassonomie.
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );

		foreach ( $taxonomies as $taxonomy => $args ) {
			$this->on_taxonomy_registered( $taxonomy, array(), $args );
		}

		$detected_post_types = get_option( self::OPTION_DETECTED_POST_TYPES, array() );
		$detected_taxonomies = get_option( self::OPTION_DETECTED_TAXONOMIES, array() );

		$summary['post_types_found'] = count( $detected_post_types );
		$summary['taxonomies_found'] = count( $detected_taxonomies );

		// Conta contenuti da tradurre.
		foreach ( $detected_post_types as $post_type => $data ) {
			$summary['posts_to_translate'] += $data['post_count'];
		}

		$this->logger->log(
			'info',
			'Scan giornaliero completato',
			$summary
		);

		return $summary;
	}

	/**
	 * Mostra admin notice per nuovi contenuti rilevati.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function show_detection_notices() {
		$detected_post_types = get_option( self::OPTION_DETECTED_POST_TYPES, array() );
		$detected_taxonomies = get_option( self::OPTION_DETECTED_TAXONOMIES, array() );

		if ( empty( $detected_post_types ) && empty( $detected_taxonomies ) ) {
			return;
		}

		// Mostra notice per post types.
		foreach ( $detected_post_types as $post_type => $data ) {
			?>
			<div class="notice notice-info is-dismissible" id="fpml-post-type-<?php echo esc_attr( $post_type ); ?>">
				<p>
					<strong><?php esc_html_e( 'FP Multilanguage:', 'fp-multilanguage' ); ?></strong>
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: 1: label post type, 2: numero di post */
							__( 'Rilevato nuovo tipo di contenuto: <strong>%1$s</strong> (%2$d elementi). Vuoi abilitare la traduzione automatica?', 'fp-multilanguage' ),
							$data['label'],
							$data['post_count']
						)
					);
					?>
				</p>
				<p>
					<button type="button" class="button button-primary fpml-accept-post-type" data-post-type="<?php echo esc_attr( $post_type ); ?>">
						<?php esc_html_e( 'Sì, abilita traduzione', 'fp-multilanguage' ); ?>
					</button>
					<button type="button" class="button fpml-ignore-post-type" data-post-type="<?php echo esc_attr( $post_type ); ?>">
						<?php esc_html_e( 'No, ignora', 'fp-multilanguage' ); ?>
					</button>
				</p>
			</div>
			<?php
		}

		// Mostra notice per tassonomie.
		foreach ( $detected_taxonomies as $taxonomy => $data ) {
			?>
			<div class="notice notice-info is-dismissible" id="fpml-taxonomy-<?php echo esc_attr( $taxonomy ); ?>">
				<p>
					<strong><?php esc_html_e( 'FP Multilanguage:', 'fp-multilanguage' ); ?></strong>
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: 1: label tassonomia, 2: numero di termini */
							__( 'Rilevata nuova tassonomia: <strong>%1$s</strong> (%2$d termini). Vuoi abilitare la traduzione automatica?', 'fp-multilanguage' ),
							$data['label'],
							$data['term_count']
						)
					);
					?>
				</p>
				<p>
					<button type="button" class="button button-primary fpml-accept-taxonomy" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>">
						<?php esc_html_e( 'Sì, abilita traduzione', 'fp-multilanguage' ); ?>
					</button>
					<button type="button" class="button fpml-ignore-taxonomy" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>">
						<?php esc_html_e( 'No, ignora', 'fp-multilanguage' ); ?>
					</button>
				</p>
			</div>
			<?php
		}

		// JavaScript per gestire i bottoni.
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Accetta post type.
			$('.fpml-accept-post-type').on('click', function() {
				var postType = $(this).data('post-type');
				var $notice = $('#fpml-post-type-' + postType);
				
				$.post(ajaxurl, {
					action: 'fpml_accept_post_type',
					post_type: postType,
					nonce: '<?php echo esc_js( wp_create_nonce( 'fpml_auto_detection' ) ); ?>'
				}, function(response) {
					if (response.success) {
						$notice.slideUp();
						// Mostra messaggio di successo.
						$('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>')
							.insertAfter($notice)
							.delay(3000)
							.slideUp();
					}
				});
			});

			// Ignora post type.
			$('.fpml-ignore-post-type').on('click', function() {
				var postType = $(this).data('post-type');
				var $notice = $('#fpml-post-type-' + postType);
				
				$.post(ajaxurl, {
					action: 'fpml_ignore_post_type',
					post_type: postType,
					nonce: '<?php echo esc_js( wp_create_nonce( 'fpml_auto_detection' ) ); ?>'
				}, function(response) {
					if (response.success) {
						$notice.slideUp();
					}
				});
			});

			// Accetta tassonomia.
			$('.fpml-accept-taxonomy').on('click', function() {
				var taxonomy = $(this).data('taxonomy');
				var $notice = $('#fpml-taxonomy-' + taxonomy);
				
				$.post(ajaxurl, {
					action: 'fpml_accept_taxonomy',
					taxonomy: taxonomy,
					nonce: '<?php echo esc_js( wp_create_nonce( 'fpml_auto_detection' ) ); ?>'
				}, function(response) {
					if (response.success) {
						$notice.slideUp();
						// Mostra messaggio di successo.
						$('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>')
							.insertAfter($notice)
							.delay(3000)
							.slideUp();
					}
				});
			});

			// Ignora tassonomia.
			$('.fpml-ignore-taxonomy').on('click', function() {
				var taxonomy = $(this).data('taxonomy');
				var $notice = $('#fpml-taxonomy-' + taxonomy);
				
				$.post(ajaxurl, {
					action: 'fpml_ignore_taxonomy',
					taxonomy: taxonomy,
					nonce: '<?php echo esc_js( wp_create_nonce( 'fpml_auto_detection' ) ); ?>'
				}, function(response) {
					if (response.success) {
						$notice.slideUp();
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX: accetta un post type e avvia la traduzione.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function ajax_accept_post_type() {
		check_ajax_referer( 'fpml_auto_detection', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
		}

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : '';

		if ( ! $post_type ) {
			wp_send_json_error( array( 'message' => __( 'Post type non valido.', 'fp-multilanguage' ) ) );
		}

		// Rimuovi dai rilevati.
		$detected = get_option( self::OPTION_DETECTED_POST_TYPES, array() );
		$data     = isset( $detected[ $post_type ] ) ? $detected[ $post_type ] : array();
		unset( $detected[ $post_type ] );
		update_option( self::OPTION_DETECTED_POST_TYPES, $detected, false );

		// Aggiungi ai translatable usando il filtro.
		add_filter(
			'fpml_translatable_post_types',
			function( $types ) use ( $post_type ) {
				if ( ! in_array( $post_type, $types, true ) ) {
					$types[] = $post_type;
				}
				return $types;
			}
		);

		// Salva permanentemente nei settings personalizzati.
		$custom_post_types = get_option( 'fpml_custom_translatable_post_types', array() );
		if ( ! in_array( $post_type, $custom_post_types, true ) ) {
			$custom_post_types[] = $post_type;
			update_option( 'fpml_custom_translatable_post_types', $custom_post_types, false );
		}

		// Avvia reindex in background.
		wp_schedule_single_event( time() + 10, 'fpml_reindex_post_type', array( $post_type ) );

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
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function ajax_ignore_post_type() {
		check_ajax_referer( 'fpml_auto_detection', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
		}

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : '';

		if ( ! $post_type ) {
			wp_send_json_error( array( 'message' => __( 'Post type non valido.', 'fp-multilanguage' ) ) );
		}

		// Rimuovi dai rilevati.
		$detected = get_option( self::OPTION_DETECTED_POST_TYPES, array() );
		unset( $detected[ $post_type ] );
		update_option( self::OPTION_DETECTED_POST_TYPES, $detected, false );

		// Aggiungi agli ignorati.
		$ignored = get_option( self::OPTION_IGNORED_POST_TYPES, array() );
		if ( ! in_array( $post_type, $ignored, true ) ) {
			$ignored[] = $post_type;
			update_option( self::OPTION_IGNORED_POST_TYPES, $ignored, false );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: accetta una tassonomia e avvia la traduzione.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function ajax_accept_taxonomy() {
		check_ajax_referer( 'fpml_auto_detection', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
		}

		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( $_POST['taxonomy'] ) : '';

		if ( ! $taxonomy ) {
			wp_send_json_error( array( 'message' => __( 'Tassonomia non valida.', 'fp-multilanguage' ) ) );
		}

		// Rimuovi dai rilevati.
		$detected = get_option( self::OPTION_DETECTED_TAXONOMIES, array() );
		$data     = isset( $detected[ $taxonomy ] ) ? $detected[ $taxonomy ] : array();
		unset( $detected[ $taxonomy ] );
		update_option( self::OPTION_DETECTED_TAXONOMIES, $detected, false );

		// Salva nei settings personalizzati.
		$custom_taxonomies = get_option( 'fpml_custom_translatable_taxonomies', array() );
		if ( ! in_array( $taxonomy, $custom_taxonomies, true ) ) {
			$custom_taxonomies[] = $taxonomy;
			update_option( 'fpml_custom_translatable_taxonomies', $custom_taxonomies, false );
		}

		// Avvia reindex in background.
		wp_schedule_single_event( time() + 10, 'fpml_reindex_taxonomy', array( $taxonomy ) );

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
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function ajax_ignore_taxonomy() {
		check_ajax_referer( 'fpml_auto_detection', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
		}

		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( $_POST['taxonomy'] ) : '';

		if ( ! $taxonomy ) {
			wp_send_json_error( array( 'message' => __( 'Tassonomia non valida.', 'fp-multilanguage' ) ) );
		}

		// Rimuovi dai rilevati.
		$detected = get_option( self::OPTION_DETECTED_TAXONOMIES, array() );
		unset( $detected[ $taxonomy ] );
		update_option( self::OPTION_DETECTED_TAXONOMIES, $detected, false );

		// Aggiungi agli ignorati.
		$ignored = get_option( self::OPTION_IGNORED_TAXONOMIES, array() );
		if ( ! in_array( $taxonomy, $ignored, true ) ) {
			$ignored[] = $taxonomy;
			update_option( self::OPTION_IGNORED_TAXONOMIES, $ignored, false );
		}

		wp_send_json_success();
	}

	/**
	 * Ottieni post types rilevati.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public function get_detected_post_types() {
		return get_option( self::OPTION_DETECTED_POST_TYPES, array() );
	}

	/**
	 * Ottieni tassonomie rilevate.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public function get_detected_taxonomies() {
		return get_option( self::OPTION_DETECTED_TAXONOMIES, array() );
	}
}
