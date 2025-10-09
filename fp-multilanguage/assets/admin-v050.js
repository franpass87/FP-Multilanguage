/**
 * Admin JavaScript for v0.5.0 Features
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

(function($) {
	'use strict';

	// Glossary Management
	if ($('#fpml-glossary-table').length) {
		// Delete term
		$('.fpml-delete-term').on('click', function() {
			if (!confirm(fpmlAdmin.confirmDelete)) {
				return;
			}

			var termId = $(this).data('term-id');
			var row = $(this).closest('tr');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'fpml_glossary_delete',
					nonce: fpmlAdmin.nonce,
					term_id: termId
				},
				success: function(response) {
					if (response.success) {
						row.fadeOut(function() {
							$(this).remove();
						});
					} else {
						alert(response.data.message || fpmlAdmin.error);
					}
				},
				error: function() {
					alert(fpmlAdmin.error);
				}
			});
		});

		// Add term button
		$('#fpml-add-term').on('click', function() {
			var name = prompt(fpmlAdmin.termNamePrompt);
			if (!name) return;

			var translation = prompt(fpmlAdmin.termTranslationPrompt);
			if (translation === null) return;

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'fpml_glossary_add',
					nonce: fpmlAdmin.nonce,
					source: name,
					target: translation
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || fpmlAdmin.error);
					}
				}
			});
		});

		// Export glossary
		$('#fpml-export-glossary').on('click', function() {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'fpml_glossary_export',
					nonce: fpmlAdmin.nonce
				},
				success: function(response) {
					if (response.success) {
						var blob = new Blob([response.data.csv], {type: 'text/csv'});
						var url = window.URL.createObjectURL(blob);
						var a = document.createElement('a');
						a.href = url;
						a.download = 'glossary-' + new Date().toISOString().slice(0,10) + '.csv';
						document.body.appendChild(a);
						a.click();
						window.URL.revokeObjectURL(url);
						document.body.removeChild(a);
					}
				}
			});
		});

		// Import glossary
		$('#fpml-import-glossary').on('click', function() {
			var input = document.createElement('input');
			input.type = 'file';
			input.accept = '.csv';
			input.onchange = function(e) {
				var file = e.target.files[0];
				if (!file) return;

				var reader = new FileReader();
				reader.onload = function(e) {
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'fpml_glossary_import',
							nonce: fpmlAdmin.nonce,
							csv: e.target.result
						},
						success: function(response) {
							if (response.success) {
								alert(fpmlAdmin.importSuccess.replace('%d', response.data.imported));
								location.reload();
							} else {
								alert(response.data.message || fpmlAdmin.error);
							}
						}
					});
				};
				reader.readAsText(file);
			};
			input.click();
		});
	}

	// API Keys Management
	if ($('#fpml-generate-key').length) {
		$('#fpml-generate-key').on('click', function() {
			var name = prompt(fpmlAdmin.apiKeyNamePrompt);
			if (!name) return;

			var description = prompt(fpmlAdmin.apiKeyDescPrompt) || '';

			$(this).prop('disabled', true).text(fpmlAdmin.generating);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'fpml_generate_api_key',
					nonce: fpmlAdmin.nonce,
					name: name,
					description: description
				},
				success: function(response) {
					if (response.success) {
						prompt(fpmlAdmin.apiKeyGenerated, response.data.api_key);
						location.reload();
					} else {
						alert(response.data.message || fpmlAdmin.error);
					}
				},
				complete: function() {
					$('#fpml-generate-key').prop('disabled', false).text(fpmlAdmin.generateKey);
				}
			});
		});

		// Revoke API key
		$('.fpml-revoke-key').on('click', function() {
			if (!confirm(fpmlAdmin.confirmRevoke)) {
				return;
			}

			var keyId = $(this).data('key-id');
			var row = $(this).closest('tr');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'fpml_revoke_api_key',
					nonce: fpmlAdmin.nonce,
					key_id: keyId
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || fpmlAdmin.error);
					}
				}
			});
		});
	}

	// Debug Page
	if ($('#fpml-debug-clear').length) {
		$('#fpml-debug-clear').on('click', function() {
			if (!confirm(fpmlAdmin.confirmClearDebug)) {
				return;
			}

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'fpml_debug_clear',
					nonce: fpmlAdmin.nonce
				},
				success: function() {
					location.reload();
				}
			});
		});

		$('#fpml-debug-export').on('click', function() {
			window.location.href = ajaxurl + '?action=fpml_debug_export&nonce=' + fpmlAdmin.nonce;
		});
	}

	// Bulk Translation - Estimate cost before starting
	if ($('.bulkactions select option[value="fpml_bulk_translate"]').length) {
		var originalBulkAction = $('form#posts-filter').on('submit', function(e) {
			var action = $(this).find('.bulkactions select').val();
			
			if (action === 'fpml_bulk_translate') {
				e.preventDefault();
				
				var postIds = [];
				$(this).find('input[name="post[]"]:checked').each(function() {
					postIds.push($(this).val());
				});

				if (postIds.length === 0) {
					alert(fpmlAdmin.noPostsSelected);
					return false;
				}

				// Show loading
				var loadingMsg = $('<div class="notice notice-info"><p>' + fpmlAdmin.estimating + '</p></div>');
				$('.wrap h1').after(loadingMsg);

				// Get cost estimate
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'fpml_bulk_estimate',
						nonce: fpmlAdmin.nonce,
						post_ids: postIds
					},
					success: function(response) {
						loadingMsg.remove();
						
						if (response.success) {
							var estimate = response.data;
							var message = fpmlAdmin.estimateMessage
								.replace('%1$d', estimate.total_posts)
								.replace('%2$d', estimate.total_characters.toLocaleString())
								.replace('%3$s', '$' + estimate.estimated_cost.toFixed(2))
								.replace('%4$s', estimate.estimated_time);

							if (confirm(message)) {
								// Proceed with bulk translation
								window.location.href = '?action=fpml_bulk_translate&post=' + postIds.join(',') + '&_wpnonce=' + fpmlAdmin.nonce;
							}
						} else {
							alert(response.data.message || fpmlAdmin.error);
						}
					},
					error: function() {
						loadingMsg.remove();
						alert(fpmlAdmin.error);
					}
				});

				return false;
			}
		});
	}

})(jQuery);
