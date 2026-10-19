/**
 * Admin JavaScript
 *
 * @package Oli_Cron_Jobs
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		// Confirm bulk delete action
		$('.bulkactions').on('click', function(e) {
			var action = $(this).closest('form').find('select[name="action"]').val();
			if (action === 'delete') {
				if (!confirm('Are you sure you want to delete the selected events?')) {
					e.preventDefault();
					return false;
				}
			}
		});

		// Highlight overdue events
		$('.oli-cj-event-overdue').closest('tr').css('background-color', '#fcf0f1');

		// Add warning icon to PHP event forms
		if ($('#php_code').length > 0) {
			$('#php_code').before(
				'<div class="notice notice-warning inline"><p><strong>Warning:</strong> PHP cron events execute arbitrary code on your server. Only use this feature if you understand the security implications.</p></div>'
			);
		}
	});

})(jQuery);
