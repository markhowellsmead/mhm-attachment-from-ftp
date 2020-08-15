(function ($) {

	$('form.mhmajaxified').on('submit', function (e) {
		e.preventDefault();
	});

	const doRestRequest = function (restRoute, method, callbackSuccess, callbackFail) {
		$.ajax(wp_api.root + restRoute, {
				method: method,
				headers: {
					'X-WP-Nonce': wp_api.nonce
				}
			})
			.success(callbackSuccess)
			.fail(callbackFail);
	};

	$('button[data-file]').on('click', function () {
		$(this).attr('disabled', 'disabled');
		if($(this).data('action') == 'delete') {
			let row = $(this).closest('tr');
			doRestRequest('mhm-attachment-from-ftp/v1/image/?file=' + $(this).data('file'), 'DELETE', function () {
				row.slideUp(1000, function () { $(this).remove(); });
			}, function (jqXHR, textStatus, errorThrown) {
				console.error([jqXHR, textStatus, errorThrown]);
				$(this).attr('disabled', null);
			});
		}
		if($(this).data('action') == 'create') {
			let row = $(this).closest('tr');
			doRestRequest('mhm-attachment-from-ftp/v1/image/?file=' + $(this).data('file'), 'POST', function () {
				row.slideUp(1000, function () { $(this).remove(); });
			}, function (jqXHR, textStatus, errorThrown) {
				console.error([jqXHR, textStatus, errorThrown]);
				$(this).attr('disabled', null);
			});
		}
	});

})(jQuery);
