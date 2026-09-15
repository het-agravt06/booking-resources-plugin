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

		// Get resources.
		$.ajax({
			url: resourceBooking.apiUrl + 'resources',
			type: 'GET',
			success: function( resources ) {

				$.each( resources, function( index, resource ) {

					$( '#resource_id' ).append(
						$('<option>', {
							value: resource.id,
							text: resource.name
						})
					);

				});

			},
			error: function() {

				console.log( 'Failed to load resources.' );

			}
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

			$.ajax({
				url: resourceBooking.apiUrl + 'availability',
				type: 'GET',
				data: {
					resource_id: resourceId,
					date: date
				},
				success: function( response ) {

					if ( response.blackout_date ) {

						$( '#resource-booking-availability' )
							.text( 'This date is unavailable.' );

						selectedTimeAvailable = false;

						return;
					}

					if ( ! response.business_hours ) {

						$( '#resource-booking-availability' )
							.text( 'This day is unavailable.' );

						selectedTimeAvailable = false;

						return;
					}

					// If end time is not selected yet, only show existing bookings.
					if ( ! endDatetime ) {

						if ( response.bookings.length > 0 ) {

							var message = 'Unavailable times: ';

							$.each( response.bookings, function( index, booking ) {

								message += booking.start_datetime + ' - ' + booking.end_datetime;

								if ( index < response.bookings.length - 1 ) {
									message += ', ';
								}

							});

							$( '#resource-booking-availability' ).text( message );

						} else {

							$( '#resource-booking-availability' )
								.text( 'No bookings found. This date is available.' );

						}

						return;
					}

					if ( startDatetime >= endDatetime ) {

						$( '#resource-booking-availability' )
							.text( 'End time must be after start time.' );

						selectedTimeAvailable = false;

						return;
					}

					var conflict = false;

					$.each( response.bookings, function( index, booking ) {

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

					});

					if ( conflict ) {

						selectedTimeAvailable = false;

						$( '#resource-booking-availability' )
							.text( 'Selected time is unavailable.' );

					} else {

						selectedTimeAvailable = true;

						$( '#resource-booking-availability' )
							.text( 'Selected time is available.' );

					}

				},
				error: function() {

					selectedTimeAvailable = false;

					$( '#resource-booking-availability' )
						.text( 'Unable to check availability.' );

				}
			});

		}

		// Check availability when resource or date/time changes.
		$( '#resource_id, #start_datetime, #end_datetime' ).on( 'change', function() {

			checkAvailability();

		});

		// Submit handler.
		$( '#resource-booking-form' ).on( 'submit', function( event ) {

			event.preventDefault();

			if ( selectedTimeAvailable === false ) {

				$( '#resource-booking-message' )
					.text( 'Please select another available time.' );

				return;
			}

			if ( selectedTimeAvailable !== true ) {

				$( '#resource-booking-message' )
					.text( 'Please check the availability first.' );

				checkAvailability();

				return;
			}

			var formData = {
				resource_id: $( '#resource_id' ).val(),
				customer_name: $( '#customer_name' ).val(),
				customer_email: $( '#customer_email' ).val(),
				start_datetime: $( '#start_datetime' ).val().replace( 'T', ' ' ) + ':00',
				end_datetime: $( '#end_datetime' ).val().replace( 'T', ' ' ) + ':00'
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
						.text( 'Booking submitted successfully.' );

					$( '#resource-booking-form' )[0].reset();

					$( '#resource-booking-availability' ).text( '' );

					selectedTimeAvailable = null;

				},
				error: function( xhr ) {

					var message = 'Unable to submit booking.';

					if ( xhr.responseJSON && xhr.responseJSON.message ) {
						message = xhr.responseJSON.message;
					}

					$( '#resource-booking-message' ).text( message );

				}
			});

		});

	});

})( jQuery );