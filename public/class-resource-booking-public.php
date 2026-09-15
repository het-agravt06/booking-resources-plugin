<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Resource_Booking
 * @subpackage Resource_Booking/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Resource_Booking
 * @subpackage Resource_Booking/public
 * @author     Het Agravat <agravathet51@gmail.com>
 */
class Resource_Booking_Public
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
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($plugin_name, $version)
	{

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		add_action('init', function () {
			add_shortcode('test_booking_form', array($this, 'display_booking_form'));
		});
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
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

		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/resource-booking-public.css', array(), $this->version, 'all');
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
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

		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/resource-booking-public.js', array('jquery'), $this->version, false);
		wp_localize_script(
			$this->plugin_name,
			'resourceBooking',
			array(
				'apiUrl' => esc_url_raw(rest_url('resource-booking/v1/')),
			)
		);
	}


	/**
	 * Display the booking form.
	 *
	 * @since 1.0.0
	 */
	public function display_booking_form()
	{
		ob_start();
		?>
		<form id="resource-booking-form">

			<p>
				<label for="resource_id">Resource</label>
				<select id="resource_id" name="resource_id" required>
					<option value="">Select Resource</option>
				</select>
			</p>

			<p>
				<label for="customer_name">Name</label>
				<input type="text" id="customer_name" name="customer_name" required>
			</p>

			<p>
				<label for="customer_email">Email</label>
				<input type="email" id="customer_email" name="customer_email" required>
			</p>

			<p>
				<label for="start_datetime">Start</label>
				<input type="datetime-local" id="start_datetime" name="start_datetime" required>
			</p>

			<p>
				<label for="end_datetime">End</label>
				<input type="datetime-local" id="end_datetime" name="end_datetime" required>
			</p>

			<button type="submit">Submit Booking</button>

		</form>

		<div id="resource-booking-message"></div>

		<?php
		return ob_get_clean();
	}

	/**
	 * Register public shortcodes.
	 *
	 * @since 1.0.0
	 */
	// public function register_shortcodes() {
	// 		add_shortcode(
	// 		'resource_booking_form',
	// 		array( $this, 'display_booking_form' )
	// 	);

	// }
}
