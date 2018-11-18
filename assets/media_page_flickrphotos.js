(function($) {

	"use strict";

	var importSingle;

	$(document).on('ready', function() {

		$('button[data-import-from-flickr]').on('click.import', function() {

			importSingle = function(flickr_id) {
				var $button = $(this),
					data = posts_for_import[flickr_id];

				//$button.hide();

				if (data) {
					$.ajax({
						method: 'POST',
						cache: false,
						dataType: 'json',
						data: data,
						url: mediaPageFlickrPhotos.rest_url + 'wp/v2/posts/',
						beforeSend: function(xhr) {
							xhr.setRequestHeader('X-WP-Nonce', mediaPageFlickrPhotos.rest_nonce);
						},
						success: function(response_data) {
							console.log(response_data);
							$button.closest('tr').slideUp();
						},
						error: function(jqXHR) {
							console.log(jqXHR);
						}
					});
				}
			};

			if ($('input[name="image[]"]:checked').length) {
				$('input[name="image[]"]:checked').each(function() {
					importSingle($(this).val());
				});
			}



		});

	});


})(jQuery);
