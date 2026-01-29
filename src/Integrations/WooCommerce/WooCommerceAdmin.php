<?php
/**
 * WooCommerce Admin - Handles admin UI for WooCommerce integration.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Integrations\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles admin UI for WooCommerce integration.
 *
 * @since 0.10.0
 */
class WooCommerceAdmin {
	/**
	 * Admin notice about WooCommerce integration.
	 *
	 * @since 0.10.0
	 *
	 * @return void
	 */
	public function integration_notice(): void {
		// Show only once
		if ( get_option( 'fpml_wc_integration_notice_dismissed' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}

		?>
		<div class="notice notice-success is-dismissible" data-dismissible="fpml-wc-integration">
			<p>
				<strong>🛒 FP Multilanguage + WooCommerce - Integrazione Completa</strong><br>
				Sincronizzati automaticamente: <strong>Variations, Attributes, Gallery, Upsell/Cross-sell, 
				Downloadable files, Product tabs, Taxonomies (Categories/Tags)</strong>.
				Supporto completo per prodotti Simple, Variable, Grouped, External/Affiliate, Downloadable.
			</p>
		</div>
		<script>
		jQuery(document).on('click', '[data-dismissible="fpml-wc-integration"] .notice-dismiss', function() {
			jQuery.post(ajaxurl, {
				action: 'fpml_dismiss_wc_notice'
			});
		});
		</script>
		<?php
	}
}
















