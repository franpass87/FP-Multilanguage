<?php
/**
 * Translation History UI - Show version history and diff viewer.
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

namespace FP\Multilanguage\Admin;

use FP\Multilanguage\Core\TranslationVersioning;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UI for viewing and managing translation versions.
 *
 * @since 0.5.0
 */
class TranslationHistoryUI {
	/**
	 * Singleton instance.
	 *
	 * @var TranslationHistoryUI|null
	 */
	protected static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return TranslationHistoryUI
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
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'wp_ajax_fpml_restore_version', array( $this, 'ajax_restore_version' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add meta box for translation history.
	 */
	public function add_meta_box() {
		$post_types = array( 'post', 'page' );
		
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'fpml_translation_history',
				__( 'Cronologia Traduzioni', 'fp-multilanguage' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'low'
			);
		}
	}

	/**
	 * Render meta box.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_meta_box( $post ) {
		$versioning = fpml_get_translation_versioning();
		$versions   = $versioning->get_versions( $post->ID, 'content' );

		if ( empty( $versions ) ) {
			echo '<p>' . esc_html__( 'Nessuna versione disponibile.', 'fp-multilanguage' ) . '</p>';
			return;
		}
		?>
		<div class="fpml-translation-history">
			<label for="fpml-version-select"><?php esc_html_e( 'Versioni disponibili:', 'fp-multilanguage' ); ?></label>
			<select id="fpml-version-select" class="widefat">
				<option value=""><?php esc_html_e( '-- Seleziona versione --', 'fp-multilanguage' ); ?></option>
				<?php foreach ( $versions as $version ) : ?>
					<option value="<?php echo esc_attr( $version['version_id'] ); ?>">
						<?php echo esc_html( $version['timestamp'] ); ?> 
						(v<?php echo esc_html( $version['version_id'] ); ?>)
					</option>
				<?php endforeach; ?>
			</select>

			<div id="fpml-version-preview" style="display:none; margin-top:10px;">
				<textarea readonly class="widefat" rows="10" id="fpml-version-content"></textarea>
				<p>
					<button type="button" class="button button-secondary" id="fpml-restore-version" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
						<?php esc_html_e( 'Ripristina questa versione', 'fp-multilanguage' ); ?>
					</button>
				</p>
			</div>
		</div>
		<?php
		wp_nonce_field( 'fpml_version_history', 'fpml_version_nonce' );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @param string $hook Current page.
	 */
	public function enqueue_scripts( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_add_inline_script(
			'jquery',
			"
			jQuery(document).ready(function($) {
				$('#fpml-version-select').on('change', function() {
					const versionId = $(this).val();
					if (!versionId) {
						$('#fpml-version-preview').hide();
						return;
					}

					// Load version content via REST
					$.get('" . rest_url( 'fpml/v1/version/' ) . "' + versionId, function(data) {
						$('#fpml-version-content').val(data.content);
						$('#fpml-version-preview').show();
					});
				});

				$('#fpml-restore-version').on('click', function() {
					if (!confirm('" . esc_js( __( 'Ripristinare questa versione?', 'fp-multilanguage' ) ) . "')) {
						return;
					}

					const versionId = $('#fpml-version-select').val();
					const postId = $(this).data('post-id');

					$.post(ajaxurl, {
						action: 'fpml_restore_version',
						post_id: postId,
						version_id: versionId,
						_wpnonce: $('#fpml_version_nonce').val()
					}, function(response) {
						if (response.success) {
							alert(response.data.message);
							location.reload();
						} else {
							alert(response.data.message);
						}
					});
				});
			});
			"
		);
	}

	/**
	 * AJAX: Restore version.
	 */
	public function ajax_restore_version() {
		check_ajax_referer( 'fpml_version_history', '_wpnonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
		}

		$post_id    = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$version_id = isset( $_POST['version_id'] ) ? absint( $_POST['version_id'] ) : 0;

		if ( ! $post_id || ! $version_id ) {
			wp_send_json_error( array( 'message' => __( 'Parametri mancanti.', 'fp-multilanguage' ) ) );
		}

		$versioning = fpml_get_translation_versioning();
		$restored   = $versioning->restore_version( $post_id, $version_id, 'content' );

		if ( is_wp_error( $restored ) ) {
			wp_send_json_error( array( 'message' => $restored->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Versione ripristinata con successo!', 'fp-multilanguage' ) ) );
	}
}

