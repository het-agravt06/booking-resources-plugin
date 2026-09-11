<?php

/**
 * Handles REST API functionality for the Resource Booking plugin.
 *
 * @package Resource_Booking
 */

class Resource_Booking_REST_API
{

    /**
     * Register REST API routes.
     */
    public function register_routes()
    {

        register_rest_route(
            'resource-booking/v1',
            '/bookings',
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'create_booking'),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Create a new booking.
     */
    public function create_booking($request)
    {

        $resource_id    = $request->get_param('resource_id');
        $customer_name  = $request->get_param('customer_name');
        $customer_email = $request->get_param('customer_email');
        $start_datetime = $request->get_param('start_datetime');
        $end_datetime   = $request->get_param('end_datetime');

        // Required field validation.
        if (empty($resource_id)) {
            return new WP_Error(
                'missing_resource_id',
                'Resource ID is required.',
                array('status' => 400)
            );
        }

        if (empty($customer_name)) {
            return new WP_Error(
                'missing_customer_name',
                'Customer name is required.',
                array('status' => 400)
            );
        }

        if (empty($customer_email)) {
            return new WP_Error(
                'missing_customer_email',
                'Customer email is required.',
                array('status' => 400)
            );
        }

        if (empty($start_datetime)) {
            return new WP_Error(
                'missing_start_datetime',
                'Start datetime is required.',
                array('status' => 400)
            );
        }

        if (empty($end_datetime)) {
            return new WP_Error(
                'missing_end_datetime',
                'End datetime is required.',
                array('status' => 400)
            );
        }

        // Email validation.
        if (! is_email($customer_email)) {
            return new WP_Error(
                'invalid_customer_email',
                'Please provide a valid email address.',
                array('status' => 400)
            );
        }

        // Date-time validation.
        $start_time = DateTime::createFromFormat('Y-m-d H:i:s', $start_datetime);
        $end_time   = DateTime::createFromFormat('Y-m-d H:i:s', $end_datetime);

        if (! $start_time || ! $end_time) {
            return new WP_Error(
                'invalid_datetime',
                'Please provide a valid datetime format: Y-m-d H:i:s.',
                array('status' => 400)
            );
        }

        if ($end_time <= $start_time) {
            return new WP_Error(
                'invalid_booking_time',
                'End datetime must be after start datetime.',
                array('status' => 400)
            );
        }

        global $wpdb;


        //Business Hours Validation
        $settings = get_option( 'resource_booking_settings' );

        $day_name = strtolower( $start_time->format( 'l' ) );

        $business_hours = isset( $settings['business_hours'][ $day_name ] )
            ? $settings['business_hours'][ $day_name ]
            : null;

        // Check if the day is closed.
        if ( empty( $business_hours ) ) {
            return new WP_Error(
                'business_closed',
                'Bookings are not available on this day.',
                array( 'status' => 400 )
            );
        }

        $business_start = $business_hours[0];
        $business_end   = $business_hours[1];

        $booking_start_time = $start_time->format( 'H:i' );
        $booking_end_time   = $end_time->format( 'H:i' );

        // Check if booking is outside business hours.
        if (
            $booking_start_time < $business_start ||
            $booking_end_time > $business_end
        ) {
            return new WP_Error(
                'outside_business_hours',
                'Booking time must be within business hours.',
                array( 'status' => 400 )
            );
        }

        //Blackout Dates
        $blackout_dates = array(
            '2026-09-21',
            '2026-09-22'
        );
            
        // Blackout Dates Validation.
        $booking_date = $start_time->format( 'Y-m-d' );
        
        
        if ( in_array( $booking_date, $blackout_dates, true ) ) 
            {
            return new WP_Error(
                'blackout_date',
                'Bookings are not available on this date.',
                array( 'status' => 400 )
            );
        }
        error_log( print_r( $settings['blackout_dates'], true ) );

        // Resource existence check.
        $resources_table = $wpdb->prefix . 'rb_resources';

        $resource = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$resources_table} WHERE id = %d",
                $resource_id
            )
        );

        if (! $resource) {
            return new WP_Error(
                'resource_not_found',
                'The requested resource does not exist.',
                array('status' => 404)
            );
        }

        // Booking table.
        $bookings_table = $wpdb->prefix . 'rb_bookings';

        // Check for overlapping bookings.
        $existing_booking = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id
				FROM {$bookings_table}
				WHERE resource_id = %d
				AND status IN ('pending', 'confirmed')
				AND start_datetime < %s
				AND end_datetime > %s
				LIMIT 1",
                $resource_id,
                $end_datetime,
                $start_datetime
            )
        );

        if ($existing_booking) {
            return new WP_Error(
                'booking_conflict',
                'The requested time is already booked.',
                array('status' => 409)
            );
        }

        // Create booking.

        $settings = get_option('resource_booking_settings');

        $hold_duration = isset($settings['hold_duration'])
            ? absint($settings['hold_duration'])
            : 30;

        $expires_at = wp_date(
            'Y-m-d H:i:s',
            time() + ( $hold_duration * 60 )
        );

        $current_time = current_time('mysql');

        $result = $wpdb->insert(
            $bookings_table,
            array(
                'resource_id'    => $resource_id,
                'customer_name'  => $customer_name,
                'customer_email' => $customer_email,
                'start_datetime' => $start_datetime,
                'end_datetime'   => $end_datetime,
                'status'         => 'pending',
                'expires_at' => $expires_at,
                'created_at' => $current_time,
                'updated_at' => $current_time,
            ),
            array(
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            )
        );

        if (false === $result) {
            return new WP_Error(
                'booking_creation_failed',
                'Failed to create the booking.',
                array('status' => 500)
            );
        }

        return array(
            'success'    => true,
            'message'    => 'Booking request submitted successfully.',
            'booking_id' => $wpdb->insert_id,
            'status'     => 'pending',
        );
    }

    /**
     * Expire pending bookings.
     */
    public function expire_pending_bookings()
    {

        global $wpdb;

        $bookings_table = $wpdb->prefix . 'rb_bookings';

        $current_time = current_time('mysql');

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$bookings_table}
				SET status = 'expired',
					updated_at = %s
				WHERE status = 'pending'
				AND expires_at IS NOT NULL
				AND expires_at <= %s",
                $current_time,
                $current_time
            )
        );
    }

    /**
     * Register custom cron interval.
     */
    public function add_cron_interval($schedules)
    {

        $schedules['one_minute'] = array(
            'interval' => 60,
            'display'  => 'Every One Minute',
        );

        return $schedules;
    }

    /**
     * Schedule booking expiration.
     */
    public function schedule_expiration()
    {

        if (! wp_next_scheduled('resource_booking_expire_bookings')) {
            wp_schedule_event(
                time(),
                'one_minute',
                'resource_booking_expire_bookings'
            );
        }
    }
}
