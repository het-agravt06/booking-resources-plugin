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
				$this, 'resource_booking_page'
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
	}
	/**
	 * Display the Resource Booking page. 
	 * 
	 * @since 1.0.0 
	 */ 
	
	public function resource_booking_page()
	{
		if ( isset( $_GET['action'] ) && 'add' === $_GET['action'] ) {
			$this->add_resource_page();
			return;
		}
		echo '<div class="wrap">';
		echo '<h1>Resource Management</h1>';

		echo '<p>Manage your bookable resources here.</p>';

		echo '<a href="' . admin_url( 'admin.php?page=resource-booking&action=add' ) . '" class="page-title-action">Add New Resource</a>';

		echo '</div>';
	}

	public function add_resource_page()
	{
		echo '<div class="wrap">';
		echo '<h1>Add New Resource</h1>';
		echo '<form method="post">';

		wp_nonce_field( 'resource_booking_add_resource', 'resource_booking_nonce' );

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
		echo '</table>';
		echo '<p class="submit">';
		echo '<input type="submit" name="resource_booking_save" class="button button-primary" value="Save Resource">';
		echo '</p>';
		echo '</form>';
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
