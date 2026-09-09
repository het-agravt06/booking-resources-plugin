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
				multiple: false
			});

			image_frame.on('select', function () {

				var attachment = image_frame.state().get('selection').first().toJSON();

				$('#resource_image_id').val(attachment.id);

				$('#resource_image_preview').html(
					'<img src="' + attachment.url + '" style="max-width: 200px; height: auto;">'
				);
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