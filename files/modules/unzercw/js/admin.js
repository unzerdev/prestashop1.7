
jQuery(document).ready(function() {
	
	jQuery('.unzercw-transaction-table .unzercw-more-details-button').each(function() {
		jQuery(this).click(function() {
			
			// hide all open 
			jQuery('.unzercw-transaction-table').find('.active').removeClass('active');
			
			// Get transaction ID
			var mainRow = jQuery(this).parents('.unzercw-main-row');
			var transactionId = mainRow.attr('id').replace('unzercw-_main_row_', '');
			
			var selector = '.unzercw-transaction-table #unzercw_details_row_' + transactionId;
			jQuery(selector).addClass('active');
			jQuery(mainRow).addClass('active');
		})
	});
	
	jQuery('.unzercw-transaction-table .unzercw-less-details-button').each(function() {
		jQuery(this).click(function() {
			// hide all open 
			jQuery('.unzercw-transaction-table').find('.active').removeClass('active');
		})
	});
	
	jQuery('.unzercw-transaction-table .transaction-information-table .description').each(function() {
		jQuery(this).mouseenter(function() {
			jQuery(this).toggleClass('hidden');
		});
		jQuery(this).mouseleave(function() {
			jQuery(this).toggleClass('hidden');
		})
	});
	
});