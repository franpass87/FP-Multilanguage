<?php
/**
 * Translations Management Dashboard.
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get translations data
$processor = \FPML_Processor::instance();
$queue = \FPML_Queue::instance();

// Get posts with translations
global $wpdb;

$posts_query = "
	SELECT 
		p.ID,
		p.post_title,
		p.post_type,
		p.post_status,
		pm.meta_value as pair_id
	FROM {$wpdb->posts} p
	LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_fpml_pair_id'
	WHERE p.post_type IN ('post', 'page')
	AND p.post_status IN ('publish', 'draft', 'pending')
	ORDER BY p.post_date DESC
	LIMIT 100
";

$posts = $wpdb->get_results( $posts_query );

// Process posts to get translation status
$posts_data = array();
foreach ( $posts as $post ) {
	$pair_id = (int) $post->pair_id;
	$has_translation = $pair_id > 0;
	
	// Get translation status
	$status = 'not_translated';
	if ( $has_translation ) {
		$target_post = get_post( $pair_id );
		if ( $target_post ) {
			// Check if content is translated
			$source_content = get_post_field( 'post_content', $post->ID );
			$target_content = get_post_field( 'post_content', $pair_id );
			
			if ( ! empty( $target_content ) && $target_content !== $source_content ) {
				$status = 'translated';
			} else {
				$status = 'partial';
			}
		}
	}
	
	$posts_data[] = array(
		'id'              => $post->ID,
		'title'           => $post->post_title,
		'type'            => $post->post_type,
		'status'          => $post->post_status,
		'translation_id' => $pair_id,
		'translation_status' => $status,
	);
}

// Get statistics
$total_posts = wp_count_posts( 'post' );
$total_pages = wp_count_posts( 'page' );
$translated_count = count( array_filter( $posts_data, function( $p ) { return $p['translation_status'] === 'translated'; } ) );
$partial_count = count( array_filter( $posts_data, function( $p ) { return $p['translation_status'] === 'partial'; } ) );
$not_translated_count = count( array_filter( $posts_data, function( $p ) { return $p['translation_status'] === 'not_translated'; } ) );
?>

<style>
.fpml-translations-table {
	width: 100%;
	border-collapse: collapse;
	margin-top: 20px;
}
.fpml-translations-table th,
.fpml-translations-table td {
	padding: 12px;
	text-align: left;
	border-bottom: 1px solid #e5e7eb;
}
.fpml-translations-table th {
	background: #f9fafb;
	font-weight: 600;
	color: #374151;
}
.fpml-translations-table tr:hover {
	background: #f9fafb;
}
.fpml-status-badge {
	display: inline-block;
	padding: 4px 12px;
	border-radius: 12px;
	font-size: 12px;
	font-weight: 500;
}
.fpml-status-translated {
	background: #d1fae5;
	color: #065f46;
}
.fpml-status-partial {
	background: #fef3c7;
	color: #92400e;
}
.fpml-status-not-translated {
	background: #fee2e2;
	color: #991b1b;
}
.fpml-bulk-actions {
	margin-bottom: 20px;
	padding: 15px;
	background: #fff;
	border: 1px solid #e5e7eb;
	border-radius: 8px;
}
.fpml-filters {
	display: flex;
	gap: 10px;
	margin-bottom: 20px;
	flex-wrap: wrap;
}
.fpml-filter-btn {
	padding: 8px 16px;
	border: 1px solid #e5e7eb;
	background: #fff;
	border-radius: 6px;
	cursor: pointer;
	transition: all 0.2s;
}
.fpml-filter-btn:hover {
	background: #f9fafb;
	border-color: #0ea5e9;
}
.fpml-filter-btn.active {
	background: #0ea5e9;
	color: #fff;
	border-color: #0ea5e9;
}
.fpml-action-btn {
	padding: 6px 12px;
	border: none;
	border-radius: 4px;
	cursor: pointer;
	font-size: 12px;
	margin-right: 5px;
}
.fpml-action-btn.translate {
	background: #0ea5e9;
	color: #fff;
}
.fpml-action-btn.view {
	background: #10b981;
	color: #fff;
}
.fpml-action-btn.regenerate {
	background: #f59e0b;
	color: #fff;
}
.fpml-stats-summary {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 15px;
	margin-bottom: 30px;
}
.fpml-stat-box {
	background: #fff;
	border: 1px solid #e5e7eb;
	border-radius: 8px;
	padding: 20px;
	text-align: center;
}
.fpml-stat-box h3 {
	margin: 0 0 10px 0;
	font-size: 14px;
	color: #6b7280;
	font-weight: 500;
}
.fpml-stat-box .value {
	font-size: 32px;
	font-weight: 700;
	color: #1f2937;
}
</style>

<div class="wrap">
	<h1><?php esc_html_e( 'Gestione Traduzioni', 'fp-multilanguage' ); ?></h1>
	
	<!-- Statistics Summary -->
	<div class="fpml-stats-summary">
		<div class="fpml-stat-box">
			<h3>Totale Contenuti</h3>
			<div class="value"><?php echo esc_html( (int) $total_posts->publish + (int) $total_pages->publish ); ?></div>
		</div>
		<div class="fpml-stat-box">
			<h3>✅ Tradotti</h3>
			<div class="value" style="color: #10b981;"><?php echo esc_html( $translated_count ); ?></div>
		</div>
		<div class="fpml-stat-box">
			<h3>⚠️ Parziali</h3>
			<div class="value" style="color: #f59e0b;"><?php echo esc_html( $partial_count ); ?></div>
		</div>
		<div class="fpml-stat-box">
			<h3>❌ Non Tradotti</h3>
			<div class="value" style="color: #ef4444;"><?php echo esc_html( $not_translated_count ); ?></div>
		</div>
	</div>
	
	<!-- Filters -->
	<div class="fpml-filters">
		<button class="fpml-filter-btn active" data-filter="all">Tutti</button>
		<button class="fpml-filter-btn" data-filter="translated">✅ Tradotti</button>
		<button class="fpml-filter-btn" data-filter="partial">⚠️ Parziali</button>
		<button class="fpml-filter-btn" data-filter="not_translated">❌ Non Tradotti</button>
	</div>
	
	<!-- Bulk Actions -->
	<div class="fpml-bulk-actions">
		<strong>Azioni Multiple:</strong>
		<button class="button" id="fpml-bulk-translate">Traduci Selezionati</button>
		<button class="button" id="fpml-bulk-regenerate">Rigenera Traduzioni</button>
		<button class="button" id="fpml-bulk-sync">Sincronizza Modifiche</button>
	</div>
	
	<!-- Translations Table -->
	<table class="fpml-translations-table wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width: 30px;"><input type="checkbox" id="fpml-select-all"></th>
				<th>Titolo</th>
				<th>Tipo</th>
				<th>Stato</th>
				<th>Stato Traduzione</th>
				<th>Azioni</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $posts_data as $post_data ) : ?>
				<tr data-status="<?php echo esc_attr( $post_data['translation_status'] ); ?>">
					<td><input type="checkbox" class="fpml-post-checkbox" value="<?php echo esc_attr( $post_data['id'] ); ?>"></td>
					<td>
						<strong><?php echo esc_html( $post_data['title'] ); ?></strong>
						<?php if ( $post_data['status'] !== 'publish' ) : ?>
							<span style="color: #6b7280; font-size: 11px;">(<?php echo esc_html( $post_data['status'] ); ?>)</span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $post_data['type'] ); ?></td>
					<td><?php echo esc_html( ucfirst( $post_data['status'] ) ); ?></td>
					<td>
						<?php
						$status_class = 'fpml-status-' . $post_data['translation_status'];
						$status_text = array(
							'translated'    => '✅ Tradotto',
							'partial'       => '⚠️ Parziale',
							'not_translated' => '❌ Non Tradotto',
						);
						?>
						<span class="fpml-status-badge <?php echo esc_attr( $status_class ); ?>">
							<?php echo esc_html( $status_text[ $post_data['translation_status'] ] ); ?>
						</span>
					</td>
					<td>
						<?php if ( $post_data['translation_status'] === 'not_translated' ) : ?>
							<button class="fpml-action-btn translate" data-post-id="<?php echo esc_attr( $post_data['id'] ); ?>">
								Traduci
							</button>
						<?php else : ?>
							<?php if ( $post_data['translation_id'] ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $post_data['translation_id'] ) ); ?>" class="fpml-action-btn view">
									Visualizza EN
								</a>
							<?php endif; ?>
							<button class="fpml-action-btn regenerate" data-post-id="<?php echo esc_attr( $post_data['id'] ); ?>">
								Rigenera
							</button>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<script>
jQuery(document).ready(function($) {
	// Filter functionality
	$('.fpml-filter-btn').on('click', function() {
		var filter = $(this).data('filter');
		$('.fpml-filter-btn').removeClass('active');
		$(this).addClass('active');
		
		if (filter === 'all') {
			$('.fpml-translations-table tbody tr').show();
		} else {
			$('.fpml-translations-table tbody tr').hide();
			$('.fpml-translations-table tbody tr[data-status="' + filter + '"]').show();
		}
	});
	
	// Select all checkbox
	$('#fpml-select-all').on('change', function() {
		$('.fpml-post-checkbox').prop('checked', $(this).prop('checked'));
	});
	
	// Bulk translate
	$('#fpml-bulk-translate').on('click', function() {
		var selected = $('.fpml-post-checkbox:checked').map(function() {
			return $(this).val();
		}).get();
		
		if (selected.length === 0) {
			alert('Seleziona almeno un contenuto da tradurre.');
			return;
		}
		
		if (!confirm('Traduci ' + selected.length + ' contenuti?')) {
			return;
		}
		
		var $btn = $(this);
		$btn.prop('disabled', true).text('Traduzione in corso...');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'fpml_bulk_translate',
				nonce: '<?php echo esc_js( wp_create_nonce( 'fpml_admin' ) ); ?>',
				post_ids: selected
			},
			success: function(response) {
				if (response.success) {
					alert('Traduzione completata: ' + response.data.success + ' successi, ' + response.data.failed + ' falliti.');
					location.reload();
				} else {
					alert('Errore: ' + (response.data.message || 'Errore sconosciuto'));
				}
			},
			error: function() {
				alert('Errore di comunicazione con il server.');
			},
			complete: function() {
				$btn.prop('disabled', false).text('Traduci Selezionati');
			}
		});
	});
	
	// Bulk regenerate
	$('#fpml-bulk-regenerate').on('click', function() {
		var selected = $('.fpml-post-checkbox:checked').map(function() {
			return $(this).val();
		}).get();
		
		if (selected.length === 0) {
			alert('Seleziona almeno un contenuto da rigenerare.');
			return;
		}
		
		if (!confirm('Rigenera traduzioni per ' + selected.length + ' contenuti?')) {
			return;
		}
		
		var $btn = $(this);
		$btn.prop('disabled', true).text('Rigenerazione in corso...');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'fpml_bulk_regenerate',
				nonce: '<?php echo esc_js( wp_create_nonce( 'fpml_admin' ) ); ?>',
				post_ids: selected
			},
			success: function(response) {
				if (response.success) {
					alert('Rigenerazione completata: ' + response.data.success + ' successi, ' + response.data.failed + ' falliti.');
					location.reload();
				} else {
					alert('Errore: ' + (response.data.message || 'Errore sconosciuto'));
				}
			},
			error: function() {
				alert('Errore di comunicazione con il server.');
			},
			complete: function() {
				$btn.prop('disabled', false).text('Rigenera Traduzioni');
			}
		});
	});
	
	// Bulk sync
	$('#fpml-bulk-sync').on('click', function() {
		var selected = $('.fpml-post-checkbox:checked').map(function() {
			return $(this).val();
		}).get();
		
		if (selected.length === 0) {
			alert('Seleziona almeno un contenuto da sincronizzare.');
			return;
		}
		
		if (!confirm('Sincronizza ' + selected.length + ' contenuti?')) {
			return;
		}
		
		var $btn = $(this);
		$btn.prop('disabled', true).text('Sincronizzazione in corso...');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'fpml_bulk_sync',
				nonce: '<?php echo esc_js( wp_create_nonce( 'fpml_admin' ) ); ?>',
				post_ids: selected
			},
			success: function(response) {
				if (response.success) {
					alert('Sincronizzazione completata: ' + response.data.success + ' successi, ' + response.data.failed + ' falliti.');
					location.reload();
				} else {
					alert('Errore: ' + (response.data.message || 'Errore sconosciuto'));
				}
			},
			error: function() {
				alert('Errore di comunicazione con il server.');
			},
			complete: function() {
				$btn.prop('disabled', false).text('Sincronizza Modifiche');
			}
		});
	});
	
	// Individual translate
	$('.fpml-action-btn.translate').on('click', function() {
		var postId = $(this).data('post-id');
		var $btn = $(this);
		
		if (!confirm('Traduci questo contenuto?')) {
			return;
		}
		
		$btn.prop('disabled', true).text('Traduzione...');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'fpml_translate_single',
				nonce: '<?php echo esc_js( wp_create_nonce( 'fpml_admin' ) ); ?>',
				post_id: postId
			},
			success: function(response) {
				if (response.success) {
					alert('Traduzione completata con successo!');
					location.reload();
				} else {
					alert('Errore: ' + (response.data.message || 'Errore sconosciuto'));
					$btn.prop('disabled', false).text('Traduci');
				}
			},
			error: function() {
				alert('Errore di comunicazione con il server.');
				$btn.prop('disabled', false).text('Traduci');
			}
		});
	});
});
</script>



