<?php
/**
 * Traduzione automatica alla pubblicazione (Feature Killer #1).
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 * @since 0.4.0
 * @since 0.10.0 Refactored to use modular components.
 */

namespace FP\Multilanguage;

use FP\Multilanguage\Core\ContainerAwareTrait;
use FP\Multilanguage\AutoTranslate\TranslationExecutor;
use FP\Multilanguage\AutoTranslate\MetaBoxRenderer;
use FP\Multilanguage\AutoTranslate\ColumnManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestisce la traduzione automatica immediata al publish.
 *
 * @since 0.4.0
 * @since 0.10.0 Refactored to use modular components.
 */
class AutoTranslate {
	use ContainerAwareTrait;
	/**
	 * Meta key for auto-translate.
	 */
	const META_AUTO_TRANSLATE = '_fpml_auto_translate_on_publish';

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Settings reference.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * Logger reference.
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Queue reference.
	 *
	 * @var \FPML_Queue
	 */
	protected $queue;

	/**
	 * Translation executor.
	 *
	 * @since 0.10.0
	 *
	 * @var TranslationExecutor
	 */
	protected TranslationExecutor $executor;

	/**
	 * Meta box renderer.
	 *
	 * @since 0.10.0
	 *
	 * @var MetaBoxRenderer
	 */
	protected MetaBoxRenderer $meta_box;

	/**
	 * Column manager.
	 *
	 * @since 0.10.0
	 *
	 * @var ColumnManager
	 */
	protected ColumnManager $column_manager;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 0.4.0
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
	 *
	 * @since 0.10.0
	 */
	protected function __construct() {
		$container = $this->getContainer();
		$this->settings = $container && $container->has( 'options' ) ? $container->get( 'options' ) : Settings::instance();
		$this->logger = $container && $container->has( 'logger' ) ? $container->get( 'logger' ) : fpml_get_logger();
		$this->queue = $container && $container->has( 'queue' ) ? $container->get( 'queue' ) : fpml_get_queue();

		// Initialize modules
		$this->executor = new TranslationExecutor( $this->logger, $this->queue );
		$this->meta_box = new MetaBoxRenderer();
		$this->column_manager = new ColumnManager();

		// Hook on post status transition
		add_action( 'transition_post_status', array( $this, 'on_post_published' ), 10, 3 );

		// Meta box in editor
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );

		// Column in post list
		add_filter( 'manage_posts_columns', array( $this->column_manager, 'add_column' ) );
		add_filter( 'manage_pages_columns', array( $this->column_manager, 'add_column' ) );
		add_action( 'manage_posts_custom_column', array( $this->column_manager, 'render_column' ), 10, 2 );
		add_action( 'manage_pages_custom_column', array( $this->column_manager, 'render_column' ), 10, 2 );

		// Quick edit support
		add_action( 'quick_edit_custom_box', array( $this, 'quick_edit_custom_box' ), 10, 2 );
		add_action( 'admin_footer', array( $this, 'quick_edit_javascript' ) );
	}

	/**
	 * Callback when a post is published.
	 *
	 * @since 0.4.0
	 * @since 0.10.0 Delegates to TranslationExecutor.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post object.
	 * @return void
	 */
	public function on_post_published( string $new_status, string $old_status, \WP_Post $post ): void {
		// Only when publishing for the first time or republishing
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		// Check if it's a translation
		if ( get_post_meta( $post->ID, '_fpml_is_translation', true ) ) {
			return;
		}

		// Check if auto-translate is enabled globally or for this post
		$global_enabled = $this->settings ? $this->settings->get( 'auto_translate_on_publish', false ) : false;
		$post_enabled   = get_post_meta( $post->ID, self::META_AUTO_TRANSLATE, true );

		if ( ! $global_enabled && ! $post_enabled ) {
			return;
		}

		// Translate immediately in synchronous mode (high priority)
		$this->executor->translate_immediately( $post );
	}

	/**
	 * Add meta box in editor.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'\FPML_auto_translate',
				__( 'Traduzione Automatica', 'fp-multilanguage' ),
				array( $this->meta_box, 'render_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Save meta box.
	 *
	 * @since 0.4.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_meta_box( int $post_id, \WP_Post $post ): void {
		// Verify nonce
		if ( ! isset( $_POST['\FPML_auto_translate_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['\FPML_auto_translate_nonce'] ) ), '\FPML_auto_translate_meta' ) ) {
			return;
		}

		// Check autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save meta
		$auto_translate = isset( $_POST['\FPML_auto_translate_on_publish'] ) ? '1' : '';
		update_post_meta( $post_id, self::META_AUTO_TRANSLATE, $auto_translate );
	}

	/**
	 * Quick edit custom box.
	 *
	 * @since 0.4.0
	 *
	 * @param string $column_name Column name.
	 * @param string $post_type   Post type.
	 * @return void
	 */
	public function quick_edit_custom_box( string $column_name, string $post_type ): void {
		if ( '\FPML_auto_translate' !== $column_name ) {
			return;
		}

		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<label>
					<input type="checkbox" name="\FPML_auto_translate_on_publish" value="1" />
					<span class="checkbox-title"><?php esc_html_e( 'Auto-Traduzione', 'fp-multilanguage' ); ?></span>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Quick edit JavaScript.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function quick_edit_javascript(): void {
		global $current_screen;

		if ( ! $current_screen || 'edit' !== $current_screen->base ) {
			return;
		}

		?>
		<script type="text/javascript">
		(function($) {
			var $wp_inline_edit = inlineEditPost.edit;
			inlineEditPost.edit = function(id) {
				$wp_inline_edit.apply(this, arguments);
				var post_id = 0;
				if (typeof(id) == 'object') {
					post_id = parseInt(this.getId(id));
				}
				if (post_id > 0) {
					var $row = $('#post-' + post_id);
					var $auto_translate = $row.find('.column-\\FPML_auto_translate .dashicons-yes').length > 0;
					$('input[name="\\FPML_auto_translate_on_publish"]').prop('checked', $auto_translate);
				}
			};
		})(jQuery);
		</script>
		<?php
	}
}
