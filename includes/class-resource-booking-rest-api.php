<?php

/**
 * Handles REST API functionality for the Resource Booking plugin.
 *
 * @package Resource_Booking
 */

class Resource_Booking_REST_API {

	public function register_routes() {

		register_rest_route(
			'resource-booking/v1',
			'/bookings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_booking' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function create_booking( $request ) {

        $resource_id     = $request->get_param( 'resource_id' );
        $customer_name   = $request->get_param( 'customer_name' );
        $customer_email  = $request->get_param( 'customer_email' );
        $start_datetime  = $request->get_param( 'start_datetime' );
        $end_datetime    = $request->get_param( 'end_datetime' );

        //requeir feild validation
        if ( empty( $resource_id ) ) {
            return new WP_Error(
                'missing_resource_id',
                'Resource ID is required.',
                array( 'status' => 400 )
            );
        }

        if ( empty( $customer_name ) ) {
            return new WP_Error(
                'missing_customer_name',
                'Customer name is required.',
                array( 'status' => 400 )
            );
        }

        if ( empty( $customer_email ) ) {
            return new WP_Error(
                'missing_customer_email',
                'Customer email is required.',
                array( 'status' => 400 )
            );
        }

        if ( empty( $start_datetime ) ) {
            return new WP_Error(
                'missing_start_datetime',
                'Start datetime is required.',
                array( 'status' => 400 )
            );
        }

        if ( empty( $end_datetime ) ) {
            return new WP_Error(
                'missing_end_datetime',
                'End datetime is required.',
                array( 'status' => 400 )
            );
        }

        //email validation
        if ( ! is_email( $customer_email ) ) {
            return new WP_Error(
                'invalid_customer_email',
                'Please provide a valid email address.',
                array( 'status' => 400 )
            );
        }

        //daye-time validation
        $start_time = DateTime::createFromFormat( 'Y-m-d H:i:s', $start_datetime );
        $end_time   = DateTime::createFromFormat( 'Y-m-d H:i:s', $end_datetime );

        if ( ! $start_time || ! $end_time ) {
            return new WP_Error(
                'invalid_datetime',
                'Please provide a valid datetime format: Y-m-d H:i:s.',
                array( 'status' => 400 )
            );
        }

        if ( $end_time <= $start_time ) {
            return new WP_Error(
                'invalid_booking_time',
                'End datetime must be after start datetime.',
                array( 'status' => 400 )
            );
        }
        
 		return array(
			'resource_id'    => $resource_id,
            'customer_name'  => $customer_name,
            'customer_email' => $customer_email,
            'start_datetime' => $start_datetime,
            'end_datetime'   => $end_datetime,
		);

	}
}