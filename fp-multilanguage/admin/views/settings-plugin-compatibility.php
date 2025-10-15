<?php
/**
 * Admin view: Plugin Compatibility Detection
 *
 * @package FP_Multilanguage
 * @since 0.4.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$detector = FPML_Plugin_Detector::instance();
$detected = $detector->get_detected_plugins();
$summary  = $detector->get_detection_summary();

?>

<div class="wrap fpml-settings-wrap">
	<h1><?php esc_html_e( 'Compatibilità Plugin', 'fp-multilanguage' ); ?></h1>

	<div class="fpml-compatibility-header">
		<p class="description">
			<?php esc_html_e( 'FP Multilanguage rileva automaticamente i plugin installati e traduce i loro campi personalizzati senza configurazione manuale.', 'fp-multilanguage' ); ?>
		</p>
		
		<button id="fpml-trigger-detection" class="button button-secondary">
			<span class="dashicons dashicons-update"></span>
			<?php esc_html_e( 'Rileva Plugin', 'fp-multilanguage' ); ?>
		</button>
	</div>

	<div class="fpml-compatibility-stats">
		<div class="fpml-stat-card">
			<span class="fpml-stat-number"><?php echo esc_html( $summary['total'] ); ?></span>
			<span class="fpml-stat-label"><?php esc_html_e( 'Plugin Rilevati', 'fp-multilanguage' ); ?></span>
		</div>
	</div>

	<?php if ( empty( $detected ) ) : ?>
		<div class="notice notice-info inline">
			<p><?php esc_html_e( 'Nessun plugin compatibile rilevato. Installa plugin come Yoast SEO, Rank Math, Elementor, WooCommerce, ecc.', 'fp-multilanguage' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped fpml-compatibility-table">
			<thead>
				<tr>
					<th class="column-plugin"><?php esc_html_e( 'Plugin', 'fp-multilanguage' ); ?></th>
					<th class="column-status"><?php esc_html_e( 'Stato', 'fp-multilanguage' ); ?></th>
					<th class="column-fields"><?php esc_html_e( 'Campi Rilevati', 'fp-multilanguage' ); ?></th>
					<th class="column-actions"><?php esc_html_e( 'Azioni', 'fp-multilanguage' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $detected as $slug => $plugin ) : ?>
					<tr data-plugin="<?php echo esc_attr( $slug ); ?>">
						<td class="column-plugin">
							<strong><?php echo esc_html( $plugin['name'] ); ?></strong>
							<div class="row-actions">
								<span class="slug"><?php echo esc_html( $slug ); ?></span>
							</div>
						</td>
						<td class="column-status">
							<span class="fpml-badge fpml-badge-success">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Attivo', 'fp-multilanguage' ); ?>
							</span>
						</td>
						<td class="column-fields">
							<?php
							$field_count = count( $plugin['fields'] );
							if ( $field_count > 0 ) {
								printf(
									/* translators: %d: number of fields */
									esc_html( _n( '%d campo', '%d campi', $field_count, 'fp-multilanguage' ) ),
									$field_count
								);
								?>
								<button class="button-link fpml-show-fields" data-plugin="<?php echo esc_attr( $slug ); ?>">
									<?php esc_html_e( 'Mostra', 'fp-multilanguage' ); ?>
								</button>
							<?php } else { ?>
								<em><?php esc_html_e( 'Rilevamento dinamico', 'fp-multilanguage' ); ?></em>
							<?php } ?>
						</td>
						<td class="column-actions">
							<button class="button button-small fpml-test-plugin" data-plugin="<?php echo esc_attr( $slug ); ?>">
								<?php esc_html_e( 'Test', 'fp-multilanguage' ); ?>
							</button>
						</td>
					</tr>
					<?php if ( ! empty( $plugin['fields'] ) ) : ?>
						<tr class="fpml-fields-row fpml-fields-<?php echo esc_attr( $slug ); ?>" style="display:none;">
							<td colspan="4">
								<div class="fpml-fields-list">
									<h4><?php esc_html_e( 'Campi Personalizzati:', 'fp-multilanguage' ); ?></h4>
									<ul>
										<?php foreach ( $plugin['fields'] as $field ) : ?>
											<li><code><?php echo esc_html( $field ); ?></code></li>
										<?php endforeach; ?>
									</ul>
								</div>
							</td>
						</tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<div class="fpml-compatibility-supported">
		<h2><?php esc_html_e( 'Plugin Supportati', 'fp-multilanguage' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'FP Multilanguage supporta automaticamente questi plugin quando sono installati:', 'fp-multilanguage' ); ?>
		</p>

		<div class="fpml-supported-grid">
			<!-- SEO -->
			<div class="fpml-supported-category">
				<h3><span class="dashicons dashicons-search"></span> <?php esc_html_e( 'SEO', 'fp-multilanguage' ); ?></h3>
				<ul>
					<li>Yoast SEO</li>
					<li>Rank Math SEO</li>
					<li>All in One SEO</li>
					<li>SEOPress</li>
				</ul>
			</div>

			<!-- Page Builders -->
			<div class="fpml-supported-category">
				<h3><span class="dashicons dashicons-layout"></span> <?php esc_html_e( 'Page Builders', 'fp-multilanguage' ); ?></h3>
				<ul>
					<li>Elementor</li>
					<li>WPBakery</li>
					<li>Beaver Builder</li>
					<li>Oxygen Builder</li>
				</ul>
			</div>

			<!-- E-commerce -->
			<div class="fpml-supported-category">
				<h3><span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'E-commerce', 'fp-multilanguage' ); ?></h3>
				<ul>
					<li>WooCommerce</li>
					<li>Easy Digital Downloads</li>
				</ul>
			</div>

			<!-- Forms -->
			<div class="fpml-supported-category">
				<h3><span class="dashicons dashicons-feedback"></span> <?php esc_html_e( 'Forms', 'fp-multilanguage' ); ?></h3>
				<ul>
					<li>WPForms</li>
					<li>Gravity Forms</li>
					<li>Ninja Forms</li>
					<li>Contact Form 7</li>
				</ul>
			</div>

			<!-- Custom Fields -->
			<div class="fpml-supported-category">
				<h3><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Custom Fields', 'fp-multilanguage' ); ?></h3>
				<ul>
					<li>Advanced Custom Fields (ACF)</li>
					<li>Meta Box</li>
					<li>Pods</li>
				</ul>
			</div>

			<!-- Other -->
			<div class="fpml-supported-category">
				<h3><span class="dashicons dashicons-admin-plugins"></span> <?php esc_html_e( 'Altri', 'fp-multilanguage' ); ?></h3>
				<ul>
					<li>The Events Calendar</li>
					<li>LearnDash</li>
				</ul>
			</div>
		</div>
	</div>

	<div class="fpml-compatibility-custom">
		<h2><?php esc_html_e( 'Aggiungi Plugin Personalizzato', 'fp-multilanguage' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Puoi aggiungere il supporto per qualsiasi plugin usando il filtro fpml_plugin_detection_rules nel tuo tema o plugin.', 'fp-multilanguage' ); ?>
		</p>

		<pre class="fpml-code-example">
add_filter( 'fpml_plugin_detection_rules', function( $rules ) {
    $rules['my_plugin'] = array(
        'name'   => 'Il Mio Plugin',
        'check'  => array( 'class' => 'MyPlugin' ),
        'fields' => array(
            '_my_custom_field_1',
            '_my_custom_field_2',
        ),
        'priority' => 10,
    );
    return $rules;
} );
		</pre>
	</div>
</div>

<style>
.fpml-compatibility-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 20px;
	padding: 15px;
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
}

.fpml-compatibility-stats {
	display: flex;
	gap: 20px;
	margin-bottom: 20px;
}

.fpml-stat-card {
	flex: 1;
	padding: 20px;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: #fff;
	border-radius: 8px;
	text-align: center;
	box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.fpml-stat-number {
	display: block;
	font-size: 48px;
	font-weight: bold;
	margin-bottom: 5px;
}

.fpml-stat-label {
	display: block;
	font-size: 14px;
	opacity: 0.9;
}

.fpml-compatibility-table {
	margin-top: 20px;
}

.fpml-badge {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	padding: 4px 12px;
	border-radius: 12px;
	font-size: 12px;
	font-weight: 600;
}

.fpml-badge-success {
	background: #d4edda;
	color: #155724;
}

.fpml-badge .dashicons {
	width: 16px;
	height: 16px;
	font-size: 16px;
}

.fpml-show-fields {
	margin-left: 10px;
	color: #2271b1;
}

.fpml-fields-list {
	padding: 15px;
	background: #f8f9fa;
	border-left: 4px solid #2271b1;
}

.fpml-fields-list ul {
	margin: 10px 0;
	padding-left: 20px;
}

.fpml-fields-list li {
	margin: 5px 0;
}

.fpml-supported-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 20px;
	margin-top: 20px;
}

.fpml-supported-category {
	padding: 20px;
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
}

.fpml-supported-category h3 {
	display: flex;
	align-items: center;
	gap: 8px;
	margin: 0 0 15px 0;
	color: #1d2327;
	font-size: 14px;
	font-weight: 600;
}

.fpml-supported-category .dashicons {
	color: #2271b1;
}

.fpml-supported-category ul {
	margin: 0;
	padding-left: 20px;
}

.fpml-supported-category li {
	margin: 8px 0;
	color: #50575e;
}

.fpml-compatibility-custom {
	margin-top: 40px;
	padding: 20px;
	background: #fff;
	border: 1px solid #ccd0d4;
	border-radius: 4px;
}

.fpml-code-example {
	background: #282c34;
	color: #abb2bf;
	padding: 15px;
	border-radius: 4px;
	overflow-x: auto;
	font-size: 13px;
	line-height: 1.6;
}

.dashicons.spin {
	animation: fpml-spin 1s linear infinite;
}

@keyframes fpml-spin {
	from {
		transform: rotate(0deg);
	}
	to {
		transform: rotate(360deg);
	}
}
</style>

<script>
jQuery(document).ready(function($) {
	// Assicurati che ajaxurl sia definito
	if (typeof ajaxurl === 'undefined') {
		var ajaxurl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
	}

	// Toggle field list
	$('.fpml-show-fields').on('click', function(e) {
		e.preventDefault();
		var plugin = $(this).data('plugin');
		$('.fpml-fields-' + plugin).toggle();
		$(this).text(function(i, text) {
			return text === '<?php echo esc_js( __( 'Mostra', 'fp-multilanguage' ) ); ?>' ? 
				'<?php echo esc_js( __( 'Nascondi', 'fp-multilanguage' ) ); ?>' : 
				'<?php echo esc_js( __( 'Mostra', 'fp-multilanguage' ) ); ?>';
		});
	});

	// Trigger detection
	$('#fpml-trigger-detection').on('click', function(e) {
		e.preventDefault();
		var $button = $(this);
		$button.prop('disabled', true).find('.dashicons').addClass('spin');

		$.post(ajaxurl, {
			action: 'fpml_trigger_detection',
			nonce: '<?php echo esc_js( wp_create_nonce( 'fpml_trigger_detection' ) ); ?>'
		}, function(response) {
			if (response.success) {
				location.reload();
			} else {
				var errorMsg = response.data && response.data.message ? response.data.message : 'Errore durante il rilevamento';
				alert(errorMsg);
				$button.prop('disabled', false).find('.dashicons').removeClass('spin');
			}
		}).fail(function() {
			alert('Errore di connessione. Riprova.');
			$button.prop('disabled', false).find('.dashicons').removeClass('spin');
		});
	});

	// Test plugin
	$('.fpml-test-plugin').on('click', function() {
		var plugin = $(this).data('plugin');
		alert('Test per ' + plugin + ' - Funzionalità in arrivo!');
	});
});
</script>
