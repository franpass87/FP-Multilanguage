<?php
/**
 * Auto Detection Notices - Shows admin notices for detected content.
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
 * Shows admin notices for detected content.
 *
 * @since 0.10.0
 */
class DetectionNotices {
	/**
	 * Storage instance.
	 *
	 * @var DetectionStorage
	 */
	protected DetectionStorage $storage;

	/**
	 * Constructor.
	 *
	 * @param DetectionStorage $storage Storage instance.
	 */
	public function __construct( DetectionStorage $storage ) {
		$this->storage = $storage;
	}

	/**
	 * Mostra admin notice per nuovi contenuti rilevati.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function show_detection_notices(): void {
		$detected_post_types = $this->storage->get_detected_post_types();
		$detected_taxonomies = $this->storage->get_detected_taxonomies();

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
					action: '\FPML_accept_post_type',
					post_type: postType,
					nonce: '<?php echo esc_js( wp_create_nonce( '\FPML_auto_detection' ) ); ?>'
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
					action: '\FPML_ignore_post_type',
					post_type: postType,
					nonce: '<?php echo esc_js( wp_create_nonce( '\FPML_auto_detection' ) ); ?>'
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
					action: '\FPML_accept_taxonomy',
					taxonomy: taxonomy,
					nonce: '<?php echo esc_js( wp_create_nonce( '\FPML_auto_detection' ) ); ?>'
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
					action: '\FPML_ignore_taxonomy',
					taxonomy: taxonomy,
					nonce: '<?php echo esc_js( wp_create_nonce( '\FPML_auto_detection' ) ); ?>'
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
}
















