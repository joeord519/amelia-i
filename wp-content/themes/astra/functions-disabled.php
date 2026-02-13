<?php

// ✅ Start Debug Logging (Force Log Update)
error_log("🟡 DEBUG: functions.php was loaded successfully at " . date("Y-m-d H:i:s"));

// ✅ Ensure WordPress Debugging is Active
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
@ini_set('log_errors', 1);
@ini_set('display_errors', 0);

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Define Constants
 */
define('ASTRA_THEME_VERSION', '4.8.12');
define('ASTRA_THEME_SETTINGS', 'astra-settings');
define('ASTRA_THEME_DIR', trailingslashit(get_template_directory()));
define('ASTRA_THEME_URI', trailingslashit(esc_url(get_template_directory_uri())));
define('ASTRA_THEME_ORG_VERSION', file_exists(ASTRA_THEME_DIR . 'inc/w-org-version.php'));

/**
 * Minimum Version requirement of the Astra Pro addon.
 */
define('ASTRA_EXT_MIN_VER', '4.8.9');

/**
 * Load in-house compatibility.
 */
if (ASTRA_THEME_ORG_VERSION) {
    require_once ASTRA_THEME_DIR . 'inc/w-org-version.php';
}

// ✅ Debug Require Files
function debug_require_file($file) {
    if (file_exists($file)) {
        error_log("🟡 DEBUG: Loading - " . $file);
        require_once $file;
    } else {
        error_log("🔴 ERROR: Missing file - " . $file);
    }
}

/**
 * Load Astra Core Files
 */
debug_require_file(ASTRA_THEME_DIR . 'inc/core/class-astra-theme-options.php');
debug_require_file(ASTRA_THEME_DIR . 'inc/core/class-theme-strings.php');
debug_require_file(ASTRA_THEME_DIR . 'inc/core/common-functions.php');
debug_require_file(ASTRA_THEME_DIR . 'inc/core/class-astra-icons.php');

define('ASTRA_WEBSITE_BASE_URL', 'https://wpastra.com');

/**
 * Update theme
 */
debug_require_file(ASTRA_THEME_DIR . 'inc/theme-update/astra-update-functions.php');
debug_require_file(ASTRA_THEME_DIR . 'inc/theme-update/class-astra-theme-background-updater.php');

/**
 * Load Fonts
 */
debug_require_file(ASTRA_THEME_DIR . 'inc/customizer/class-astra-font-families.php');
if (is_admin()) {
    debug_require_file(ASTRA_THEME_DIR . 'inc/customizer/class-astra-fonts-data.php');
}

debug_require_file(ASTRA_THEME_DIR . 'inc/lib/webfont/class-astra-webfont-loader.php');
debug_require_file(ASTRA_THEME_DIR . 'inc/lib/docs/class-astra-docs-loader.php');
debug_require_file(ASTRA_THEME_DIR . 'inc/customizer/class-astra-fonts.php');

/**
 * Load FullCalendar Scripts
 */
function enqueue_fullcalendar_scripts() {
    error_log("🟡 DEBUG: Enqueueing FullCalendar Scripts.");
    wp_enqueue_script(
        'fullcalendar-global',
        'https://amelia-i.com/wp-content/plugins/fullcalendar_test/custom-calendar/dist/index.global.min.js',
        array('jquery'),
        null,
        true
    );

    wp_enqueue_style(
        'fullcalendar-css',
        'https://amelia-i.com/wp-content/plugins/fullcalendar_test/custom-calendar/dist/index.global.css',
        array(),
        null
    );
}
add_action('wp_enqueue_scripts', 'enqueue_fullcalendar_scripts');

/**
 * Custom Aircraft Rewrite Rule
 */
function custom_aircraft_rewrite_rule() {
    add_rewrite_rule('^([A-Za-z0-9]+)-aircraft-documents/?$', 'index.php?pagename=$matches[1]-aircraft-documents&tail_number=$matches[1]', 'top');
}
add_action('init', 'custom_aircraft_rewrite_rule');

function custom_aircraft_query_vars($vars) {
    $vars[] = 'tail_number';
    return $vars;
}
add_filter('query_vars', 'custom_aircraft_query_vars');

/**
 * Google Calendar Event Creation Function
 */
function createGoogleCalendarEvent($cfi_email, $student_name, $student_phone, $flight_date, $appointment_time, $aircraft) {
    $google_calendar_api_key = "YOUR_GOOGLE_API_KEY";
    $calendar_id = urlencode($cfi_email);
    $start_datetime = $flight_date . "T" . $appointment_time . ":00";
    $end_datetime = date("Y-m-d\TH:i:s", strtotime("+2 hours", strtotime($start_datetime)));

    $event = [
        "summary" => "Flight with $student_name ($aircraft)",
        "description" => "Student: $student_name\nPhone: $student_phone\nAircraft: $aircraft",
        "start" => ["dateTime" => $start_datetime, "timeZone" => "America/Chicago"],
        "end" => ["dateTime" => $end_datetime, "timeZone" => "America/Chicago"],
        "attendees" => [["email" => $cfi_email]],
        "reminders" => ["useDefault" => false, "overrides" => [["method" => "email", "minutes" => 60]]]
    ];

    $url = "https://www.googleapis.com/calendar/v3/calendars/$calendar_id/events?key=$google_calendar_api_key";

    $response = wp_remote_post($url, [
        'body' => json_encode($event),
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 30
    ]);

    $response_body = json_decode(wp_remote_retrieve_body($response), true);

    return $response_body['id'] ?? null;
}

/**
 * Allow CORS (If needed)
 */
function allow_custom_cors() {
    if (!headers_sent()) {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type");
    }
}
add_action('init', 'allow_custom_cors');

/**
 * Ensure Proper Ending of PHP
 */
?>
