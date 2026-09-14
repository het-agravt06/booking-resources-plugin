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
		
	}

	//form action button handling 

	public function handle_booking_actions() {
		// error_log( 'HANDLE BOOKING ACTIONS CALLED' );
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['resource_booking_action'] ) ) {
			return;
		}

		if ( 'confirm' !== $_POST['resource_booking_action'] ) {
			return;
		}

		if (
			! isset( $_POST['resource_booking_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['resource_booking_nonce'] ) ),
				'resource_booking_confirm'
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

	if ( 'pending' !== $booking->status ) {
		return;
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


		$bookings = $wpdb->get_results(
			"SELECT bookings.*, resources.name AS resource_name
			FROM {$bookings_table} AS bookings
			LEFT JOIN {$resources_table} AS resources
				ON bookings.resource_id = resources.id
			{$where_sql}
			ORDER BY bookings.start_datetime DESC"
		);

		echo '<div class="wrap">';
		echo '<h1>Bookings</h1>';


		echo '<form method="get">';

		echo '<input type="hidden" name="page" value="resource-booking-bookings">';

		//Add Status dropdown
		echo '<select name="status">';

		echo '<option value="">All Statuses</option>';
		echo '<option value="pending"' . selected( $status, 'pending', false ) . '>Pending</option>';
		echo '<option value="confirmed"' . selected( $status, 'confirmed', false ) . '>Confirmed</option>';
		echo '<option value="cancelled"' . selected( $status, 'cancelled', false ) . '>Cancelled</option>';
		echo '<option value="expired"' . selected( $status, 'expired', false ) . '>Expired</option>';

		echo '</select>';

		// 	Add Resource dropdown
		echo '<select name="resource_id">';

		echo '<option value="0">All Resources</option>';
			foreach ( $resources as $resource ) {
				echo '<option value="' . esc_attr( $resource->id ) . '"'
					. selected( $resource_id, $resource->id, false ) . '>'
					. esc_html( $resource->name )
					. '</option>';
			}
		echo '</select>';


		submit_button( 'Filter', 'secondary', 'filter', false );

		echo '</form>';

		if ( empty( $bookings ) ) {
			echo '<p>No bookings found.</p>';
			return;
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
			echo '<td>' . esc_html( ucfirst( $booking->status ) ) . '</td>';
			echo '<td>';

				if ( 'pending' === $booking->status ) {
					echo '<form method="post" style="display:inline;">';
					wp_nonce_field( 'resource_booking_confirm', 'resource_booking_nonce' );
					echo '<input type="hidden" name="booking_id" value="' . esc_attr( $booking->id ) . '">';
					echo '<input type="hidden" name="resource_booking_action" value="confirm">';
					echo '<button type="submit" class="button button-primary">Confirm</button>';
					echo '</form>';
				}

			echo '</td>';
			

			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';
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
			$resource_image_id = isset( $_POST['resource_image_id'] ) ? absint( $_POST['resource_image_id'] ) : 0;
			
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
					'image_id' => $resource_image_id,
					'capacity'    => $resource_capacity,
					'created_at'  => current_time('mysql'),
					'updated_at'  => current_time('mysql'),
				),
				array(
					'%s',
					'%s',
					'%d',
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

			echo '<input type="hidden" name="resource_image_id" id="resource_image_id" value="">';

			echo '<button type="button" class="button" id="resource_image_button">Select Image</button>';

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
			$resource_image_id    = isset( $_POST['resource_image_id'] ) ? absint( $_POST['resource_image_id'] ) : 0;
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
					'image_id'    => $resource_image_id,
					'capacity'    => $resource_capacity,
					'updated_at'  => current_time('mysql'),
				),
				array(
					'id' => $resource_id,
				),
				array(
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

		echo '<input type="hidden" name="resource_image_id" id="resource_image_id" value="' . esc_attr( $resource->image_id ) . '">';

		if ( ! empty( $resource->image_id ) ) {

			echo '<div id="resource_image_preview">';
			echo wp_get_attachment_image(
				$resource->image_id,
				'medium',
				false,
				array(
					'style' => 'max-width: 200px; height: auto;'
				)
			);
			echo '</div>';

		} else {

			echo '<div id="resource_image_preview"></div>';

		}

		echo '<p>';
		echo '<button type="button" class="button" id="resource_image_button">Select Image</button>';
		echo '<button type="button" class="button" id="resource_image_remove_button">Remove Image</button>';
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
		$result = $wpdb->delete(
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
	}
}
