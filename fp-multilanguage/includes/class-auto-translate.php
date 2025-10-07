<?php
/**
 * Traduzione automatica alla pubblicazione (Feature Killer #1).
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
 * Gestisce la traduzione automatica immediata al publish.
 *
 * @since 0.4.0
 */
class FPML_Auto_Translate {
	/**
	 * Meta key per abilitare auto-translate per post specifico.
	 */
	const META_AUTO_TRANSLATE = '_fpml_auto_translate_on_publish';

	/**
	 * Singleton instance.
	 *
	 * @var FPML_Auto_Translate|null
	 */
	protected static $instance = null;

	/**
	 * Settings reference.
	 *
	 * @var FPML_Settings
	 */
	protected $settings;

	/**
	 * Logger reference.
	 *
	 * @var FPML_Logger
	 */
	protected $logger;

	/**
	 * Queue reference.
	 *
	 * @var FPML_Queue
	 */
	protected $queue;

	/**
	 * Processor reference.
	 *
	 * @var FPML_Processor
	 */
	protected $processor;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.0
	 *
	 * @return FPML_Auto_Translate
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
		$this->settings  = FPML_Settings::instance();
		$this->logger    = FPML_Logger::instance();
		$this->queue     = FPML_Queue::instance();
		$this->processor = FPML_Processor::instance();

		// Hook su transizione di stato.
		add_action( 'transition_post_status', array( $this, 'on_post_published' ), 10, 3 );

		// Meta box nell'editor.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );

		// Colonna nella lista post.
		add_filter( 'manage_posts_columns', array( $this, 'add_column' ) );
		add_filter( 'manage_pages_columns', array( $this, 'add_column' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_action( 'manage_pages_custom_column', array( $this, 'render_column' ), 10, 2 );

		// Quick edit support.
		add_action( 'quick_edit_custom_box', array( $this, 'quick_edit_custom_box' ), 10, 2 );
		add_action( 'admin_footer', array( $this, 'quick_edit_javascript' ) );
	}

	/**
	 * Callback quando un post viene pubblicato.
	 *
	 * @since 0.4.0
	 *
	 * @param string  $new_status Nuovo stato.
	 * @param string  $old_status Vecchio stato.
	 * @param WP_Post $post       Post object.
	 *
	 * @return void
	 */
	public function on_post_published( $new_status, $old_status, $post ) {
		// Solo quando si pubblica per la prima volta o si ripubblica.
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		// Controlla se è una traduzione.
		if ( get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
			return;
		}

		// Controlla se auto-translate è abilitato globalmente o per questo post.
		$global_enabled = $this->settings ? $this->settings->get( 'auto_translate_on_publish', false ) : false;
		$post_enabled   = get_post_meta( $post->ID, self::META_AUTO_TRANSLATE, true );

		if ( ! $global_enabled && ! $post_enabled ) {
			return;
		}

		// Traduci immediatamente in modalità sincrona (priorità alta).
		$this->translate_immediately( $post );
	}

	/**
	 * Traduce un post immediatamente in modalità prioritaria.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $post Post da tradurre.
	 *
	 * @return void
	 */
	protected function translate_immediately( $post ) {
		// Crea/ottieni il post tradotto.
		$plugin      = FPML_Plugin::instance();
		$target_post = null;

		if ( method_exists( $plugin, 'ensure_post_translation' ) ) {
			$target_post = $plugin->ensure_post_translation( $post );
		}

		if ( ! $target_post ) {
			$this->logger->log(
				'error',
				sprintf( 'Impossibile creare traduzione per post #%d', $post->ID ),
				array( 'post_id' => $post->ID )
			);
			return;
		}

		// Enqueue job con priorità.
		if ( method_exists( $plugin, 'enqueue_post_jobs' ) ) {
			$plugin->enqueue_post_jobs( $post, $target_post, false );
		}

		// Forza l'esecuzione immediata della coda (massimo 10 secondi).
		$this->run_queue_with_timeout( 10 );

		// Pubblica il post tradotto se la traduzione è completa.
		$this->maybe_publish_translation( $target_post );

		$this->logger->log(
			'info',
			sprintf( 'Post #%d tradotto automaticamente alla pubblicazione', $post->ID ),
			array(
				'source_id' => $post->ID,
				'target_id' => $target_post->ID,
			)
		);
	}

	/**
	 * Esegue la coda con timeout.
	 *
	 * @since 0.4.0
	 *
	 * @param int $timeout_seconds Timeout in secondi.
	 *
	 * @return void
	 */
	protected function run_queue_with_timeout( $timeout_seconds ) {
		$start_time = time();
		$processed  = 0;

		while ( ( time() - $start_time ) < $timeout_seconds ) {
			$result = $this->processor->run_queue();

			if ( is_wp_error( $result ) ) {
				break;
			}

			if ( isset( $result['claimed'] ) && 0 === $result['claimed'] ) {
				// Nessun job da processare.
				break;
			}

			$processed += isset( $result['processed'] ) ? $result['processed'] : 0;

			// Breve pausa per evitare rate limit.
			usleep( 100000 ); // 0.1 secondi.
		}

		$this->logger->log(
			'debug',
			sprintf( 'Processati %d job in %d secondi', $processed, time() - $start_time ),
			array( 'processed' => $processed, 'duration' => time() - $start_time )
		);
	}

	/**
	 * Pubblica il post tradotto se tutti i job sono completati.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $target_post Post tradotto.
	 *
	 * @return void
	 */
	protected function maybe_publish_translation( $target_post ) {
		// Controlla se ci sono job pending per questo post.
		global $wpdb;
		$table = $this->queue->get_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$pending = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE object_type = %s AND object_id = %d AND state IN ('pending', 'translating')",
				'post',
				$target_post->ID
			)
		);

		if ( 0 === (int) $pending && 'publish' !== $target_post->post_status ) {
			// Pubblica il post tradotto.
			wp_update_post(
				array(
					'ID'          => $target_post->ID,
					'post_status' => 'publish',
				)
			);

			$this->logger->log(
				'info',
				sprintf( 'Post tradotto #%d pubblicato automaticamente', $target_post->ID ),
				array( 'post_id' => $target_post->ID )
			);
		}
	}

	/**
	 * Aggiunge meta box nell'editor.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function add_meta_box() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'fpml_auto_translate',
				__( 'Traduzione Automatica', 'fp-multilanguage' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Renderizza meta box.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return void
	 */
	public function render_meta_box( $post ) {
		// Controlla se è una traduzione.
		if ( get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
			echo '<p>' . esc_html__( 'Questo è un post tradotto.', 'fp-multilanguage' ) . '</p>';
			return;
		}

		$auto_translate = get_post_meta( $post->ID, self::META_AUTO_TRANSLATE, true );

		wp_nonce_field( 'fpml_auto_translate_meta', 'fpml_auto_translate_nonce' );
		?>
		<p>
			<label>
				<input type="checkbox" name="fpml_auto_translate_on_publish" value="1" <?php checked( $auto_translate, '1' ); ?> />
				<?php esc_html_e( 'Traduci automaticamente alla pubblicazione', 'fp-multilanguage' ); ?>
			</label>
		</p>
		<p class="description">
			<?php esc_html_e( 'Quando pubblichi questo contenuto, verrà tradotto immediatamente in inglese e pubblicato automaticamente.', 'fp-multilanguage' ); ?>
		</p>
		<?php

		// Mostra stato traduzione se esiste.
		$pair_id = get_post_meta( $post->ID, '_fpml_pair_id', true );
		if ( $pair_id ) {
			$translation = get_post( $pair_id );
			if ( $translation ) {
				echo '<hr />';
				echo '<p><strong>' . esc_html__( 'Traduzione:', 'fp-multilanguage' ) . '</strong></p>';
				echo '<p>';
				echo '<a href="' . esc_url( get_edit_post_link( $translation->ID ) ) . '" target="_blank">';
				echo esc_html( $translation->post_title ? $translation->post_title : __( '(Senza titolo)', 'fp-multilanguage' ) );
				echo '</a><br />';
				echo '<span class="dashicons dashicons-visibility"></span> ';
				echo '<a href="' . esc_url( get_permalink( $translation->ID ) ) . '" target="_blank">' . esc_html__( 'Visualizza', 'fp-multilanguage' ) . '</a>';
				echo '</p>';

				// Stato traduzione.
				$this->render_translation_status( $translation );
			}
		}
	}

	/**
	 * Renderizza lo stato della traduzione.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_Post $translation Post tradotto.
	 *
	 * @return void
	 */
	protected function render_translation_status( $translation ) {
		$fields = array( 'post_title', 'post_content', 'post_excerpt' );
		$status = array();

		foreach ( $fields as $field ) {
			$meta_key   = '_fpml_status_' . $field;
			$field_status = get_post_meta( $translation->ID, $meta_key, true );

			if ( $field_status ) {
				$status[ $field ] = $field_status;
			}
		}

		if ( empty( $status ) ) {
			return;
		}

		echo '<p><small>';
		foreach ( $status as $field => $state ) {
			$icon  = 'synced' === $state || 'done' === $state ? '✓' : '⏳';
			$label = str_replace( 'post_', '', $field );
			echo esc_html( sprintf( '%s %s: %s', $icon, $label, $state ) ) . '<br />';
		}
		echo '</small></p>';
	}

	/**
	 * Salva meta box.
	 *
	 * @since 0.4.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public function save_meta_box( $post_id, $post ) {
		// Verifica nonce.
		if ( ! isset( $_POST['fpml_auto_translate_nonce'] ) || ! wp_verify_nonce( $_POST['fpml_auto_translate_nonce'], 'fpml_auto_translate_meta' ) ) {
			return;
		}

		// Verifica autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Verifica permessi.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Salva il valore.
		$value = isset( $_POST['fpml_auto_translate_on_publish'] ) ? '1' : '0';
		update_post_meta( $post_id, self::META_AUTO_TRANSLATE, $value );
	}

	/**
	 * Aggiunge colonna nella lista post.
	 *
	 * @since 0.4.0
	 *
	 * @param array $columns Colonne esistenti.
	 *
	 * @return array
	 */
	public function add_column( $columns ) {
		$columns['fpml_auto_translate'] = '<span class="dashicons dashicons-translation" title="' . esc_attr__( 'Traduzione Auto', 'fp-multilanguage' ) . '"></span>';
		return $columns;
	}

	/**
	 * Renderizza contenuto colonna.
	 *
	 * @since 0.4.0
	 *
	 * @param string $column  Nome colonna.
	 * @param int    $post_id Post ID.
	 *
	 * @return void
	 */
	public function render_column( $column, $post_id ) {
		if ( 'fpml_auto_translate' !== $column ) {
			return;
		}

		$auto_translate = get_post_meta( $post_id, self::META_AUTO_TRANSLATE, true );

		if ( '1' === $auto_translate ) {
			echo '<span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="' . esc_attr__( 'Traduzione automatica attiva', 'fp-multilanguage' ) . '"></span>';
		} else {
			echo '<span class="dashicons dashicons-minus" style="color: #ddd;" title="' . esc_attr__( 'Traduzione manuale', 'fp-multilanguage' ) . '"></span>';
		}
	}

	/**
	 * Aggiunge campo in quick edit.
	 *
	 * @since 0.4.0
	 *
	 * @param string $column_name Nome colonna.
	 * @param string $post_type   Post type.
	 *
	 * @return void
	 */
	public function quick_edit_custom_box( $column_name, $post_type ) {
		if ( 'fpml_auto_translate' !== $column_name ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<label>
					<input type="checkbox" name="fpml_auto_translate_on_publish" value="1" />
					<span class="checkbox-title"><?php esc_html_e( 'Auto-traduzione', 'fp-multilanguage' ); ?></span>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * JavaScript per popolare quick edit.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function quick_edit_javascript() {
		global $current_screen;

		if ( ! $current_screen || 'edit' !== $current_screen->base ) {
			return;
		}
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			var $wp_inline_edit = inlineEditPost.edit;
			
			inlineEditPost.edit = function(id) {
				$wp_inline_edit.apply(this, arguments);
				
				var post_id = 0;
				if (typeof(id) == 'object') {
					post_id = parseInt(this.getId(id));
				}
				
				if (post_id > 0) {
					var $row = $('#post-' + post_id);
					var $auto_translate = $row.find('.column-fpml_auto_translate .dashicons-yes-alt');
					var $edit_row = $('#edit-' + post_id);
					
					if ($auto_translate.length > 0) {
						$edit_row.find('input[name="fpml_auto_translate_on_publish"]').prop('checked', true);
					} else {
						$edit_row.find('input[name="fpml_auto_translate_on_publish"]').prop('checked', false);
					}
				}
			};
		});
		</script>
		<?php
	}
}
