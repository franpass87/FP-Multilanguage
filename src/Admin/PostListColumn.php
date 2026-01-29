<?php
/**
 * Post List Column - Translation Status.
 *
 * Shows translation status in WordPress post list.
 *
 * @package FP_Multilanguage
 * @since 0.6.1
 */

namespace FP\Multilanguage\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add translation status column to post list.
 *
 * @since 0.6.1
 */
class PostListColumn {
	/**
	 * Singleton instance.
	 *
	 * @var PostListColumn|null
	 */
	protected static $instance = null;

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

	/**
	 * Constructor.
	 */
	protected function __construct() {
		add_filter( 'manage_posts_columns', array( $this, 'add_column' ) );
		add_filter( 'manage_pages_columns', array( $this, 'add_column' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_action( 'manage_pages_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-post_sortable_columns', array( $this, 'sortable_column' ) );
		add_filter( 'manage_edit-page_sortable_columns', array( $this, 'sortable_column' ) );
		add_action( 'pre_get_posts', array( $this, 'sort_by_translation_status' ) );
		add_action( 'admin_head', array( $this, 'column_styles' ) );
	}

	/**
	 * Add translation column.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function add_column( array $columns ): array {
		$new_columns = array();
		
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			
			// Add after title column
			if ( 'title' === $key ) {
				$new_columns['fpml_translation'] = '🌍 Traduzione';
			}
		}
		
		return $new_columns;
	}

	/**
	 * Render translation column.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 *
	 * @return void
	 */
	public function render_column( string $column, int $post_id ): void {
		if ( 'fpml_translation' !== $column ) {
			return;
		}

		// Check if this is a translation itself
		$is_translation = get_post_meta( $post_id, '_fpml_is_translation', true );
		if ( $is_translation ) {
			echo '<span style="color:#9ca3af; font-size:11px;">🔗 È una traduzione</span>';
			return;
		}

		$manager = fpml_get_translation_manager();
		$translation_id = $manager ? $manager->get_translation_id( $post_id, 'en' ) : false;
		
		if ( $translation_id ) {
			$en_post = get_post( $translation_id );
			
			if ( ! $en_post ) {
				echo '<span class="fpml-status-badge fpml-status-error" title="' . esc_attr__( 'Post EN non trovato. La traduzione potrebbe essere stata eliminata.', 'fp-multilanguage' ) . '">⚠️ Errore</span>';
				return;
			}
			
			// Check queue status
			$queue = fpml_get_queue();
			$queue_status = $queue ? $queue->get_job_state( 'post', $post_id, 'post_content' ) : null;
			
			// Determine translation status
			$source_content = get_post_field( 'post_content', $post_id );
			$target_content = get_post_field( 'post_content', $translation_id );
			$has_content = ! empty( $target_content ) && $target_content !== $source_content;
			
			echo '<div class="fpml-translation-status">';
			
			if ( 'translating' === $queue_status || 'pending' === $queue_status ) {
				// In queue or translating
				echo '<span class="fpml-status-badge fpml-status-pending" title="' . esc_attr__( 'Traduzione in coda o in elaborazione', 'fp-multilanguage' ) . '">';
				echo '<span class="fpml-badge-icon">⏳</span> ';
				echo '<span class="fpml-badge-text">' . esc_html__( 'In corso', 'fp-multilanguage' ) . '</span>';
				echo '</span>';
				echo '<div class="fpml-translation-progress" style="margin-top:4px;">';
				echo '<div class="fpml-progress-bar-mini">';
				echo '<div class="fpml-progress-fill-mini" style="width: 60%; animation: fpml-pulse 2s infinite;"></div>';
				echo '</div>';
				echo '</div>';
			} elseif ( $has_content ) {
				// Completed translation
				echo '<span class="fpml-status-badge fpml-status-translated" title="' . esc_attr__( 'Traduzione completa disponibile', 'fp-multilanguage' ) . '">';
				echo '<span class="fpml-badge-icon">✓</span> ';
				echo '<span class="fpml-badge-text">' . esc_html__( 'Tradotto', 'fp-multilanguage' ) . '</span>';
				echo '</span>';
				echo '<div class="row-actions">';
				echo '<span class="view">';
				echo '<a href="' . esc_url( get_permalink( $translation_id ) ) . '" target="_blank" title="' . esc_attr__( 'Visualizza traduzione EN', 'fp-multilanguage' ) . '">🇬🇧 Visualizza</a>';
				echo '</span> | ';
				echo '<span class="edit">';
				echo '<a href="' . esc_url( get_edit_post_link( $translation_id ) ) . '" title="' . esc_attr__( 'Modifica traduzione EN', 'fp-multilanguage' ) . '">✏️ Modifica</a>';
				echo '</span>';
				echo '</div>';
			} else {
				// Partial translation
				echo '<span class="fpml-status-badge fpml-status-partial" title="' . esc_attr__( 'Traduzione parziale o non ancora completata', 'fp-multilanguage' ) . '">';
				echo '<span class="fpml-badge-icon">⚠</span> ';
				echo '<span class="fpml-badge-text">' . esc_html__( 'Parziale', 'fp-multilanguage' ) . '</span>';
				echo '</span>';
				echo '<div class="row-actions">';
				echo '<span class="edit">';
				echo '<a href="' . esc_url( get_edit_post_link( $translation_id ) ) . '" title="' . esc_attr__( 'Completa la traduzione', 'fp-multilanguage' ) . '">✏️ Completa</a>';
				echo '</span>';
				echo '</div>';
			}
			
			echo '</div>';
		} else {
			// Not translated
			echo '<span class="fpml-status-badge fpml-status-not-translated" title="' . esc_attr__( 'Nessuna traduzione disponibile. Clicca per creare la traduzione.', 'fp-multilanguage' ) . '">';
			echo '<span class="fpml-badge-icon">⚪</span> ';
			echo '<span class="fpml-badge-text">' . esc_html__( 'Non tradotto', 'fp-multilanguage' ) . '</span>';
			echo '</span>';
			echo '<div class="row-actions">';
			echo '<span class="translate">';
			echo '<a href="' . esc_url( get_edit_post_link( $post_id ) ) . '#fpml-translation-metabox" title="' . esc_attr__( 'Crea traduzione EN', 'fp-multilanguage' ) . '">🚀 Traduci</a>';
			echo '</span>';
			echo '</div>';
		}
	}

	/**
	 * Make column sortable.
	 *
	 * @param array<string, string> $columns Sortable columns.
	 * @return array<string, string>
	 */
	public function sortable_column( array $columns ): array {
		$columns['fpml_translation'] = 'fpml_translation_status';
		return $columns;
	}

	/**
	 * Sort by translation status.
	 *
	 * @param \WP_Query $query Query object.
	 *
	 * @return void
	 */
	public function sort_by_translation_status( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$orderby = $query->get( 'orderby' );
		
		if ( 'fpml_translation_status' === $orderby ) {
			$query->set( 'meta_key', '_fpml_pair_id' );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}

	/**
	 * Add column styles.
	 *
	 * @return void
	 */
	public function column_styles(): void {
		$screen = get_current_screen();
		
		if ( ! $screen || ! in_array( $screen->id, array( 'edit-post', 'edit-page' ), true ) ) {
			return;
		}
		
		?>
		<style>
			.column-fpml_translation {
				width: 160px;
			}
			.fpml-translation-status .row-actions {
				margin-top: 4px;
			}
			.fpml-status-badge {
				display: inline-flex;
				align-items: center;
				gap: 4px;
				padding: 4px 8px;
				border-radius: 4px;
				font-size: 11px;
				font-weight: 600;
				line-height: 1.4;
				cursor: help;
				transition: all 0.2s ease;
			}
			.fpml-status-badge .fpml-badge-icon {
				font-size: 12px;
			}
			.fpml-status-badge .fpml-badge-text {
				white-space: nowrap;
			}
			.fpml-status-translated {
				background: #d1fae5;
				color: #065f46;
			}
			.fpml-status-partial {
				background: #fef3c7;
				color: #92400e;
			}
			.fpml-status-pending {
				background: #fde68a;
				color: #78350f;
			}
			.fpml-status-not-translated {
				background: #f3f4f6;
				color: #6b7280;
			}
			.fpml-status-error {
				background: #fee2e2;
				color: #991b1b;
			}
			.fpml-status-badge:hover {
				opacity: 0.8;
				transform: translateY(-1px);
			}
			.fpml-translation-progress {
				margin-top: 6px;
			}
			.fpml-progress-bar-mini {
				width: 100%;
				height: 4px;
				background: #e5e7eb;
				border-radius: 2px;
				overflow: hidden;
			}
			.fpml-progress-fill-mini {
				height: 100%;
				background: linear-gradient(90deg, #f59e0b 0%, #fbbf24 100%);
				border-radius: 2px;
			}
			@keyframes fpml-pulse {
				0%, 100% {
					opacity: 1;
				}
				50% {
					opacity: 0.5;
				}
			}
			.fpml-translation-status .row-actions a {
				text-decoration: none;
			}
			.fpml-translation-status .row-actions a:hover {
				text-decoration: underline;
			}
		</style>
		<?php
	}
}

