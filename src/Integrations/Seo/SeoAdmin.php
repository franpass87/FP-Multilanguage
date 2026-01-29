<?php
/**
 * SEO Admin - Handles admin UI for SEO integration.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Integrations\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles admin UI for SEO integration.
 *
 * @since 0.10.0
 */
class SeoAdmin {
	/**
	 * Admin notice about integration.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function integration_notice(): void {
		// Show only once
		if ( get_option( 'fpml_seo_integration_notice_dismissed' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}

		?>
		<div class="notice notice-success is-dismissible" data-dismissible="fpml-seo-integration">
			<p>
				<strong>🎉 FP Multilanguage + FP SEO Manager v0.9.0</strong><br>
				Integrazione completa attiva! Sincronizzati automaticamente:
				<strong>Meta SEO, Keywords, AI Features, GEO data, Social meta, Schema FAQ/HowTo</strong>.
				Google Search Console metrics disponibili per entrambe le lingue nel metabox traduzioni.
			</p>
		</div>
		<script>
		jQuery(document).on('click', '[data-dismissible="fpml-seo-integration"] .notice-dismiss', function() {
			jQuery.post(ajaxurl, {
				action: 'fpml_dismiss_seo_notice'
			});
			jQuery.post(ajaxurl, {
				action: 'edit',
				_ajax_nonce: '<?php echo esc_js( wp_create_nonce( 'dismiss-notice' ) ); ?>',
				option: 'fpml_seo_integration_notice_dismissed',
				value: '1'
			});
		});
		</script>
		<?php
	}
}
















