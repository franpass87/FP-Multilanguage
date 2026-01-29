<?php
/**
 * Preview Inline Translation.
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

namespace FP\Multilanguage\Admin;

use FP\Multilanguage\Providers\ProviderOpenAI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PreviewInline {
	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function __construct() {
		add_action( 'edit_form_after_title', array( $this, 'add_preview_button' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_fpml_preview_translation', array( $this, 'ajax_preview' ) );
	}

	public function add_preview_button( $post ) {
		if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}
		?>
		<div class="fpml-preview-wrap" style="margin: 10px 0;">
			<button type="button" class="button button-secondary" id="fpml-preview-btn">
				🔍 <?php esc_html_e( 'Anteprima Traduzione', 'fp-multilanguage' ); ?>
			</button>
		</div>

		<div id="fpml-preview-modal" class="fpml-modal" style="display:none;">
			<div class="fpml-modal-content">
				<span class="fpml-close">&times;</span>
				<h2><?php esc_html_e( 'Anteprima Traduzione', 'fp-multilanguage' ); ?></h2>
				<div class="fpml-preview-container">
					<div class="fpml-preview-side">
						<h3><?php esc_html_e( 'Originale (IT)', 'fp-multilanguage' ); ?></h3>
						<div id="fpml-preview-original"></div>
					</div>
					<div class="fpml-preview-side">
						<h3><?php esc_html_e( 'Traduzione (EN)', 'fp-multilanguage' ); ?></h3>
						<div id="fpml-preview-translation">
							<div class="spinner is-active"></div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function enqueue_scripts( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_add_inline_script( 'jquery', "
			jQuery(document).ready(function($) {
				$('#fpml-preview-btn').on('click', function() {
					const content = typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor 
						? tinyMCE.activeEditor.getContent() 
						: $('#content').val();

					$('#fpml-preview-original').html(content);
					$('#fpml-preview-modal').show();

					$.post(ajaxurl, {
						action: 'fpml_preview_translation',
						content: content,
						nonce: '" . wp_create_nonce( 'fpml_preview' ) . "'
					}, function(response) {
						if (response.success) {
							$('#fpml-preview-translation').html(response.data.translation);
						} else {
							$('#fpml-preview-translation').html('<p class=\"error\">' + response.data.message + '</p>');
						}
					});
				});

				$('.fpml-close').on('click', function() {
					$('#fpml-preview-modal').hide();
				});
			});
		" );

		wp_add_inline_style( 'common', "
			.fpml-modal {
				display: none;
				position: fixed;
				z-index: 100000;
				left: 0;
				top: 0;
				width: 100%;
				height: 100%;
				background-color: rgba(0,0,0,0.7);
			}
			.fpml-modal-content {
				background-color: #fff;
				margin: 5% auto;
				padding: 30px;
				border: 1px solid #888;
				width: 90%;
				max-width: 1200px;
				border-radius: 8px;
			}
			.fpml-close {
				float: right;
				font-size: 28px;
				font-weight: bold;
				cursor: pointer;
			}
			.fpml-preview-container {
				display: flex;
				gap: 20px;
			}
			.fpml-preview-side {
				flex: 1;
				padding: 15px;
				background: #f5f5f5;
				border-radius: 4px;
			}
		" );
	}

	public function ajax_preview() {
		check_ajax_referer( 'fpml_preview', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-multilanguage' ) ) );
		}

		$content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';

		if ( empty( $content ) ) {
			wp_send_json_error( array( 'message' => __( 'Contenuto vuoto.', 'fp-multilanguage' ) ) );
		}

		$provider = new ProviderOpenAI();

		if ( ! $provider->is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'OpenAI non configurato.', 'fp-multilanguage' ) ) );
		}

		$translation = $provider->translate( strip_tags( $content ), 'it', 'en', 'general' );

		if ( is_wp_error( $translation ) ) {
			wp_send_json_error( array( 'message' => $translation->get_error_message() ) );
		}

		wp_send_json_success( array( 'translation' => wpautop( $translation ) ) );
	}
}

