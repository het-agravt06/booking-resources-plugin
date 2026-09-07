<?php

/**
 * Handles database operations for the Resource Booking plugin.
 *
 * @package Resource_Booking
 */

class Resource_Booking_Database {

	/**
	 * Create plugin database tables.
	 *
	 * @return void
	 */
	public static function create_tables() {

		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$resources_table = $wpdb->prefix . 'rb_resources';
		$bookings_table  = $wpdb->prefix . 'rb_bookings';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$resources_sql = "CREATE TABLE {$resources_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text NOT NULL,
			image_id bigint(20) unsigned DEFAULT NULL,
			capacity int(11) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id)
		) {$charset_collate};";

		$bookings_sql = "CREATE TABLE {$bookings_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			resource_id bigint(20) unsigned NOT NULL,
			customer_name varchar(255) NOT NULL,
			customer_email varchar(255) NOT NULL,
			start_datetime datetime NOT NULL,
			end_datetime datetime NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			expires_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY resource_id (resource_id),
			KEY status (status),
			KEY start_datetime (start_datetime),
			KEY end_datetime (end_datetime)
		) {$charset_collate};";

		dbDelta( $resources_sql );
		dbDelta( $bookings_sql );
	}
}