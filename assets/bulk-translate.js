/**
 * Bulk Translation JS
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

jQuery(document).ready(function($) {
	const ratePerThousand = fpmlBulk && fpmlBulk.rate ? parseFloat(fpmlBulk.rate) : 0.00011;
	const costDecimals = ratePerThousand < 0.01 ? 6 : 2;
	const fallbackModel = 'GPT-5 nano';
	const modelLabel = fpmlBulk && fpmlBulk.model ? fpmlBulk.model : fallbackModel;
	const rateLabel = fpmlBulk && fpmlBulk.rate_label ? fpmlBulk.rate_label : '€0.00011 / 1000';

	// Update bulk summary when selection changes
	function updateBulkSummary() {
		const $checked = $('input[name="post_ids[]"]:checked');
		const count = $checked.length;
		
		if (count === 0) {
			$('#fpml-bulk-summary').hide();
			return;
		}
		
		let totalChars = 0;
		$checked.each(function() {
			const $row = $(this).closest('tr');
			const charsText = $row.find('td:eq(3)').text(); // 4th column has chars
			const chars = parseInt(charsText.replace(/[^0-9]/g, '')) || 0;
			totalChars += chars;
		});
		const totalCost = (totalChars / 1000) * ratePerThousand;
		const totalTime = Math.max(1, Math.ceil(totalChars / 1000)); // ~1 min per 1K
		
		$('#fpml-selected-count').text(count);
		$('#fpml-total-chars').text(totalChars.toLocaleString());
		$('#fpml-total-time').text(totalTime + ' min');
		$('#fpml-total-cost').text('€' + totalCost.toFixed(costDecimals));
		$('#fpml-total-cost').attr('title', modelLabel + ' · ' + rateLabel);
		
		$('#fpml-bulk-summary').fadeIn();
	}
	
	// Select all checkbox
	$('#fpml-select-all').on('change', function() {
		$('input[name="post_ids[]"]').prop('checked', this.checked);
		setTimeout(updateBulkSummary, 50);
	});
	
	// Individual checkboxes
	$(document).on('change', 'input[name="post_ids[]"]', updateBulkSummary);

	// Bulk translate form submit
	$('#fpml-bulk-form').on('submit', function(e) {
		e.preventDefault();

		const $form = $(this);
		const $btn = $('#fpml-bulk-translate-btn');
		const $spinner = $form.find('.spinner');
		const $progress = $('#fpml-bulk-progress');
		const $progressBar = $('#fpml-progress-bar');
		const $progressText = $('#fpml-progress-text');

		const postIds = [];
		$('input[name="post_ids[]"]:checked').each(function() {
			postIds.push($(this).val());
		});

		if (postIds.length === 0) {
			alert(fpmlBulk.strings.noSelection || 'Seleziona almeno un contenuto.');
			return;
		}

		// Show progress
		$btn.prop('disabled', true);
		$spinner.addClass('is-active');
		$progress.show();
		$progressBar.attr('max', postIds.length);

		// Send AJAX request
		$.ajax({
			url: fpmlBulk.ajaxurl,
			type: 'POST',
			data: {
				action: 'fpml_bulk_translate',
				nonce: fpmlBulk.nonce,
				post_ids: postIds
			},
			success: function(response) {
				$spinner.removeClass('is-active');
				$btn.prop('disabled', false);

				if (response.success) {
					$progressBar.val(postIds.length);
					$progressText.text(response.data.added + ' / ' + postIds.length);
					alert(response.data.message);

					// Redirect to diagnostics
					setTimeout(function() {
						window.location.href = fpmlBulk.diagnosticsUrl || 'admin.php?page=fpml-settings&tab=diagnostics';
					}, 2000);
				} else {
					alert(response.data.message || 'Errore durante l\'aggiunta alla coda.');
				}
			},
			error: function() {
				$spinner.removeClass('is-active');
				$btn.prop('disabled', false);
				alert('Errore di comunicazione con il server.');
			}
		});
	});
});

