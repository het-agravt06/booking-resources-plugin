(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 */

	// Open media file.
	$(document).ready(function () {

		$('#resource_image_button').on('click', function (event) {

			event.preventDefault();

			var image_frame = wp.media({
				title: 'Select Resource Image',
				button: {
					text: 'Use This Image'
				},
				multiple: true
			});

			image_frame.on('select', function () {

				var attachments = image_frame.state().get('selection').toJSON();
				var image_ids = [];
				var preview_html = '';

				$.each(attachments, function (index, attachment) {

					image_ids.push(attachment.id);

					preview_html +=
						'<img src="' + attachment.url + '" style="max-width: 150px; height: auto; margin: 5px;">';

				});

				$('#resource_image_ids').val(image_ids.join(','));

				$('#resource_image_preview').html(preview_html);
			});

			image_frame.open();
		});

		// Remove image.
		$('#resource_image_remove_button').on('click', function (event) {

			event.preventDefault();

			$('#resource_image_id').val('');

			$('#resource_image_preview').html('');

		});

	});

})( jQuery );