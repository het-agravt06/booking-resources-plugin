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
class Resource_Booking_Activator
{

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate()
	{

		require_once plugin_dir_path(__FILE__) . 'class-resource-booking-database.php';

		Resource_Booking_Database::create_tables();
		$default_settings = array(
			'business_hours' => array(
				'monday' => array('09:00', '18:00'), 
				'tuesday' => array('09:00', '18:00'), 
				'wednesday' => array('09:00', '18:00'), 
				'thursday' => array('09:00', '18:00'), 
				'friday' => array('09:00', '18:00'), 
				'saturday' => array('09:00', '18:00'), 
				'sunday' => null,
			), 
			'blackout_dates' => array(
				'2026-09-21',
				'2026-09-22',
			),
			'hold_duration' => 30,
		);
		update_option( 'resource_booking_settings', $default_settings );  
	}
}
