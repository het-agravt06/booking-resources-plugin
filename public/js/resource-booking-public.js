(function( $ ) {
	'use strict';

	/**
	 * All of the code for your public-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */


	$(function() {

		var selectedTimeAvailable = null;

		// Set minimum date/time to current date.
		var now = new Date();
		var year = now.getFullYear();
		var month = String( now.getMonth() + 1 ).padStart( 2, '0' );
		var day = String( now.getDate() ).padStart( 2, '0' );
		var today = year + '-' + month + '-' + day;

		$( '#start_datetime, #end_datetime' ).attr(
			'min',
			today + 'T00:00'
		);

		console.log( resourceBooking.apiUrl + 'resources' );
		// Get resources.
		$.ajax({
			url: resourceBooking.apiUrl + 'resources',
			type: 'GET',
			success: function( resources ) {

				$( '#resource_id' ).data( 'resources', resources );

				$.each( resources, function( index, resource ) {

					$( '#resource_id' ).append(
						$( '<option>', {
							value: resource.id,
							text: resource.name
						})
					);

				});

			},
			error: function() {

				$( '#resource-booking-message' )
					.removeClass()
					.addClass( 'resource-booking-error' )
					.text( 'Failed to load resources.' );

			}
		});

		$( '#resource_id' ).on( 'change', function() {

			var resourceId = $( this ).val();
			var resources = $( this ).data( 'resources' );

			$( '#resource-details' ).html( '' );

			if ( ! resourceId || ! resources ) {
				return;
			}

			$.each( resources, function( index, resource ) {

				if ( String( resource.id ) === String( resourceId ) ) {
					//display images on frontend
					$( '#resource-details' ).html(
						'<p><strong>Description:</strong> ' +
						resource.description +
						'</p>' +
						'<p><strong>Capacity:</strong> ' +
						resource.capacity +
						'</p>'
					);

					$( '#resource-image' ).html( '' );

				if ( resource.image_urls && resource.image_urls.length > 0 ) {

					var image_html = '<p><strong>Images:</strong></p>';

					$.each( resource.image_urls, function( index, image_url ) {

						image_html +=
							'<img src="' +
							image_url +
							'" alt="' +
							resource.name +
							'" style="max-width: 200px; height: auto; margin: 5px;">';

					});

					$( '#resource-image' ).html( image_html );

					}

					return false;
				}

			});

		});
		
		// Check availability.
		function checkAvailability() {

			var resourceId = $( '#resource_id' ).val();
			var startDatetime = $( '#start_datetime' ).val();
			var endDatetime = $( '#end_datetime' ).val();

			selectedTimeAvailable = null;

			if ( ! resourceId || ! startDatetime ) {

				$( '#resource-booking-availability' ).text( '' );

				return;
			}

			var date = startDatetime.substring( 0, 10 );

			$( '#resource-booking-availability' )
				.text( 'Checking availability...' );

			$.ajax({
				url: resourceBooking.apiUrl + 'availability',
				type: 'GET',
				data: {
					resource_id: resourceId,
					date: date
				},
				success: function( response ) {

					/*
					 * Check blackout date.
					 */
					if ( response.blackout_date ) {

						$( '#resource-booking-availability' )
						.removeClass()
						.addClass( 'resource-booking-warning' )
						.text( 'This date is unavailable.' );

						selectedTimeAvailable = false;

						return;
					}

					/*
					 * Check business day.
					 */
					if ( ! response.business_hours ) {

						$( '#resource-booking-availability' )
						.removeClass()
						.addClass( 'resource-booking-warning' )
						.text( 'This day is unavailable.' );

						selectedTimeAvailable = false;

						return;
					}

					var businessStart = response.business_hours.start;
					var businessEnd = response.business_hours.end;

					var selectedStartTime = startDatetime.substring( 11, 16 );

					/*
					 * Check start time against business hours.
					 */
					if (
						selectedStartTime < businessStart ||
						selectedStartTime >= businessEnd
					) {

						$( '#resource-booking-availability' )
							.removeClass()
							.addClass( 'resource-booking-warning' )
							.text(
								'Bookings are available from ' +
								businessStart +
								' to ' +
								businessEnd +
								'.'
							);

						selectedTimeAvailable = false;

						return;
					}

					/*
					 * If end time is not selected yet,
					 * show existing bookings only.
					 */
					if ( ! endDatetime ) {

						if ( response.bookings.length > 0 ) {

							var message = 'Unavailable times: ';

							$.each(
								response.bookings,
								function( index, booking ) {

									message +=
										booking.start_datetime +
										' - ' +
										booking.end_datetime;

									if (
										index <
										response.bookings.length - 1
									) {
										message += ', ';
									}

								}
							);

							$( '#resource-booking-availability' )
								.text( message );

						} else {

							$( '#resource-booking-availability' )
								.text(
									'No bookings found. This date is available.'
								);

						}

						return;
					}

					var selectedEndTime = endDatetime.substring( 11, 16 );

					/*
					 * Check end time against business hours.
					 */
					if (
						selectedEndTime > businessEnd ||
						selectedEndTime <= businessStart
					) {

						$( '#resource-booking-availability' )
							.text(
								'Bookings are available from ' +
								businessStart +
								' to ' +
								businessEnd +
								'.'
							);

						selectedTimeAvailable = false;

						return;
					}

					/*
					 * Check start and end time.
					 */
					if ( startDatetime >= endDatetime ) {

						$( '#resource-booking-availability' )
							.text(
								'End time must be after start time.'
							);

						selectedTimeAvailable = false;

						return;
					}

					/*
					 * Check booking conflicts.
					 */
					var conflict = false;

					$.each(
						response.bookings,
						function( index, booking ) {

							var bookingStart = booking.start_datetime
								.replace( ' ', 'T' )
								.substring( 0, 16 );

							var bookingEnd = booking.end_datetime
								.replace( ' ', 'T' )
								.substring( 0, 16 );

							if (
								startDatetime < bookingEnd &&
								endDatetime > bookingStart
							) {

								conflict = true;

								return false;
							}

						}
					);

					if ( conflict ) {

						selectedTimeAvailable = false;

						$( '#resource-booking-availability' )
						.removeClass()
						.addClass( 'resource-booking-error' )
						.text( 'Selected time is unavailable.' );

					} else {

						selectedTimeAvailable = true;

						$( '#resource-booking-availability' )
							.removeClass()
							.addClass( 'resource-booking-success' )
							.text( 'Selected time is available.' );

					}

				},
				error: function() {

					selectedTimeAvailable = false;

					$( '#resource-booking-availability' )
						.text(
							'Unable to check availability.'
						);

				}
			});

		}

		/*
		 * Check availability whenever resource,
		 * start time, or end time changes.
		 */
		$( '#resource_id, #start_datetime, #end_datetime' ).on(
			'change',
			function() {

				checkAvailability();

			}
		);

		/*
		 * Submit booking.
		 */
		$( '#resource-booking-form' ).on(
			'submit',
			function( event ) {

				event.preventDefault();

				/*
				 * Do not submit until availability has been checked.
				 */
				if ( selectedTimeAvailable !== true ) {

					if ( selectedTimeAvailable === false ) {

						$( '#resource-booking-message' )
							.text(
								'Please select another available time.'
							);

					} else {

						$( '#resource-booking-message' )
							.text(
								'Please check the availability first.'
							);

						checkAvailability();

					}

					return;
				}

				var formData = {
					resource_id: $( '#resource_id' ).val(),
					customer_name: $( '#customer_name' ).val(),
					customer_email: $( '#customer_email' ).val(),
					start_datetime: $( '#start_datetime' )
						.val()
						.replace( 'T', ' ' ) + ':00',
					end_datetime: $( '#end_datetime' )
						.val()
						.replace( 'T', ' ' ) + ':00'
				};

				$.ajax({
					url: resourceBooking.apiUrl + 'bookings',
					type: 'POST',
					data: formData,
					beforeSend: function() {

						$( '#resource-booking-message' )
							.text( 'Submitting booking...' );

					},
					success: function( response ) {

						$( '#resource-booking-message' )
							.removeClass()
							.addClass( 'resource-booking-success' )
							.text( 'Booking submitted successfully.' );

						$( '#resource-booking-form' )[0].reset();

						$( '#resource-booking-availability' )
							.text( '' );

						selectedTimeAvailable = null;

					},
					error: function( xhr ) {

						var message = 'Unable to submit booking.';

						if (
							xhr.responseJSON &&
							xhr.responseJSON.message
						) {
							message = xhr.responseJSON.message;
						}

						$( '#resource-booking-message' )
							.removeClass()
							.addClass( 'resource-booking-error' )
							.text( message );

					}
				});

			}
		);

	});

})( jQuery );