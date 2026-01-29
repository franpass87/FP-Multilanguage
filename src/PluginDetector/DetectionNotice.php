<?php
/**
 * Plugin Detector Detection Notice - Shows admin notices for detected plugins.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\PluginDetector;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows admin notices for detected plugins.
 *
 * @since 0.10.0
 */
class DetectionNotice {
	/**
	 * Show detection notice.
	 *
	 * @since 0.10.0
	 *
	 * @param array $detected_plugins Detected plugins.
	 * @return void
	 */
	public function show_detection_notice( array $detected_plugins ): void {
		// Only show on FP Multilanguage admin pages
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'fp-multilanguage' ) ) {
			return;
		}

		// Check if user dismissed the notice
		$dismissed = get_user_meta( get_current_user_id(), '\FPML_plugin_detection_dismissed', true );
		if ( $dismissed ) {
			return;
		}

		// Only show if plugins were detected
		if ( empty( $detected_plugins ) ) {
			return;
		}

		$plugin_names = array_column( $detected_plugins, 'name' );
		$count = count( $plugin_names );

		?>
		<div class="notice notice-info is-dismissible fpml-plugin-detection-notice">
			<p>
				<strong><?php esc_html_e( 'FP Multilanguage - Plugin rilevati', 'fp-multilanguage' ); ?></strong>
			</p>
			<p>
				<?php
				printf(
					/* translators: %d: number of detected plugins */
					esc_html( _n(
						'È stato rilevato %d plugin compatibile con campi personalizzati:',
						'Sono stati rilevati %d plugin compatibili con campi personalizzati:',
						$count,
						'fp-multilanguage'
					) ),
					esc_html( $count )
				);
				?>
			</p>
			<ul style="list-style: disc; margin-left: 20px;">
				<?php foreach ( $plugin_names as $name ) : ?>
					<li><?php echo esc_html( $name ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p>
				<?php esc_html_e( 'I campi personalizzati di questi plugin sono stati automaticamente aggiunti alla whitelist per la traduzione.', 'fp-multilanguage' ); ?>
			</p>
		</div>
		<script>
		jQuery(document).ready(function($) {
			$('.fpml-plugin-detection-notice').on('click', '.notice-dismiss', function() {
				$.post(ajaxurl, {
					action: 'fpml_dismiss_plugin_detection',
					nonce: '<?php echo esc_js( wp_create_nonce( 'fpml_dismiss_plugin_detection' ) ); ?>'
				});
			});
		});
		</script>
		<?php
	}
}















