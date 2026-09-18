<?php

/**
 * Handles Stripe payment gateway functionality for the Resource Booking plugin.
 *
 * @package Resource_Booking
 */

class Resource_Booking_Stripe
{

    /**
     * Register REST API routes for Stripe.
     */
    public function register_routes()
    {
        // Create a Stripe Checkout Session for a booking.
        register_rest_route(
            'resource-booking/v1',
            '/create-checkout-session',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'create_checkout_session' ),
                'permission_callback' => '__return_true',
            )
        );

        // Stripe webhook endpoint.
        register_rest_route(
            'resource-booking/v1',
            '/stripe-webhook',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_webhook' ),
                'permission_callback' => '__return_true',
            )
        );

        // Verify a booking payment directly against Stripe (fallback for
        // when a webhook could not be delivered, e.g. localhost testing).
        register_rest_route(
            'resource-booking/v1',
            '/verify-booking-payment',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'verify_booking_payment' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Write a line to the plugin debug log.
     *
     * @param string $message The message to log.
     * @return void
     */
    private function log( $message )
    {
        $upload_dir = wp_upload_dir();

        $log_file = trailingslashit( $upload_dir['basedir'] ) . 'resource-booking-debug.log';

        file_put_contents(
            $log_file,
            '[' . current_time( 'mysql' ) . '] ' . $message . "\n",
            FILE_APPEND
        );
    }

    /**
     * Get the Stripe API key settings.
     *
     * @return array
     */
    private function get_stripe_keys()
    {
        $settings = get_option( 'resource_booking_settings', array() );

        return array(
            'secret_key'       => isset( $settings['stripe_secret_key'] ) ? $settings['stripe_secret_key'] : '',
            'publishable_key'  => isset( $settings['stripe_publishable_key'] ) ? $settings['stripe_publishable_key'] : '',
            'webhook_secret'   => isset( $settings['stripe_webhook_secret'] ) ? $settings['stripe_webhook_secret'] : '',
        );
    }

    /**
     * Validate that Stripe is configured.
     *
     * @return bool|WP_Error True on success, WP_Error when not configured.
     */
    private function stripe_configured()
    {
        $keys = $this->get_stripe_keys();

        if ( empty( $keys['secret_key'] ) || empty( $keys['publishable_key'] ) ) {
            return new WP_Error(
                'stripe_not_configured',
                'Stripe payment is not configured. Please contact the administrator.',
                array( 'status' => 500 )
            );
        }

        return true;
    }

    /**
     * Create a Stripe Checkout Session for a given booking.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error
     */
    public function create_checkout_session( $request )
    {
        $booking_id = absint( $request->get_param( 'booking_id' ) );

        if ( ! $booking_id ) {
            return new WP_Error(
                'missing_booking_id',
                'Booking ID is required.',
                array( 'status' => 400 )
            );
        }

        $configured = $this->stripe_configured();

        if ( is_wp_error( $configured ) ) {
            return $configured;
        }

        global $wpdb;

        $bookings_table = $wpdb->prefix . 'rb_bookings';

        $booking = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$bookings_table} WHERE id = %d",
                $booking_id
            )
        );

        if ( ! $booking ) {
            return new WP_Error(
                'booking_not_found',
                'The booking does not exist.',
                array( 'status' => 404 )
            );
        }

        if ( 'paid' === $booking->payment_status ) {
            return array(
                'success'      => true,
                'already_paid' => true,
            );
        }

        if ( 'cancelled' === $booking->status || 'expired' === $booking->status ) {
            return new WP_Error(
                'booking_unavailable',
                'This booking is no longer available to pay for.',
                array( 'status' => 410 )
            );
        }

        $resources_table = $wpdb->prefix . 'rb_resources';

        $resource = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT name FROM {$resources_table} WHERE id = %d",
                $booking->resource_id
            )
        );

        $resource_name = $resource ? $resource->name : 'Resource';

        $amount_in_cents = round( (float) $booking->total_amount * 100 );

        $keys = $this->get_stripe_keys();

        // Build the payload for the Stripe API.
        $payload = array(
            'mode'             => 'payment',
            'success_url'      => $this->get_redirect_url( 'success', $booking_id ),
            'cancel_url'       => $this->get_redirect_url( 'cancelled', $booking_id ),
            'customer_email'   => $booking->customer_email,
            'client_reference_id' => (string) $booking_id,
            'line_items'       => array(
                array(
                    'quantity' => 1,
                    'price_data' => array(
                        'currency'     => $this->get_currency(),
                        'unit_amount'  => $amount_in_cents,
                        'product_data' => array(
                            'name'        => $resource_name,
                            'description' => 'Booking from ' . $booking->start_datetime . ' to ' . $booking->end_datetime,
                        ),
                    ),
                ),
            ),
            'metadata'         => array(
                'booking_id' => (string) $booking_id,
            ),
        );

        // Align the Stripe session expiry with the booking hold duration.
        $settings = get_option( 'resource_booking_settings', array() );

        $hold_duration = isset( $settings['hold_duration'] )
            ? max( 30, absint( $settings['hold_duration'] ) )
            : 30;

        $session_expires_at = time() + ( $hold_duration * 60 );

        $payload['expires_at'] = $session_expires_at;

        $response = wp_remote_post(
            'https://api.stripe.com/v1/checkout/sessions',
            array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $keys['secret_key'],
                ),
                'body'    => $this->flatten_params( $payload ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'stripe_request_failed',
                'Unable to connect to Stripe. Please try again later.',
                array( 'status' => 500 )
            );
        }

        $body = wp_remote_retrieve_body( $response );

        $data = json_decode( $body, true );

        $status_code = wp_remote_retrieve_response_code( $response );

        if ( 200 !== (int) $status_code || empty( $data['id'] ) ) {
            $error_message = isset( $data['error']['message'] )
                ? $data['error']['message']
                : 'Stripe could not create the checkout session.';

            return new WP_Error(
                'stripe_session_failed',
                $error_message,
                array( 'status' => 502 )
            );
        }

        // Save the Stripe session ID on the booking.
        $wpdb->update(
            $bookings_table,
            array(
                'stripe_session_id' => $data['id'],
                'updated_at'        => current_time( 'mysql' ),
            ),
            array( 'id' => $booking_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        return array(
            'success'      => true,
            'checkout_url' => $data['url'],
            'session_id'   => $data['id'],
            'booking_id'   => $booking_id,
        );
    }

    /**
     * Verify a booking's payment directly against Stripe.
     *
     * Used as a fallback when the webhook could not be delivered
     * (e.g. testing on localhost without the Stripe CLI running).
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error|array
     */
    public function verify_booking_payment( $request )
    {
        $booking_id = absint( $request->get_param( 'booking_id' ) );

        if ( ! $booking_id ) {
            return new WP_Error(
                'missing_booking_id',
                'Booking ID is required.',
                array( 'status' => 400 )
            );
        }

        $configured = $this->stripe_configured();

        if ( is_wp_error( $configured ) ) {
            return $configured;
        }

        return $this->sync_booking_payment( $booking_id );
    }

    /**
     * Check a booking against Stripe and update its payment status.
     *
     * Used by the verify REST endpoint and the admin "Sync" action so a
     * booking is marked paid even when the webhook could not be delivered.
     *
     * @param int $booking_id The booking ID.
     * @return array|WP_Error
     */
    public function sync_booking_payment( $booking_id )
    {
        global $wpdb;

        $bookings_table = $wpdb->prefix . 'rb_bookings';

        $booking = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$bookings_table} WHERE id = %d",
                $booking_id
            )
        );

        if ( ! $booking ) {
            return new WP_Error(
                'booking_not_found',
                'The booking does not exist.',
                array( 'status' => 404 )
            );
        }

        if ( 'paid' === $booking->payment_status ) {
            return array(
                'success'        => true,
                'booking_id'     => $booking_id,
                'payment_status' => 'paid',
            );
        }

        if ( empty( $booking->stripe_session_id ) ) {
            return array(
                'success'        => true,
                'booking_id'     => $booking_id,
                'payment_status' => $booking->payment_status,
            );
        }

        $session = $this->get_session( $booking->stripe_session_id );

        if ( is_wp_error( $session ) ) {
            return $session;
        }

        $payment_status = isset( $session['payment_status'] )
            ? $session['payment_status']
            : '';
        $session_status = isset( $session['status'] )
            ? $session['status']
            : '';

        if ( 'paid' === $payment_status && 'complete' === $session_status ) {
            $this->mark_booking_paid( $booking_id, $session );

            return array(
                'success'        => true,
                'booking_id'     => $booking_id,
                'payment_status' => 'paid',
            );
        }

        return array(
            'success'        => true,
            'booking_id'     => $booking_id,
            'payment_status' => $payment_status ? $payment_status : $booking->payment_status,
        );
    }

    /**
     * Retrieve a Stripe Checkout Session.
     *
     * @param string $session_id The Stripe Checkout Session ID.
     * @return array|WP_Error
     */
    private function get_session( $session_id )
    {
        $keys = $this->get_stripe_keys();

        $response = wp_remote_get(
            'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode( $session_id ),
            array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $keys['secret_key'],
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'stripe_request_failed',
                'Unable to connect to Stripe.',
                array( 'status' => 500 )
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== (int) $status_code || empty( $data['id'] ) ) {
            return new WP_Error(
                'stripe_session_lookup_failed',
                isset( $data['error']['message'] )
                    ? $data['error']['message']
                    : 'Unable to retrieve the Stripe session.',
                array( 'status' => 502 )
            );
        }

        return $data;
    }

    /**
     * Handle Stripe webhook events.
     */
    public function handle_webhook()
    {
        $keys = $this->get_stripe_keys();

        $payload       = file_get_contents( 'php://input' );
        $sig_header    = '';

        if ( function_exists( 'getallheaders' ) ) {
            $headers = getallheaders();

            if ( isset( $headers['Stripe-Signature'] ) ) {
                $sig_header = $headers['Stripe-Signature'];
            }
        }

        if ( empty( $sig_header ) && isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ) {
            $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        }

        if ( empty( $keys['webhook_secret'] ) ) {
            $this->log( 'Stripe webhook rejected: webhook secret is not configured.' );

            return new WP_REST_Response(
                array(
                    'error' => 'Webhook secret is not configured.',
                ),
                500
            );
        }

        $this->log( 'Stripe webhook received. Signature header present: ' . ( empty( $sig_header ) ? 'NO' : 'YES' ) );

        $event = $this->construct_event( $payload, $sig_header, $keys['webhook_secret'] );

        if ( is_wp_error( $event ) ) {
            $this->log( 'Stripe webhook rejected: ' . $event->get_error_code() . ' - ' . $event->get_error_message() );

            return new WP_REST_Response(
                array(
                    'error' => $event->get_error_message(),
                ),
                400
            );
        }

        if ( 'checkout.session.completed' === $event['type'] ) {
            $session = $event['data']['object'];

            $booking_id = 0;

            if ( isset( $session['client_reference_id'] ) ) {
                $booking_id = absint( $session['client_reference_id'] );
            } elseif ( isset( $session['metadata']['booking_id'] ) ) {
                $booking_id = absint( $session['metadata']['booking_id'] );
            }

            if ( $booking_id ) {
                $this->log( 'Stripe webhook checkout.session.completed for booking ' . $booking_id );
                $this->mark_booking_paid( $booking_id, $session );
            } else {
                $this->log( 'Stripe webhook: Booking ID not found in Checkout Session.' );
            }
        }

        if ( 'checkout.session.expired' === $event['type'] ) {
            $session = $event['data']['object'];

            $booking_id = absint( $session['client_reference_id'] );

            if ( $booking_id ) {
                $this->release_booking( $booking_id );
            }
        }

        return new WP_REST_Response(
            array( 'received' => true ),
            200
        );
    }

    /**
     * Verify Stripe webhook signature using the PHP-idiomatic approach.
     *
     * @param string $payload       Raw request payload.
     * @param string $sig_header    The Stripe-Signature header value.
     * @param string $webhook_secret The webhook signing secret.
     * @return array|WP_Error       The event array or WP_Error.
     */
    private function construct_event( $payload, $sig_header, $webhook_secret )
    {
        if ( empty( $sig_header ) ) {
            return new WP_Error(
                'missing_signature',
                'Missing Stripe signature header.'
            );
        }

        $tolerance = 300;

        // Parse the Stripe-Signature header segments.
        $timestamp = null;
        $signatures = array();

        foreach ( explode( ',', $sig_header ) as $part ) {
            $kv = explode( '=', $part, 2 );

            if ( 2 !== count( $kv ) ) {
                continue;
            }

            list( $key, $value ) = $kv;

            if ( 't' === $key ) {
                $timestamp = (int) $value;
            } elseif ( 'v1' === $key ) {
                $signatures[] = $value;
            }
        }

        if ( null === $timestamp || empty( $signatures ) ) {
            return new WP_Error(
                'invalid_signature_header',
                'Invalid Stripe signature header.'
            );
        }

        if ( ( time() - $timestamp ) > $tolerance ) {
            return new WP_Error(
                'signature_timestamp_too_old',
                'Stripe signature timestamp is too old.'
            );
        }

        $signed_payload = $timestamp . '.' . $payload;

        $expected = hash_hmac( 'sha256', $signed_payload, $webhook_secret );

        $signature_match = false;

        foreach ( $signatures as $signature ) {
            if ( hash_equals( $expected, $signature ) ) {
                $signature_match = true;
                break;
            }
        }

        if ( ! $signature_match ) {
            return new WP_Error(
                'signature_mismatch',
                'Stripe signature does not match.'
            );
        }

        $event = json_decode( $payload, true );

        $this->log( 'Stripe webhook event received. ID: ' . ( isset( $event['id'] ) ? $event['id'] : 'unknown' ) . ' Type: ' . ( isset( $event['type'] ) ? $event['type'] : 'unknown' ) );

        if ( ! is_array( $event ) || empty( $event['id'] ) ) {
            return new WP_Error(
                'invalid_payload',
                'Invalid webhook payload.'
            );
        }

        return $event;
    }

    /**
     * Mark a booking as paid.
     *
     * @param int   $booking_id The booking ID.
     * @param array $session    The Stripe session object.
     * @return void
     */
    private function mark_booking_paid( $booking_id, $session )
    {
        global $wpdb;

        $bookings_table = $wpdb->prefix . 'rb_bookings';

        $booking = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$bookings_table} WHERE id = %d",
                $booking_id
            )
        );

        if ( ! $booking ) {
            return;
        }

        if ( 'cancelled' === $booking->status ) {
            $this->log( 'Stripe payment received for booking ' . $booking_id . ' but the booking was cancelled. Payment not applied.' );
            return;
        }

        $payment_intent = isset( $session['payment_intent'] )
            ? $session['payment_intent']
            : '';

        $updated = $wpdb->update(
            $bookings_table,
            array(
                'payment_status'        => 'paid',
                'stripe_session_id'     => isset( $session['id'] ) ? $session['id'] : '',
                'stripe_payment_intent' => $payment_intent,
                'status'                => 'confirmed',
                'expires_at'            => null,
                'updated_at'            => current_time( 'mysql' ),
            ),
            array( 'id' => $booking_id ),
            array( '%s', '%s', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        $this->log( 'Booking ' . $booking_id . ' marked paid. DB update result: ' . ( false === $updated ? 'failed' : 'success' ) );

        // Payment confirmation email.
        $resources_table = $wpdb->prefix . 'rb_resources';

        $resource_name = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT name FROM {$resources_table} WHERE id = %d",
                $booking->resource_id
            )
        );

        wp_mail(
            $booking->customer_email,
            'Booking Confirmed - Payment Received',
            'Hello ' . $booking->customer_name . ",\n\n" .
            'Thank you for your payment. Your booking has been confirmed successfully.' . "\n\n" .
            'Resource: ' . $resource_name . "\n" .
            'Start: ' . $booking->start_datetime . "\n" .
            'End: ' . $booking->end_datetime . "\n" .
            'Total Paid: ' . $booking->total_amount . "\n" .
            'Status: Paid & Confirmed' . "\n\n" .
            'Thank you.'
        );

        $admin_email = get_option( 'admin_email' );
        wp_mail(
            $admin_email,
            'Payment Received for Booking',
            'Hello Admin,' . "\n\n" .
            'Payment has been received for a booking.' . "\n\n" .
            'Booking ID: ' . $booking->id . "\n" .
            'Customer Name: ' . $booking->customer_name . "\n" .
            'Customer Email: ' . $booking->customer_email . "\n" .
            'Resource: ' . $resource_name . "\n" .
            'Total: ' . $booking->total_amount . "\n\n" .
            'The booking is now confirmed.'
        );
    }

    /**
     * Release a booking whose checkout session expired / was abandoned.
     *
     * @param int $booking_id The booking ID.
     * @return void
     */
    private function release_booking( $booking_id )
    {
        global $wpdb;

        $bookings_table = $wpdb->prefix . 'rb_bookings';

        $wpdb->update(
            $bookings_table,
            array(
                'status'     => 'cancelled',
                'updated_at' => current_time( 'mysql' ),
            ),
            array(
                'id'             => $booking_id,
                'payment_status' => 'unpaid',
            ),
            array( '%s', '%s' ),
            array( '%d', '%s' )
        );
    }

    /**
     * Get the currency code for Stripe charges.
     *
     * @return string
     */
    private function get_currency()
    {
        return 'inr';
    }

    /**
     * Build the redirect URL returned to the user after Stripe checkout.
     *
     * @param string $result     success|cancelled
     * @param int    $booking_id The booking ID.
     * @return string
     */
    private function get_redirect_url( $result, $booking_id )
    {
        $booking_url = get_transient( 'resource_booking_page_url' );

        if ( ! $booking_url ) {
            $booking_url = home_url( '/resources-booking/' );

            foreach ( get_pages( array( 'post_status' => 'publish' ) ) as $page ) {
                if ( has_shortcode( $page->post_content, 'test_booking_form' ) ) {
                    $booking_url = get_permalink( $page );
                    break;
                }
            }
        }

        $url = add_query_arg(
            array(
                'rb_payment' => $result,
                'booking_id' => $booking_id,
            ),
            $booking_url
        );

        return $url;
    }

    /**
     * Flatten a nested array into Stripe's form-encoded parameter style.
     *
     * @param array  $params Nested params.
     * @param string $prefix Optional key prefix.
     * @return array
     */
    private function flatten_params( $params, $prefix = '' )
    {
        $output = array();

        foreach ( $params as $key => $value ) {
            $full_key = $prefix ? "{$prefix}[{$key}]" : $key;

            if ( is_array( $value ) ) {
                $output = array_merge( $output, $this->flatten_params( $value, $full_key ) );
            } else {
                $output[ $full_key ] = $value;
            }
        }

        return $output;
    }
}