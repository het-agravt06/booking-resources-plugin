<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Resource_Booking
 * @subpackage Resource_Booking/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Resource_Booking
 * @subpackage Resource_Booking/admin
 * @author     Het Agravat <agravathet51@gmail.com>
 */
class Resource_Booking_Admin
{
	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($plugin_name, $version)
	{

		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}


	/** 
	 * Register the Resource Booking admin menu. 
	 * 
	 *  @since 1.0.0 
	 */

	public function add_admin_menu()
	{
		add_menu_page(
			'Resource Booking',
			'Resource Booking',
			'manage_options',
			'resource-booking',
			array(
				$this,
				'resource_booking_page'
			),
			'dashicons-calendar-alt',
			25
		);
		add_submenu_page(
			'resource-booking',
			'Resources',
			'Resources',
			'manage_options',
			'resource-booking',
			array($this, 'resource_booking_page')
		);
		add_submenu_page(
			'resource-booking',
			'Bookings',
			'Bookings',
			'manage_options',
			'resource-booking-bookings',
			array( $this, 'display_bookings_page' )
		);
		add_submenu_page(
			null,
			'Edit Booking',
			'Edit Booking',
			'manage_options',
			'resource-booking-edit-booking',
			array( $this, 'edit_booking_page' )
		);
		
	}

	//form action button handling 

	public function handle_booking_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['resource_booking_action'] ) ) {
			return;
		}

		$action = sanitize_text_field(
			wp_unslash( $_POST['resource_booking_action'] )
		);

		if ( ! in_array( $action, array( 'confirm', 'reject', 'edit' ), true ) ) {
			return;
		}

		if ( 'confirm' === $action ) {
			$nonce_action = 'resource_booking_confirm';
		} elseif ( 'reject' === $action ) {
			$nonce_action = 'resource_booking_reject';
		} else {
			$nonce_action = 'resource_booking_edit';
		}

		if (
			! isset( $_POST['resource_booking_nonce'] ) &&
			! isset( $_POST['resource_booking_edit_nonce'] )
		) {
			return;
		}

		$nonce = isset( $_POST['resource_booking_edit_nonce'] )
			? $_POST['resource_booking_edit_nonce']
			: $_POST['resource_booking_nonce'];

		if (
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $nonce ) ),
				$nonce_action
			)
		) {
			return;
		}

		$booking_id = isset( $_POST['booking_id'] )
			? absint( $_POST['booking_id'] )
			: 0;

		if ( ! $booking_id ) {
			return;
		}

	global $wpdb;

	$bookings_table = $wpdb->prefix . 'rb_bookings';

	$booking = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT *
			FROM {$bookings_table}
			WHERE id = %d",
			$booking_id
		)
	);

	if ( ! $booking ) {
		return;
	}

	if ( 'reject' === $action ) {

		$updated = $wpdb->update(
			$bookings_table,
			array(
				'status'     => 'cancelled',
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'id' => $booking_id,
			),
			array(
				'%s',
				'%s',
			),
			array(
				'%d',
			)
		);

		if ( false === $updated ) {
			return;
		}

		// sending an email
		// 4. Booking Cancelled → Customer
		$resources_table = $wpdb->prefix . 'rb_resources';

		$resource_name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT name
				FROM {$resources_table}
				WHERE id = %d",
				$booking->resource_id
			)
		);

		wp_mail(
			$booking->customer_email,
			'Booking Cancelled',
			'Hello ' . $booking->customer_name . ",\n\n" .
			'Your booking request has been cancelled.' . "\n\n" .
			'Resource: ' . $resource_name . "\n" .
			'Start: ' . $booking->start_datetime . "\n" .
			'End: ' . $booking->end_datetime . "\n" .
			'Status: Cancelled' . "\n\n" .
			'Thank you.'
		);
		wp_safe_redirect(
			admin_url( 'admin.php?page=resource-booking-bookings&rejected=1' )
		);
		exit;
	}

	if ( 'edit' === $action ) {

		$resource_id = isset( $_POST['resource_id'] )
			? absint( $_POST['resource_id'] )
			: 0;

		$customer_name = isset( $_POST['customer_name'] )
			? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) )
			: '';

		$customer_email = isset( $_POST['customer_email'] )
			? sanitize_email( wp_unslash( $_POST['customer_email'] ) )
			: '';

		$start_datetime = isset( $_POST['start_datetime'] )
			? sanitize_text_field( wp_unslash( $_POST['start_datetime'] ) )
			: '';

		$end_datetime = isset( $_POST['end_datetime'] )
			? sanitize_text_field( wp_unslash( $_POST['end_datetime'] ) )
			: '';

		$status = isset( $_POST['status'] )
			? sanitize_text_field( wp_unslash( $_POST['status'] ) )
			: '';

		//email validation
		if (
			! $resource_id ||
			empty( $customer_name ) ||
			empty( $customer_email ) ||
			empty( $start_datetime ) ||
			empty( $end_datetime ) ||
			empty( $status )
		) {
			return;
		}

		if ( ! in_array( $status, array( 'pending', 'confirmed', 'cancelled', 'expired' ), true ) ) {
			return;
		}

		if ( 'confirmed' === $booking->status && 'pending' === $status ) {
			return;
		}

		if ( 'cancelled' === $booking->status && 'pending' === $status ) {
			return;
		}

		if ( 'expired' === $booking->status && 'pending' === $status ) {
			return;
		}



		if ( ! is_email( $customer_email ) ) {
			return;
		}

		//date time validation
		$start_time = DateTime::createFromFormat(
			'Y-m-d\TH:i',
			$start_datetime
		);

		$end_time = DateTime::createFromFormat(
			'Y-m-d\TH:i',
			$end_datetime
		);

		if ( ! $start_time || ! $end_time ) {
			return;
		}
		
		if ( $start_time >= $end_time ) {
			return;
		}

		// business hour validation
		$settings = get_option( 'resource_booking_settings' );

		$day_name = strtolower( $start_time->format( 'l' ) );

		$business_hours = isset( $settings['business_hours'][ $day_name ] )
			? $settings['business_hours'][ $day_name ]
			: null;

		if ( empty( $business_hours ) ) {
			return;
		}

		$business_start = $business_hours[0];
		$business_end   = $business_hours[1];

		$booking_start_time = $start_time->format( 'H:i' );
		$booking_end_time   = $end_time->format( 'H:i' );

		if (
			$booking_start_time < $business_start ||
			$booking_end_time > $business_end
		) {
			return;
		}
		
		//blackout date validation
		$blackout_dates = isset( $settings['blackout_dates'] )
			? $settings['blackout_dates']
			: array();

		$booking_date = $start_time->format( 'Y-m-d' );

		if ( in_array( $booking_date, $blackout_dates, true ) ) {
			return;
		}
		
		//resource validation + overlap check.
		$resources_table = $wpdb->prefix . 'rb_resources';

		$resource_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id
				FROM {$resources_table}
				WHERE id = %d",
				$resource_id
			)
		);

		if ( ! $resource_exists ) {
			return;
		}	
		
		//Check Overlapping Booking
		$overlapping_booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id
				FROM {$bookings_table}
				WHERE resource_id = %d
				AND id != %d
				AND status IN ('pending', 'confirmed')
				AND start_datetime < %s
				AND end_datetime > %s
				LIMIT 1",
				$resource_id,
				$booking_id,
				$start_time->format( 'Y-m-d H:i:s' ),
				$end_time->format( 'Y-m-d H:i:s' )
			)
		);

		if ( $overlapping_booking ) {
			return;
		}
		
		//update booking in database
		$updated = $wpdb->update(
			$bookings_table,
			array(
				'resource_id'    => $resource_id,
				'customer_name'  => $customer_name,
				'customer_email' => $customer_email,
				'start_datetime' => $start_time->format( 'Y-m-d H:i:s' ),
				'end_datetime'   => $end_time->format( 'Y-m-d H:i:s' ),
				'status'         => $status,
				'updated_at'     => current_time( 'mysql' ),
			),
			array(
				'id' => $booking_id,
			),
			array(
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			),
			array(
				'%d',
			)
		);

		if ( false === $updated ) {
			return;
		}

		// . Booking Updated → Customer
		$resources_table = $wpdb->prefix . 'rb_resources';

		$resource_name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT name
				FROM {$resources_table}
				WHERE id = %d",
				$resource_id
			)
		);

		wp_mail(
			$customer_email,
			'Booking Updated',
			'Hello ' . $customer_name . ",\n\n" .
			'Your booking has been updated successfully.' . "\n\n" .
			'Resource: ' . $resource_name . "\n" .
			'Start: ' . $start_time->format( 'Y-m-d H:i:s' ) . "\n" .
			'End: ' . $end_time->format( 'Y-m-d H:i:s' ) . "\n" .
			'Status: ' . ucfirst( $status ) . "\n\n" .
			'Thank you.'
		);

		wp_safe_redirect(
			admin_url( 'admin.php?page=resource-booking-bookings&updated=1' )
		);
		exit;

	}

	$overlapping_booking = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id
			FROM {$bookings_table}
			WHERE resource_id = %d
			AND id != %d
			AND status IN ('pending', 'confirmed')
			AND start_datetime < %s
			AND end_datetime > %s
			LIMIT 1",
			$booking->resource_id,
			$booking_id,
			$booking->end_datetime,
			$booking->start_datetime
		)
	);

	if ( $overlapping_booking ) {
		return;
	}


	$updated = $wpdb->update(
		$bookings_table,
		array(
			'status'     => 'confirmed',
			'updated_at' => current_time( 'mysql' ),
		),
		array(
			'id' => $booking_id,
		),
		array(
			'%s',
			'%s',
		),
		array(
			'%d',
		)
	);

	if ( false === $updated ) {
		return;
	}
	//email sending
		// 3. Booking Confirmed → Customer
			$resources_table = $wpdb->prefix . 'rb_resources';

			$resource_name = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT name
					FROM {$resources_table}
					WHERE id = %d",
					$booking->resource_id
				)
			);

			wp_mail(
				$booking->customer_email,
				'Booking Confirmed',
				'Hello ' . $booking->customer_name . ",\n\n" .
				'Your booking has been confirmed successfully.' . "\n\n" .
				'Resource: ' . $resource_name . "\n" .
				'Start: ' . $booking->start_datetime . "\n" .
				'End: ' . $booking->end_datetime . "\n" .
				'Status: Confirmed' . "\n\n" .
				'Thank you.'
			);
	
	wp_safe_redirect(
		admin_url( 'admin.php?page=resource-booking-bookings&confirmed=1' )
	);
	exit;
	// We will add the database update here next.
}
	public function display_bookings_page() {

		global $wpdb;

		$status = isset( $_GET['status'] )
			? sanitize_text_field( wp_unslash( $_GET['status'] ) )
			: '';

		$resource_id = isset( $_GET['resource_id'] )
			? absint( $_GET['resource_id'] )
			: 0;

		$bookings_table  = $wpdb->prefix . 'rb_bookings';
		$resources_table = $wpdb->prefix . 'rb_resources';

		$resources = $wpdb->get_results(
			"SELECT id, name
			FROM {$resources_table}
			ORDER BY name ASC"
		);

		$where = array();

		if ( ! empty( $status ) ) {
			$where[] = $wpdb->prepare(
				'bookings.status = %s',
				$status
			);
		}

		if ( ! empty( $resource_id ) ) {
			$where[] = $wpdb->prepare(
				'bookings.resource_id = %d',
				$resource_id
			);
		}

		$where_sql = '';

		if ( ! empty( $where ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where );
		}

		//pagination
		$per_page = 3;

		$current_page = isset( $_GET['paged'] )
			? max( 1, absint( $_GET['paged'] ) )
			: 1;

		$offset = ( $current_page - 1 ) * $per_page;

		$total_bookings = $wpdb->get_var(
			"SELECT COUNT(*)
			FROM {$bookings_table} AS bookings
			{$where_sql}"
		);

		$total_pages = ceil( $total_bookings / $per_page );

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT bookings.*, resources.name AS resource_name
				FROM {$bookings_table} AS bookings
				LEFT JOIN {$resources_table} AS resources
					ON bookings.resource_id = resources.id
				{$where_sql}
				ORDER BY bookings.id DESC
				LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		echo '<div class="wrap">';
		echo '<h1>Bookings</h1>';


		echo '<form method="get" id="resource-booking-filters">';

		echo '<input type="hidden" name="page" value="resource-booking-bookings">';

		//Add Status dropdown
		echo '<select name="status" id="rb_status_filter">';

		echo '<option value="">All Statuses</option>';
		echo '<option value="pending"' . selected( $status, 'pending', false ) . '>Pending</option>';
		echo '<option value="confirmed"' . selected( $status, 'confirmed', false ) . '>Confirmed</option>';
		echo '<option value="cancelled"' . selected( $status, 'cancelled', false ) . '>Cancelled</option>';
		echo '<option value="expired"' . selected( $status, 'expired', false ) . '>Expired</option>';

		echo '</select>';

		// 	Add Resource dropdown
		echo '<select name="resource_id" id="rb_resource_filter">';

		echo '<option value="0">All Resources</option>';
			foreach ( $resources as $resource ) {
				echo '<option value="' . esc_attr( $resource->id ) . '"'
					. selected( $resource_id, $resource->id, false ) . '>'
					. esc_html( $resource->name )
					. '</option>';
			}
		echo '</select>';


		echo '<button type="button" class="button" id="rb_apply_filters">Filter</button>';

		echo '</form>';

		echo '<div id="resource-booking-bookings" data-current-page="' . esc_attr( $current_page ) . '">';
		echo $this->get_bookings_table_html( $status, $resource_id, $current_page );
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Render the bookings table HTML (used by both the page and AJAX).
	 */
	private function get_bookings_table_html( $status, $resource_id, $current_page ) {

		global $wpdb;

		$bookings_table  = $wpdb->prefix . 'rb_bookings';
		$resources_table = $wpdb->prefix . 'rb_resources';

		$where = array();

		if ( ! empty( $status ) ) {
			$where[] = $wpdb->prepare(
				'bookings.status = %s',
				$status
			);
		}

		if ( ! empty( $resource_id ) ) {
			$where[] = $wpdb->prepare(
				'bookings.resource_id = %d',
				$resource_id
			);
		}

		$where_sql = '';

		if ( ! empty( $where ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where );
		}

		$per_page = 3;
		$offset   = ( $current_page - 1 ) * $per_page;

		$total_bookings = (int) $wpdb->get_var(
			"SELECT COUNT(*)
			FROM {$bookings_table} AS bookings
			{$where_sql}"
		);

		$total_pages = max( 1, (int) ceil( $total_bookings / $per_page ) );

		if ( $current_page > $total_pages ) {
			$current_page = $total_pages;
			$offset       = ( $current_page - 1 ) * $per_page;
		}

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT bookings.*, resources.name AS resource_name
				FROM {$bookings_table} AS bookings
				LEFT JOIN {$resources_table} AS resources
					ON bookings.resource_id = resources.id
				{$where_sql}
				ORDER BY bookings.id DESC
				LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		ob_start();

		if ( empty( $bookings ) ) {
			echo '<p>No bookings found.</p>';
			return ob_get_clean();
		}

		echo '<table class="widefat fixed striped">';
		echo '<thead>';
		echo '<tr>';
		echo '<th>ID</th>';
		echo '<th>Resource</th>';
		echo '<th>Customer Name</th>';
		echo '<th>Email</th>';
		echo '<th>Start</th>';
		echo '<th>End</th>';
		echo '<th>Status</th>';
		echo '<th>Actions</th>';
		echo '</tr>';
		echo '</thead>';

		echo '<tbody>';

		foreach ( $bookings as $booking ) {

			echo '<tr>';
			echo '<td>' . esc_html( $booking->id ) . '</td>';
			echo '<td>' . esc_html( $booking->resource_name ) . '</td>';
			echo '<td>' . esc_html( $booking->customer_name ) . '</td>';
			echo '<td>' . esc_html( $booking->customer_email ) . '</td>';
			echo '<td>' . esc_html( $booking->start_datetime ) . '</td>';
			echo '<td>' . esc_html( $booking->end_datetime ) . '</td>';
			echo '<td><span class="rb-status rb-status-' . esc_attr( $booking->status ) . '">'
				. esc_html( ucfirst( $booking->status ) ) . '</span></td>';

			echo '<td>';
			echo '<a href="' . esc_url(
				admin_url( 'admin.php?page=resource-booking-edit-booking&booking_id=' . $booking->id )
			) . '" class="button">Edit</a> ';

			if ( 'pending' === $booking->status ) {
				echo '<button type="button" class="button button-primary rb-booking-action" data-booking-id="'
					. esc_attr( $booking->id ) . '" data-rb-action="confirm">Confirm</button> ';
				echo '<button type="button" class="button rb-booking-action" data-booking-id="'
					. esc_attr( $booking->id ) . '" data-rb-action="reject">Reject</button>';
			}

			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';

		if ( $total_pages > 1 ) {

			$page_url = admin_url( 'admin.php?page=resource-booking-bookings' );

			if ( ! empty( $status ) ) {
				$page_url = add_query_arg( 'status', $status, $page_url );
			}

			if ( ! empty( $resource_id ) ) {
				$page_url = add_query_arg( 'resource_id', $resource_id, $page_url );
			}

			echo '<div class="tablenav" style="margin-top:20px;">';
			echo '<div class="tablenav-pages" style="float:none; text-align:center;">';

			if ( $current_page > 1 ) {
				echo '<a href="' . esc_url( add_query_arg( 'paged', $current_page - 1, $page_url ) )
					. '" class="rb-pagination-link" data-page="'
					. esc_attr( $current_page - 1 ) . '">&laquo; Previous</a>';
			}

			for ( $i = 1; $i <= $total_pages; $i++ ) {
				if ( $i === $current_page ) {
					echo '<span class="page-numbers current" aria-current="page">'
						. esc_html( $i ) . '</span>';
				} else {
					echo '<a href="' . esc_url( add_query_arg( 'paged', $i, $page_url ) )
						. '" class="rb-pagination-link" data-page="' . esc_attr( $i ) . '">'
						. esc_html( $i ) . '</a>';
				}
			}

			if ( $current_page < $total_pages ) {
				echo '<a href="' . esc_url( add_query_arg( 'paged', $current_page + 1, $page_url ) )
					. '" class="rb-pagination-link" data-page="'
					. esc_attr( $current_page + 1 ) . '">Next &raquo;</a>';
			}

			echo '</div>';
			echo '</div>';
		}

		return ob_get_clean();
	}

	/**
	 * AJAX handler: load filtered/paginated bookings table.
	 */
	public function ajax_get_bookings() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'You do not have permission to view bookings.' ) );
		}

		check_ajax_referer( 'resource_booking_admin', 'nonce' );

		$status       = isset( $_POST['status'] )
			? sanitize_text_field( wp_unslash( $_POST['status'] ) )
			: '';
		$resource_id  = isset( $_POST['resource_id'] )
			? absint( $_POST['resource_id'] )
			: 0;
		$current_page = isset( $_POST['paged'] )
			? max( 1, absint( $_POST['paged'] ) )
			: 1;

		$allowed_statuses = array( 'pending', 'confirmed', 'cancelled', 'expired' );

		if ( $status && ! in_array( $status, $allowed_statuses, true ) ) {
			$status = '';
		}

		$html = $this->get_bookings_table_html( $status, $resource_id, $current_page );

		wp_send_json_success( array(
			'html'         => $html,
			'current_page' => $current_page,
		) );
	}

	/**
	 * AJAX handler: confirm or reject a booking.
	 */
	public function ajax_update_booking_status() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'You do not have permission to manage bookings.' ) );
		}

		check_ajax_referer( 'resource_booking_admin', 'nonce' );

		$booking_id = isset( $_POST['booking_id'] )
			? absint( $_POST['booking_id'] )
			: 0;
		$action     = isset( $_POST['rb_action'] )
			? sanitize_text_field( wp_unslash( $_POST['rb_action'] ) )
			: '';

		if ( ! $booking_id || ! in_array( $action, array( 'confirm', 'reject' ), true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid request.' ) );
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
			wp_send_json_error( array( 'message' => 'Booking not found.' ) );
		}

		if ( 'pending' !== $booking->status ) {
			wp_send_json_error( array( 'message' => 'This booking can no longer be confirmed or rejected.' ) );
		}

		$new_status = ( 'confirm' === $action ) ? 'confirmed' : 'cancelled';

		if ( 'confirm' === $action ) {
			$overlapping = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id
					FROM {$bookings_table}
					WHERE resource_id = %d
					AND id != %d
					AND status IN ('pending', 'confirmed')
					AND start_datetime < %s
					AND end_datetime > %s
					LIMIT 1",
					$booking->resource_id,
					$booking_id,
					$booking->end_datetime,
					$booking->start_datetime
				)
			);

			if ( $overlapping ) {
				wp_send_json_error( array( 'message' => 'Cannot confirm: the time overlaps with another booking.' ) );
			}
		}

		$updated = $wpdb->update(
			$bookings_table,
			array(
				'status'     => $new_status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $booking_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => 'Failed to update the booking.' ) );
		}

		// Send email notification.
		$resources_table = $wpdb->prefix . 'rb_resources';
		$resource_name   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT name FROM {$resources_table} WHERE id = %d",
				$booking->resource_id
			)
		);

		if ( 'confirmed' === $new_status ) {
			wp_mail(
				$booking->customer_email,
				'Booking Confirmed',
				'Hello ' . $booking->customer_name . ",\n\n" .
				'Your booking has been confirmed successfully.' . "\n\n" .
				'Resource: ' . $resource_name . "\n" .
				'Start: ' . $booking->start_datetime . "\n" .
				'End: ' . $booking->end_datetime . "\n" .
				'Status: Confirmed' . "\n\n" .
				'Thank you.'
			);
		} else {
			wp_mail(
				$booking->customer_email,
				'Booking Cancelled',
				'Hello ' . $booking->customer_name . ",\n\n" .
				'Your booking request has been cancelled.' . "\n\n" .
				'Resource: ' . $resource_name . "\n" .
				'Start: ' . $booking->start_datetime . "\n" .
				'End: ' . $booking->end_datetime . "\n" .
				'Status: Cancelled' . "\n\n" .
				'Thank you.'
			);
		}

		wp_send_json_success( array(
			'message'    => ( 'confirmed' === $new_status )
				? 'Booking confirmed successfully.'
				: 'Booking cancelled successfully.',
			'booking_id' => $booking_id,
			'status'     => $new_status,
		) );
	}

	//edit booking 
	public function edit_booking_page() {

		global $wpdb;

		$booking_id = isset( $_GET['booking_id'] )
			? absint( $_GET['booking_id'] )
			: 0;

		if ( ! $booking_id ) {
			echo '<div class="wrap">';
			echo '<p>Invalid booking.</p>';
			echo '</div>';
			return;
		}

		$bookings_table = $wpdb->prefix . 'rb_bookings';

		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *
				FROM {$bookings_table}
				WHERE id = %d",
				$booking_id
			)
		);

		if ( ! $booking ) {
			echo '<div class="wrap">';
			echo '<p>Booking not found.</p>';
			echo '</div>';
			return;
		}

		$resources_table = $wpdb->prefix . 'rb_resources';

		$resources = $wpdb->get_results(
			"SELECT id, name
			FROM {$resources_table}
			ORDER BY name ASC"
		);

		echo '<div class="wrap">';
		echo '<h1>Edit Booking</h1>';

		echo '<form method="post">';

		wp_nonce_field( 'resource_booking_edit', 'resource_booking_edit_nonce' );

		echo '<input type="hidden" name="booking_id" value="' . esc_attr( $booking->id ) . '">';
		echo '<input type="hidden" name="resource_booking_action" value="edit">';

		echo '<table class="form-table">';
		
			echo '<tr>';
			echo '<th><label for="resource_id">Resource</label></th>';
			echo '<td>';

			echo '<select name="resource_id" id="resource_id">';

			foreach ( $resources as $resource ) {

				echo '<option value="' . esc_attr( $resource->id ) . '"'
					. selected( $booking->resource_id, $resource->id, false ) . '>'
					. esc_html( $resource->name )
					. '</option>';
			}

			echo '</select>';

			echo '</td>';
			echo '</tr>';

		echo '<tr>';
			echo '<th><label for="customer_name">Customer Name</label></th>';
			echo '<td><input type="text" name="customer_name" id="customer_name" value="' . esc_attr( $booking->customer_name ) . '" class="regular-text"></td>';
		echo '</tr>';

		echo '<tr>';
			echo '<th><label for="customer_email">Customer Email</label></th>';
			echo '<td><input type="email" name="customer_email" id="customer_email" value="' . esc_attr( $booking->customer_email ) . '" class="regular-text"></td>';
		echo '</tr>';

		echo '<tr>';
			echo '<th><label for="start_datetime">Start Date & Time</label></th>';
			echo '<td><input type="datetime-local" name="start_datetime" id="start_datetime" value="' . esc_attr( date( 'Y-m-d\TH:i', strtotime( $booking->start_datetime ) ) ) . '"></td>';
		echo '</tr>';

		echo '<tr>';
			echo '<th><label for="end_datetime">End Date & Time</label></th>';
			echo '<td><input type="datetime-local" name="end_datetime" id="end_datetime" value="' . esc_attr( date( 'Y-m-d\TH:i', strtotime( $booking->end_datetime ) ) ) . '"></td>';
		echo '</tr>';

		echo '<tr>';
			echo '<th><label for="status">Booking Status</label></th>';
			echo '<td>';

				echo '<select name="status" id="status">';
				echo '<option value="pending"' . selected( $booking->status, 'pending', false ) . '>Pending</option>';
				echo '<option value="confirmed"' . selected( $booking->status, 'confirmed', false ) . '>Confirmed</option>';
				echo '<option value="cancelled"' . selected( $booking->status, 'cancelled', false ) . '>Cancelled</option>';
				echo '<option value="expired"' . selected( $booking->status, 'expired', false ) . '>Expired</option>';
				echo '</select>';

			echo '</td>';
		echo '</tr>';

		echo '</table>';

		submit_button( 'Update Booking' );

		echo '</form>';

		echo '</div>';
	}

	/**
	 * Display the Resource Booking page. 
	 * 
	 * @since 1.0.0 
	 */

	public function resource_booking_page()
	{
		if (isset($_GET['action']) && 'add' === $_GET['action']) {
			$this->add_resource_page();
			return;
		}

		if (isset($_GET['action']) && 'edit' === $_GET['action']) {
			$this->edit_resource_page();
			return;
		}

		if (isset($_GET['action']) && 'delete' === $_GET['action']) {
			$this->delete_resource();
			return;
		}

		//database retrieval 
		global $wpdb;

		$resources_table = $wpdb->prefix . 'rb_resources';

		$resources = $wpdb->get_results(
			"SELECT * FROM {$resources_table} ORDER BY id DESC"
		);


		echo '<div class="wrap">';
		echo '<h1>Resource Management</h1>';

		echo '<p>Manage your bookable resources here.</p>';

		echo '<a href="' . admin_url('admin.php?page=resource-booking&action=add') . '" class="page-title-action">Add New Resource</a>';

		//display resource list
		echo '<table class="widefat fixed striped">';
		echo '<thead>';
		echo '<tr>';
		echo '<th>Name</th>';
		echo '<th>Description</th>';
		echo '<th>Capacity</th>';
		echo '<th>Created</th>';
		echo '<th>Actions</th>';
		echo '</tr>';
		echo '</thead>';

		echo '<tbody>';

		if (! empty($resources)) {

			foreach ($resources as $resource) {

				echo '<tr>';

				echo '<td>' . esc_html($resource->name) . '</td>';
				echo '<td>' . esc_html($resource->description) . '</td>';
				echo '<td>' . esc_html($resource->capacity) . '</td>';
				echo '<td>' . esc_html($resource->created_at) . '</td>';

				//action (edit/delete)
				echo '<td>';
				//edit
				echo '<a href="' . admin_url('admin.php?page=resource-booking&action=edit&id=' . $resource->id) . '">Edit</a>';
				echo ' | ';
				//delete
				$delete_url = wp_nonce_url(
					admin_url('admin.php?page=resource-booking&action=delete&id=' . $resource->id),
					'resource_booking_delete_resource_' . $resource->id
				);

				echo '<a href="' . esc_url($delete_url) . '">Delete</a>';
				echo '</td>';
				echo '</tr>';
			}
		} else {

			echo '<tr>';
			echo '<td colspan="5">No resources found.</td>';
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';

		echo '</div>';
	}

	public function add_resource_page()
	{
		if (isset($_POST['resource_booking_save'])) {

			check_admin_referer(
				'resource_booking_add_resource',
				'resource_booking_nonce'
			);

			if (! current_user_can('manage_options')) {
				wp_die('You do not have permission to manage resources.');
			}

			//capability check
			$resource_name        = isset($_POST['resource_name']) ? sanitize_text_field(wp_unslash($_POST['resource_name'])) : '';  //"10" → 10
			$resource_description = isset($_POST['resource_description']) ? sanitize_textarea_field(wp_unslash($_POST['resource_description'])) : '';  //"5abc" → 5
			$resource_capacity    = isset($_POST['resource_capacity']) ? absint($_POST['resource_capacity']) : 0;   // e.g. "-3" → 0
			$resource_image_ids = isset( $_POST['resource_image_ids'] )
				? array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['resource_image_ids'] ) ) ) )
				: array();
			$resource_image_ids = array_filter( $resource_image_ids );

			//validate value
			if (empty($resource_name)) {
				wp_die('Resource name is required.');
			}

			if ($resource_capacity < 1) {
				wp_die('Resource capacity must be at least 1.');
			}

			//get wpdb object
			global $wpdb;

			$resources_table = $wpdb->prefix . 'rb_resources';

			$result = $wpdb->insert(
				$resources_table,
				array(
					'name'        => $resource_name,
					'description' => $resource_description,
					'image_id' => maybe_serialize($resource_image_ids),
					'capacity'    => $resource_capacity,
					'created_at'  => current_time('mysql'),
					'updated_at'  => current_time('mysql'),
				),
				array(
					'%s',
					'%s',
					'%s',
					'%d',
					'%s',
					'%s',
				)
			);
			if (false === $result) {
				wp_die('Failed to save the resource.');
			}

			echo '<div class="notice notice-success is-dismissible">';
			echo '<p>Resource saved successfully.</p>';
			echo '</div>';
		}

		echo '<div class="wrap">';
		echo '<h1>Add New Resource</h1>';
		echo '<form method="post">';

		//nonce added
		wp_nonce_field('resource_booking_add_resource', 'resource_booking_nonce');

		echo '<table class="form-table">';
		echo '<tr>';
			echo '<th><label for="resource_name">Resource Name</label></th>';
			echo '<td><input type="text" name="resource_name" id="resource_name" class="regular-text"></td>';
		echo '</tr>';
		echo '<tr>';
			echo '<th><label for="resource_description">Description</label></th>';
			echo '<td><textarea name="resource_description" id="resource_description" rows="5" class="large-text"></textarea></td>';
		echo '</tr>';
		echo '<tr>';
			echo '<th><label for="resource_capacity">Capacity / Quantity</label></th>';
			echo '<td><input type="number" name="resource_capacity" id="resource_capacity" min="1" value="1" class="small-text"></td>';
		echo '</tr>';
		echo '<tr>';
			echo '<th><label for="resource_image">Resource Image</label></th>';
			echo '<td>';

			echo '<input type="hidden" name="resource_image_ids" id="resource_image_ids" value="">';

			echo '<button type="button" class="button" id="resource_image_button">Select Images</button>';

			echo '<div id="resource_image_preview"></div>';

			echo '</td>';
		echo '</tr>';
		echo '</table>';
		echo '<p class="submit">';

		//submit identifier
		echo '<input type="submit" name="resource_booking_save" class="button button-primary" value="Save Resource">';
		echo '</p>';
		echo '</form>';
		echo '</div>';
	}

	public function edit_resource_page()
	{
		if (! isset($_GET['id'])) {
			wp_die('Resource ID is required.');
		}

		$resource_id = absint($_GET['id']);

		if ($resource_id < 1) {
			wp_die('Invalid resource ID.');
		}

		global $wpdb;

		$resources_table = $wpdb->prefix . 'rb_resources';

		// Get existing resource.

		$resource = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$resources_table} WHERE id = %d",
				$resource_id
			)
		);

		if (! $resource) {
			wp_die('Resource not found.');
		}

		// Handle resource update.
		if (isset($_POST['resource_booking_update'])) {

			check_admin_referer(
				'resource_booking_edit_resource',
				'resource_booking_edit_nonce'
			);

			if (! current_user_can('manage_options')) {
				wp_die('You do not have permission to manage resources.');
			}

			// Sanitize form values.
			$resource_name        = isset($_POST['resource_name']) ? sanitize_text_field(wp_unslash($_POST['resource_name'])) : '';
			$resource_description = isset($_POST['resource_description']) ? sanitize_textarea_field(wp_unslash($_POST['resource_description'])) : '';
			$resource_image_ids = isset( $_POST['resource_image_ids'] )
				? array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['resource_image_ids'] ) ) ) )
				: array();

			$resource_image_ids = array_filter( $resource_image_ids );
			$resource_capacity    = isset($_POST['resource_capacity']) ? absint($_POST['resource_capacity']) : 0;
			
			// Validate form values.
			if (empty($resource_name)) {
				wp_die('Resource name is required.');
			}

			if ($resource_capacity < 1) {
				wp_die('Resource capacity must be at least 1.');
			}

			// Update resource.
			$result = $wpdb->update(
				$resources_table,
				array(
					'name'        => $resource_name,
					'description' => $resource_description,
					'image_id'    => maybe_serialize( $resource_image_ids),
					'capacity'    => $resource_capacity,
					'updated_at'  => current_time('mysql'),
				),
				array(
					'id' => $resource_id,
				),
				array(
					'%s',
					'%s',
					'%s',
					'%d',
					'%s',
				),
				array(
					'%d',
				)
			);

			if (false === $result) {
				wp_die('Failed to update the resource.');
			}

			echo '<div class="notice notice-success is-dismissible">';
			echo '<p>Resource updated successfully.</p>';
			echo '</div>';

			// Get updated resource.
			$resource = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$resources_table} WHERE id = %d",
					$resource_id
				)
			);
		}

		echo '<div class="wrap">';

		echo '<h1>Edit Resource</h1>';

		echo '<form method="post">';

		wp_nonce_field(
			'resource_booking_edit_resource',
			'resource_booking_edit_nonce'
		);

		echo '<table class="form-table">';

		echo '<tr>';
		echo '<th><label for="resource_name">Resource Name</label></th>';
		echo '<td><input type="text" name="resource_name" id="resource_name" class="regular-text" value="' . esc_attr($resource->name) . '"></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th><label for="resource_description">Description</label></th>';
		echo '<td><textarea name="resource_description" id="resource_description" rows="5" class="large-text">' . esc_textarea($resource->description) . '</textarea></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th><label for="resource_capacity">Capacity / Quantity</label></th>';
		echo '<td><input type="number" name="resource_capacity" id="resource_capacity" min="1" value="' . esc_attr($resource->capacity) . '" class="small-text"></td>';
		echo '</tr>';

		//edit images library
		echo '<tr>';
			echo '<th><label for="resource_image">Resource Image</label></th>';
		echo '<td>';

		$image_ids = maybe_unserialize( $resource->image_id );

		if ( ! is_array( $image_ids ) ) {
			$image_ids = array();
		}

		echo '<input type="hidden" name="resource_image_ids" id="resource_image_ids" value="' . esc_attr( implode( ',', $image_ids ) ) . '">';

		$image_ids = maybe_unserialize( $resource->image_id );

		if ( ! is_array( $image_ids ) ) {
			$image_ids = array();
		}

		echo '<div id="resource_image_preview">';

		foreach ( $image_ids as $image_id ) {

			echo wp_get_attachment_image(
				$image_id,
				'medium',
				false,
				array(
					'style' => 'max-width: 200px; height: auto; margin: 5px;'
				)
			);

		}

		echo '</div>';

		echo '<p>';
		echo '<button type="button" class="button" id="resource_image_button">Select Images</button>';
		echo '<button type="button" class="button" id="resource_image_remove_button">Remove Images</button>';
		echo '</p>';

		echo '</td>';
		echo '</tr>';


		echo '</table>';

		echo '<p class="submit">';

		echo '<input type="submit" name="resource_booking_update" class="button button-primary" value="Update Resource">';

		echo '</p>';

		echo '</form>';

		echo '</div>';
	}


	//for the delete

	public function delete_resource()
	{
		if (! isset($_GET['id'])) {
			wp_die('Resource ID is required.');
		}

		$resource_id = absint($_GET['id']);

		if ($resource_id < 1) {
			wp_die('Invalid resource ID.');
		}

		if (! current_user_can('manage_options')) {
			wp_die('You do not have permission to delete resources.');
		}

		check_admin_referer(
			'resource_booking_delete_resource_' . $resource_id
		);

		global $wpdb;

		$resources_table = $wpdb->prefix . 'rb_resources';

		$bookings_table = $wpdb->prefix . 'rb_bookings';

		// Check if resource exists.
		$resource = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$resources_table} WHERE id = %d",
				$resource_id
			)
		);

		if (! $resource) {
			wp_die('Resource not found.');
		}

		// Check if the resource has any bookings.
		$booking_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$bookings_table} WHERE resource_id = %d",
				$resource_id
			)
		);

		if ($booking_count > 0) {
			wp_die('This resource cannot be deleted because it has bookings.');
		}

		// Delete resource.
		$result = $wpdb-> delete(
			$resources_table,
			array(
				'id' => $resource_id,
			),
			array(
				'%d',
			)
		);

		if (false === $result) {
			wp_die('Failed to delete the resource.');
		}

		echo '<div class="notice notice-success is-dismissible">';
		echo '<p>Resource deleted successfully.</p>';
		echo '</div>';
	}




	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles()
	{

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Resource_Booking_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Resource_Booking_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/resource-booking-admin.css', array(), $this->version, 'all');
	}



	public function enqueue_media()
	{
		wp_enqueue_media();
	}



	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts()
	{

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Resource_Booking_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Resource_Booking_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/resource-booking-admin.js', array('jquery'), $this->version, false);
		wp_localize_script(
			$this->plugin_name,
			'resourceBookingAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'resource_booking_admin' ),
			)
		);
	}

	public function booking_admin_notices() {

		if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) {
			echo '<div class="notice notice-success is-dismissible">';
			echo '<p>Booking updated successfully.</p>';
			echo '</div>';
		}

		if ( isset( $_GET['confirmed'] ) && '1' === $_GET['confirmed'] ) {
			echo '<div class="notice notice-success is-dismissible">';
			echo '<p>Booking confirmed successfully.</p>';
			echo '</div>';
		}

		if ( isset( $_GET['rejected'] ) && '1' === $_GET['rejected'] ) {
			echo '<div class="notice notice-success is-dismissible">';
			echo '<p>Booking rejected successfully.</p>';
			echo '</div>';
		}

	}
	
}
