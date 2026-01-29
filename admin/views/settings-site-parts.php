<?php
/**
 * Site Parts Translation Settings View
 *
 * @package FP_Multilanguage
 * @since 0.9.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get current translations status
$menus = wp_get_nav_menus();
$menu_items_count = 0;
$menu_translated_count = 0;

foreach ( $menus as $menu ) {
	$items = wp_get_nav_menu_items( $menu->term_id );
	if ( $items ) {
		$menu_items_count += count( $items );
		foreach ( $items as $item ) {
			if ( get_option( '_fpml_en_menu_item_' . $item->ID . '_title' ) ) {
				$menu_translated_count++;
			}
		}
	}
}

// Get widgets status
global $wp_registered_widgets;
$sidebars = wp_get_sidebars_widgets();
$widgets_count = 0;
$widgets_translated_count = 0;

foreach ( $sidebars as $sidebar_id => $widgets ) {
	if ( is_array( $widgets ) ) {
		$widgets_count += count( $widgets );
		foreach ( $widgets as $widget_id ) {
			if ( isset( $wp_registered_widgets[ $widget_id ] ) ) {
				$has_title = get_option( '_fpml_en_widget_' . $widget_id . '_title' );
				$has_text = get_option( '_fpml_en_widget_' . $widget_id . '_text' );
				if ( $has_title || $has_text ) {
					$widgets_translated_count++;
				}
			}
		}
	}
}

$nonce = wp_create_nonce( 'fpml_translate_site_part' );
?>

<div class="wrap">
	<h2><?php esc_html_e( 'Traduzione Parti del Sito', 'fp-multilanguage' ); ?></h2>
	
	<p class="description">
		<?php esc_html_e( 'Traduci automaticamente menu, widget e altre parti del sito che vengono visualizzate su /en/.', 'fp-multilanguage' ); ?>
	</p>

	<div class="fpml-site-parts-translator" style="margin-top: 20px;">
		
		<!-- Menu Translation -->
		<div class="postbox" style="margin-bottom: 20px;">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Menu', 'fp-multilanguage' ); ?></h2>
			</div>
			<div class="inside">
				<p>
					<?php
					printf(
						/* translators: 1: translated count, 2: total count */
						esc_html__( 'Elementi menu tradotti: %1$d / %2$d', 'fp-multilanguage' ),
						$menu_translated_count,
						$menu_items_count
					);
					?>
				</p>
				<p class="description">
					<?php esc_html_e( 'Traduce automaticamente i titoli degli elementi del menu che vengono visualizzati su /en/.', 'fp-multilanguage' ); ?>
				</p>
				<button type="button" class="button button-primary fpml-translate-part" data-part="menus" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					🚀 <?php esc_html_e( 'Traduci Menu', 'fp-multilanguage' ); ?>
				</button>
				<span class="fpml-translate-status" style="margin-left: 10px;"></span>
			</div>
		</div>

		<!-- Widget Translation -->
		<div class="postbox" style="margin-bottom: 20px;">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Widget', 'fp-multilanguage' ); ?></h2>
			</div>
			<div class="inside">
				<p>
					<?php
					printf(
						/* translators: 1: translated count, 2: total count */
						esc_html__( 'Widget tradotti: %1$d / %2$d', 'fp-multilanguage' ),
						$widgets_translated_count,
						$widgets_count
					);
					?>
				</p>
				<p class="description">
					<?php esc_html_e( 'Traduce automaticamente i titoli e il contenuto dei widget che vengono visualizzati su /en/.', 'fp-multilanguage' ); ?>
				</p>
				<button type="button" class="button button-primary fpml-translate-part" data-part="widgets" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					🚀 <?php esc_html_e( 'Traduci Widget', 'fp-multilanguage' ); ?>
				</button>
				<span class="fpml-translate-status" style="margin-left: 10px;"></span>
			</div>
		</div>

		<!-- Theme Options Translation -->
		<div class="postbox" style="margin-bottom: 20px;">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Opzioni Tema', 'fp-multilanguage' ); ?></h2>
			</div>
			<div class="inside">
				<p class="description">
					<?php esc_html_e( 'Traduce automaticamente le opzioni del tema (es. testi header, footer, copyright) che vengono visualizzate su /en/.', 'fp-multilanguage' ); ?>
				</p>
				<button type="button" class="button button-primary fpml-translate-part" data-part="theme-options" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					🚀 <?php esc_html_e( 'Traduci Opzioni Tema', 'fp-multilanguage' ); ?>
				</button>
				<span class="fpml-translate-status" style="margin-left: 10px;"></span>
			</div>
		</div>

		<!-- Plugins Translation -->
		<div class="postbox" style="margin-bottom: 20px;">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Plugin', 'fp-multilanguage' ); ?></h2>
			</div>
			<div class="inside">
				<p class="description">
					<?php esc_html_e( 'Traduce automaticamente le stringhe di plugin comuni (es. WooCommerce, Contact Form 7) che vengono visualizzate su /en/.', 'fp-multilanguage' ); ?>
				</p>
				<button type="button" class="button button-primary fpml-translate-part" data-part="plugins" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					🚀 <?php esc_html_e( 'Traduci Plugin', 'fp-multilanguage' ); ?>
				</button>
				<span class="fpml-translate-status" style="margin-left: 10px;"></span>
			</div>
		</div>

		<!-- Site Settings Translation -->
		<div class="postbox" style="margin-bottom: 20px;">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Impostazioni Sito', 'fp-multilanguage' ); ?></h2>
			</div>
			<div class="inside">
				<p class="description">
					<?php esc_html_e( 'Traduce automaticamente il nome del sito e la tagline che vengono visualizzati su /en/.', 'fp-multilanguage' ); ?>
				</p>
				<button type="button" class="button button-primary fpml-translate-part" data-part="site-settings" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					🚀 <?php esc_html_e( 'Traduci Impostazioni Sito', 'fp-multilanguage' ); ?>
				</button>
				<span class="fpml-translate-status" style="margin-left: 10px;"></span>
			</div>
		</div>

		<!-- Media Translation -->
		<div class="postbox" style="margin-bottom: 20px;">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Media', 'fp-multilanguage' ); ?></h2>
			</div>
			<div class="inside">
				<p class="description">
					<?php esc_html_e( 'Traduce automaticamente alt text, caption e descrizioni delle immagini che vengono visualizzate su /en/.', 'fp-multilanguage' ); ?>
				</p>
				<button type="button" class="button button-primary fpml-translate-part" data-part="media" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					🚀 <?php esc_html_e( 'Traduci Media', 'fp-multilanguage' ); ?>
				</button>
				<span class="fpml-translate-status" style="margin-left: 10px;"></span>
			</div>
		</div>

		<!-- Comments Translation -->
		<div class="postbox" style="margin-bottom: 20px;">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Commenti', 'fp-multilanguage' ); ?></h2>
			</div>
			<div class="inside">
				<p class="description">
					<?php esc_html_e( 'Traduce automaticamente il contenuto dei commenti che vengono visualizzati su /en/.', 'fp-multilanguage' ); ?>
				</p>
				<button type="button" class="button button-primary fpml-translate-part" data-part="comments" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					🚀 <?php esc_html_e( 'Traduci Commenti', 'fp-multilanguage' ); ?>
				</button>
				<span class="fpml-translate-status" style="margin-left: 10px;"></span>
			</div>
		</div>

		<!-- Customizer Translation -->
		<div class="postbox" style="margin-bottom: 20px;">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Customizer', 'fp-multilanguage' ); ?></h2>
			</div>
			<div class="inside">
				<p class="description">
					<?php esc_html_e( 'Traduce automaticamente le opzioni del personalizzatore tema che vengono visualizzate su /en/.', 'fp-multilanguage' ); ?>
				</p>
				<button type="button" class="button button-primary fpml-translate-part" data-part="customizer" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					🚀 <?php esc_html_e( 'Traduci Customizer', 'fp-multilanguage' ); ?>
				</button>
				<span class="fpml-translate-status" style="margin-left: 10px;"></span>
			</div>
		</div>

		<!-- Archives Translation -->
		<div class="postbox" style="margin-bottom: 20px;">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Archivi', 'fp-multilanguage' ); ?></h2>
			</div>
			<div class="inside">
				<p class="description">
					<?php esc_html_e( 'Traduce automaticamente i titoli e le descrizioni degli archivi (categorie, tag, autori, date) che vengono visualizzati su /en/.', 'fp-multilanguage' ); ?>
				</p>
				<button type="button" class="button button-primary fpml-translate-part" data-part="archives" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					🚀 <?php esc_html_e( 'Traduci Archivi', 'fp-multilanguage' ); ?>
				</button>
				<span class="fpml-translate-status" style="margin-left: 10px;"></span>
			</div>
		</div>

		<!-- Search Translation -->
		<div class="postbox" style="margin-bottom: 20px;">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Risultati Ricerca', 'fp-multilanguage' ); ?></h2>
			</div>
			<div class="inside">
				<p class="description">
					<?php esc_html_e( 'Traduce automaticamente i messaggi dei risultati di ricerca che vengono visualizzati su /en/.', 'fp-multilanguage' ); ?>
				</p>
				<button type="button" class="button button-primary fpml-translate-part" data-part="search" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					🚀 <?php esc_html_e( 'Traduci Ricerca', 'fp-multilanguage' ); ?>
				</button>
				<span class="fpml-translate-status" style="margin-left: 10px;"></span>
			</div>
		</div>

		<!-- 404 Translation -->
		<div class="postbox" style="margin-bottom: 20px;">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Pagine 404', 'fp-multilanguage' ); ?></h2>
			</div>
			<div class="inside">
				<p class="description">
					<?php esc_html_e( 'Traduce automaticamente i messaggi delle pagine 404 che vengono visualizzati su /en/.', 'fp-multilanguage' ); ?>
				</p>
				<button type="button" class="button button-primary fpml-translate-part" data-part="404" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					🚀 <?php esc_html_e( 'Traduci 404', 'fp-multilanguage' ); ?>
				</button>
				<span class="fpml-translate-status" style="margin-left: 10px;"></span>
			</div>
		</div>

		<!-- Breadcrumbs Translation -->
		<div class="postbox" style="margin-bottom: 20px;">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Breadcrumb', 'fp-multilanguage' ); ?></h2>
			</div>
			<div class="inside">
				<p class="description">
					<?php esc_html_e( 'Traduce automaticamente le etichette dei breadcrumb (Yoast SEO, Rank Math, AIOSEO) che vengono visualizzate su /en/.', 'fp-multilanguage' ); ?>
				</p>
				<button type="button" class="button button-primary fpml-translate-part" data-part="breadcrumbs" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					🚀 <?php esc_html_e( 'Traduci Breadcrumb', 'fp-multilanguage' ); ?>
				</button>
				<span class="fpml-translate-status" style="margin-left: 10px;"></span>
			</div>
		</div>

		<!-- Forms Translation -->
		<div class="postbox" style="margin-bottom: 20px;">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Form', 'fp-multilanguage' ); ?></h2>
			</div>
			<div class="inside">
				<p class="description">
					<?php esc_html_e( 'Traduce automaticamente labels e placeholders dei form (Contact Form 7, WPForms) che vengono visualizzati su /en/.', 'fp-multilanguage' ); ?>
				</p>
				<button type="button" class="button button-primary fpml-translate-part" data-part="forms" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					🚀 <?php esc_html_e( 'Traduci Form', 'fp-multilanguage' ); ?>
				</button>
				<span class="fpml-translate-status" style="margin-left: 10px;"></span>
			</div>
		</div>

		<!-- Authors Translation -->
		<div class="postbox" style="margin-bottom: 20px;">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Autori', 'fp-multilanguage' ); ?></h2>
			</div>
			<div class="inside">
				<p class="description">
					<?php esc_html_e( 'Traduce automaticamente le biografie degli autori che vengono visualizzate su /en/.', 'fp-multilanguage' ); ?>
				</p>
				<button type="button" class="button button-primary fpml-translate-part" data-part="authors" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					🚀 <?php esc_html_e( 'Traduci Autori', 'fp-multilanguage' ); ?>
				</button>
				<span class="fpml-translate-status" style="margin-left: 10px;"></span>
			</div>
		</div>

		<!-- Info Box -->
		<div class="notice notice-info" style="margin-top: 20px;">
			<p>
				<strong><?php esc_html_e( 'Come Funziona:', 'fp-multilanguage' ); ?></strong>
			</p>
			<ul style="list-style: disc; margin-left: 20px;">
				<li><?php esc_html_e( 'Le traduzioni vengono salvate automaticamente nel database WordPress.', 'fp-multilanguage' ); ?></li>
				<li><?php esc_html_e( 'Quando un utente visita /en/, i contenuti vengono automaticamente sostituiti con le versioni inglesi.', 'fp-multilanguage' ); ?></li>
				<li><?php esc_html_e( 'Puoi ritradurre in qualsiasi momento cliccando di nuovo il pulsante.', 'fp-multilanguage' ); ?></li>
				<li><?php esc_html_e( 'Le traduzioni vengono effettuate usando il provider configurato nelle impostazioni.', 'fp-multilanguage' ); ?></li>
			</ul>
		</div>

	</div>
</div>

<script>
jQuery(document).ready(function($) {
	$('.fpml-translate-part').on('click', function(e) {
		e.preventDefault();
		
		var $button = $(this);
		var $status = $button.siblings('.fpml-translate-status');
		var part = $button.data('part');
		var nonce = $button.data('nonce');
		
		// Disable button
		$button.prop('disabled', true);
		$status.html('<span class="spinner is-active" style="float:none;margin:0;"></span> ' + '<?php esc_html_e( 'Traduzione in corso...', 'fp-multilanguage' ); ?>');
		
		// Make AJAX request
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'fpml_translate_site_part',
				part: part,
				_wpnonce: nonce
			},
			success: function(response) {
				if (response.success) {
					$status.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
					// Reload page after 2 seconds to update counts
					setTimeout(function() {
						location.reload();
					}, 2000);
				} else {
					$status.html('<span style="color:red;">✗ ' + (response.data.message || '<?php esc_html_e( 'Errore durante la traduzione', 'fp-multilanguage' ); ?>') + '</span>');
					$button.prop('disabled', false);
				}
			},
			error: function() {
				$status.html('<span style="color:red;">✗ <?php esc_html_e( 'Errore di comunicazione con il server', 'fp-multilanguage' ); ?></span>');
				$button.prop('disabled', false);
			}
		});
	});
});
</script>

<style>
.fpml-site-parts-translator .postbox {
	max-width: 800px;
}
.fpml-site-parts-translator .postbox-header h2 {
	font-size: 14px;
	font-weight: 600;
	padding: 12px;
}
.fpml-site-parts-translator .inside {
	padding: 12px;
}
.fpml-translate-status {
	display: inline-block;
	vertical-align: middle;
}
</style>

