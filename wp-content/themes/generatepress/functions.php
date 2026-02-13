<?php
/**
 * GeneratePress.
 *
 * Please do not make any edits to this file. All edits should be done in a child theme.
 *
 * @package GeneratePress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Set our theme version.
define( 'GENERATE_VERSION', '3.5.1' );

if ( ! function_exists( 'generate_setup' ) ) {
	add_action( 'after_setup_theme', 'generate_setup' );
	/**
	 * Sets up theme defaults and registers support for various WordPress features.
	 *
	 * @since 0.1
	 */
	function generate_setup() {
		// Make theme available for translation.
		load_theme_textdomain( 'generatepress' );

		// Add theme support for various features.
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'post-formats', array( 'aside', 'image', 'video', 'quote', 'link', 'status' ) );
		add_theme_support( 'woocommerce' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'script', 'style' ) );
		add_theme_support( 'customize-selective-refresh-widgets' );
		add_theme_support( 'align-wide' );
		add_theme_support( 'responsive-embeds' );

		$color_palette = generate_get_editor_color_palette();

		if ( ! empty( $color_palette ) ) {
			add_theme_support( 'editor-color-palette', $color_palette );
		}

		add_theme_support(
			'custom-logo',
			array(
				'height' => 70,
				'width' => 350,
				'flex-height' => true,
				'flex-width' => true,
			)
		);

		// Register primary menu.
		register_nav_menus(
			array(
				'primary' => __( 'Primary Menu', 'generatepress' ),
			)
		);

		// Set the content width to something large
		global $content_width;
		if ( ! isset( $content_width ) ) {
			$content_width = 1200; /* pixels */
		}

		// Add editor styles to the block editor.
		add_theme_support( 'editor-styles' );

		$editor_styles = apply_filters(
			'generate_editor_styles',
			array(
				'assets/css/admin/block-editor.css',
			)
		);

		add_editor_style( $editor_styles );
	}
}

// Load necessary theme files
$theme_dir = get_template_directory();
require $theme_dir . '/inc/theme-functions.php';
require $theme_dir . '/inc/defaults.php';
require $theme_dir . '/inc/class-css.php';
require $theme_dir . '/inc/css-output.php';
require $theme_dir . '/inc/general.php';
require $theme_dir . '/inc/customizer.php';
require $theme_dir . '/inc/markup.php';
require $theme_dir . '/inc/typography.php';
require $theme_dir . '/inc/plugin-compat.php';
require $theme_dir . '/inc/block-editor.php';
require $theme_dir . '/inc/class-typography.php';
require $theme_dir . '/inc/class-typography-migration.php';
require $theme_dir . '/inc/class-html-attributes.php';
require $theme_dir . '/inc/class-theme-update.php';
require $theme_dir . '/inc/class-rest.php';
require $theme_dir . '/inc/deprecated.php';

if ( is_admin() ) {
	require $theme_dir . '/inc/meta-box.php';
	require $theme_dir . '/inc/class-dashboard.php';
}

// Flight Booking AJAX Function
add_action('wp_ajax_fetch_timeslots', 'fetch_timeslots_callback');
add_action('wp_ajax_nopriv_fetch_timeslots', 'fetch_timeslots_callback');

function fetch_timeslots_callback() {
    error_log("🚀 AJAX Request received for fetch_timeslots"); // ✅ Debug log

    require_once ABSPATH . 'wp-load.php';
    require_once '/home/customer/www/amelia-i.com/public_html/wp-content/plugins/flight-booking/db_connect.php';

    header('Content-Type: application/json');

    $date = $_POST['date'] ?? '';
    $aircraft = $_POST['aircraft'] ?? '';
    $cfi = $_POST['cfi'] ?? ''; // ✅ Ensure cfi is an empty string, not null

    if (empty($date) || empty($aircraft)) {
        error_log("❌ Missing parameters: date=$date, aircraft=$aircraft, cfi=$cfi");
        echo json_encode(["<option value=''>No available slots</option>"]);
        wp_die();
    }

    global $conn;

    // ✅ Ensure database connection is established
    if (!$conn) {
        error_log("❌ Database connection failed in fetch_timeslots_callback.");
        die(json_encode(["<option value=''>Database connection error</option>"]));
    }

    // ✅ Adjust SQL query for solo flights (if cfi is empty, ignore CFI filtering)
    if ($cfi === '' || $cfi === null) {
        $query = "SELECT start_time, end_time FROM wp_flight_schedule 
                  WHERE aircraft_id = ? 
                  AND DATE(start_time) = ? 
                  AND status = 'Scheduled'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $aircraft, $date);
    } else {
        $query = "SELECT start_time, end_time FROM wp_flight_schedule 
                  WHERE (aircraft_id = ? OR cfi_id = ?) 
                  AND DATE(start_time) = ? 
                  AND status = 'Scheduled'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $aircraft, $cfi, $date);
    }

    if (!$stmt) {
        error_log("❌ SQL Error: " . $conn->error);
        echo json_encode(["<option value=''>Error fetching time slots</option>"]);
        wp_die();
    }

    $stmt->execute();
    $result = $stmt->get_result();

    // ✅ Generate all available time slots (6 AM - 10 PM)
    $all_slots = [];
    $start_time = strtotime('06:00');
    $end_time = strtotime('22:00');

    while ($start_time < $end_time) {
        $formatted_time = date("H:i", $start_time);
        $all_slots[$formatted_time] = true;
        $start_time = strtotime('+30 minutes', $start_time);
    }

    // ✅ Remove booked slots
    while ($row = $result->fetch_assoc()) {
        $booked_start = date("H:i", strtotime($row['start_time']));
        $booked_end = date("H:i", strtotime($row['end_time']));

        foreach ($all_slots as $time => $available) {
            if ($time >= $booked_start && $time < $booked_end) {
                unset($all_slots[$time]);
            }
        }
    }

    // ✅ Output available slots
    $available_slots = [];
    foreach ($all_slots as $time => $available) {
        $available_slots[] = "<option value='$time'>" . date("h:i A", strtotime($time)) . "</option>";
    }

    if (empty($available_slots)) {
        $available_slots[] = "<option value=''>No available slots</option>";
    }

    echo json_encode(array_values($available_slots), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
wp_die();

}





