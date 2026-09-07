<?php

/**
 * Fired during plugin activation
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Resource_Booking
 * @subpackage Resource_Booking/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Resource_Booking
 * @subpackage Resource_Booking/includes
 * @author     Het Agravat <agravathet51@gmail.com>
 */
class Resource_Booking_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		
		require_once plugin_dir_path( __FILE__ ) . 'class-resource-booking-database.php';

		Resource_Booking_Database::create_tables();

	}

}
