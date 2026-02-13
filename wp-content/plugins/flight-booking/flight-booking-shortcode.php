<?php
/**
 * Plugin Name: Flight Booking Shortcode
 * Description: Adds a shortcode to display the flight booking form.
 * Version: 1.0
 * Author: Your Name
 */

function flight_booking_shortcode() {
    ob_start();
    include plugin_dir_path(__FILE__) . 'flight_booking_form.php';
    return ob_get_clean();
}
add_shortcode('flight_booking_form', 'flight_booking_shortcode');
