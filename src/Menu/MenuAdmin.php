<?php
/**
 * Menu Admin - Handles admin UI for menu synchronization.
 *
 * @package FP_Multilanguage
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

namespace FP\Multilanguage\Menu;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles admin UI for menu synchronization.
 *
 * @since 0.10.0
 */
class MenuAdmin {
	/**
	 * Enqueue admin scripts for nav-menus screen.
	 *
	 * @param string $hook Current admin page.
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		if ( 'nav-menus.php' !== $hook ) {
			return;
		}

		// Inline script for menu sync UI
		?>
		<script>
		jQuery(document).ready(function($) {
			// Add sync status to each menu in nav-menus.php
			const currentMenuId = $('#menu').val();
			
			if (!currentMenuId) return;
			
			// Get EN menu status
			$.post(ajaxurl, {
				action: 'fpml_get_menu_status',
				menu_id: currentMenuId,
				_wpnonce: '<?php echo esc_js( wp_create_nonce( 'fpml_menu_status' ) ); ?>'
			}, function(response) {
				if (response.success && response.data.has_en_menu) {
					// Add notice after menu name
					$('#menu-name').after(
						'<p class="fpml-menu-status" style="margin:10px 0; padding:12px; background:#f0f9ff; border-left:4px solid #0ea5e9; border-radius:4px;">' +
						'<strong>🌍 Menu Inglese:</strong> ' +
						'<a href="nav-menus.php?action=edit&menu=' + response.data.en_menu_id + '" target="_blank">' +
						response.data.en_menu_name + ' (' + response.data.items_count + ' items)' +
						'</a>' +
						'<span style="margin-left:10px; color:#059669;">✓ Sincronizzato</span>' +
						'</p>'
					);
				} else {
					$('#menu-name').after(
						'<p class="fpml-menu-status" style="margin:10px 0; padding:10px; background:#fef3c7; border-left:4px solid #f59e0b; border-radius:4px;">' +
						'<strong>🌍 Menu Inglese:</strong> Sarà creato automaticamente al salvataggio' +
						'</p>'
					);
				}
			});
		});
		</script>
		<style>
		.fpml-menu-status {
			font-size: 13px;
		}
		.fpml-menu-status a {
			font-weight: 600;
			text-decoration: none;
		}
		</style>
		<?php
	}

	/**
	 * Admin notice about menu sync.
	 *
	 * @return void
	 */
	public function menu_sync_notice(): void {
		// Show only once
		if ( get_option( 'fpml_menu_sync_notice_dismissed' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'nav-menus' !== $screen->id ) {
			return;
		}

		?>
		<div class="notice notice-info is-dismissible" data-dismissible="fpml-menu-sync">
			<p>
				<strong>🧭 FP Multilanguage - Menu Navigation</strong><br>
				I menu vengono automaticamente sincronizzati in inglese. 
				Ogni modifica al menu italiano sarà replicata nella versione EN.
				Mega menu Salient (icone, stili, colonne) vengono preservati.
			</p>
		</div>
		<script>
		jQuery(document).on('click', '[data-dismissible="fpml-menu-sync"] .notice-dismiss', function() {
			jQuery.post(ajaxurl, {
				action: 'fpml_dismiss_menu_notice'
			});
			update_option('fpml_menu_sync_notice_dismissed', '1');
		});
		</script>
		<?php
	}
}
















