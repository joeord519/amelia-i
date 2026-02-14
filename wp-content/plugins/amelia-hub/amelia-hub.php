<?php
/**
 * Plugin Name: Amelia Hub
 * Description: Central multi-role hub for students, instructors, and admins with fleet and flight-history tools.
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Amelia_Hub_Plugin {
    public function __construct() {
        add_shortcode('amelia_hub', array($this, 'render_hub'));
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));

        add_action('wp_ajax_amelia_get_fleet', array($this, 'get_fleet'));
        add_action('wp_ajax_nopriv_amelia_get_fleet', array($this, 'get_fleet'));

        add_action('wp_ajax_amelia_get_student_history', array($this, 'get_student_history'));
        add_action('wp_ajax_nopriv_amelia_get_student_history', array($this, 'get_student_history'));

        add_action('wp_ajax_amelia_update_aircraft_status', array($this, 'update_aircraft_status'));
    }

    public function register_assets() {
        wp_register_style(
            'amelia-hub-style',
            plugins_url('assets/hub.css', __FILE__),
            array(),
            '0.1.0'
        );

        wp_register_script(
            'amelia-hub-script',
            plugins_url('assets/hub.js', __FILE__),
            array(),
            '0.1.0',
            true
        );
    }

    public function render_hub() {
        wp_enqueue_style('amelia-hub-style');
        wp_enqueue_script('amelia-hub-script');

        wp_localize_script('amelia-hub-script', 'ameliaHub', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('amelia_hub_nonce'),
            'isAdmin' => current_user_can('manage_options'),
        ));

        ob_start();
        ?>
        <div class="amelia-hub">
            <h2>Amelia Operations Hub</h2>
            <p class="amelia-hub-intro">One place for students, instructors, and admins to access scheduling, fleet, and training information.</p>

            <div class="amelia-role-grid">
                <button class="amelia-role-card is-active" data-role-target="student">Student</button>
                <button class="amelia-role-card" data-role-target="cfi">Instructor / CFI</button>
                <button class="amelia-role-card" data-role-target="admin">Admin / Staff</button>
            </div>

            <section class="amelia-panel is-active" data-role-panel="student">
                <h3>Student Flight History</h3>
                <p>Use your account email or phone number to pull your completed and scheduled flight log.</p>
                <form id="amelia-student-history-form">
                    <label for="amelia-student-lookup">Email or phone</label>
                    <input id="amelia-student-lookup" name="lookup" type="text" required placeholder="name@email.com or (555) 123-4567" />
                    <button type="submit">View History</button>
                </form>
                <div id="amelia-student-history-results"></div>
            </section>

            <section class="amelia-panel" data-role-panel="cfi">
                <h3>Instructor Quick Links</h3>
                <ul>
                    <li><a href="/wp-content/plugins/checkout/flight-schedule.php">Flight Schedule</a></li>
                    <li><a href="/wp-content/plugins/checkout/manage-cfis.php">Instructor Management</a></li>
                    <li><a href="/wp-content/plugins/calendar.v2/manage-aircraft.php">Aircraft Availability</a></li>
                </ul>
            </section>

            <section class="amelia-panel" data-role-panel="admin">
                <h3>Fleet Management Snapshot</h3>
                <p>Live fleet status and at-a-glance details from the aircraft database.</p>
                <div id="amelia-fleet-results">Loading fleet…</div>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }

    public function get_fleet() {
        check_ajax_referer('amelia_hub_nonce', 'nonce');

        global $wpdb;
        $table = $wpdb->prefix . 'aircraft';
        $rows = $wpdb->get_results("SELECT tail_number, model, aircraft_type, home_airport, status FROM {$table} ORDER BY tail_number ASC", ARRAY_A);

        wp_send_json_success(array('fleet' => $rows));
    }

    public function get_student_history() {
        check_ajax_referer('amelia_hub_nonce', 'nonce');

        $lookup_raw = isset($_POST['lookup']) ? sanitize_text_field(wp_unslash($_POST['lookup'])) : '';
        if ($lookup_raw === '') {
            wp_send_json_error(array('message' => 'Email or phone is required.'));
        }

        global $wpdb;
        $students_table = $wpdb->prefix . 'students';
        $schedule_table = $wpdb->prefix . 'flight_schedule';
        $users_table = $wpdb->prefix . 'users';

        $normalized_phone = preg_replace('/\D+/', '', $lookup_raw);

        $student = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT student_id, first_name, last_name, email, phone FROM {$students_table} WHERE email = %s OR phone = %s OR REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', ''), ' ', '') = %s LIMIT 1",
                $lookup_raw,
                $lookup_raw,
                $normalized_phone
            ),
            ARRAY_A
        );

        if (!$student) {
            wp_send_json_error(array('message' => 'No student found for that email/phone.'));
        }

        $flights = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT fs.id, fs.start_time, fs.end_time, fs.flight_type, fs.tail_number, fs.status,
                        CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                        u.user_email AS cfi_email
                 FROM {$schedule_table} fs
                 LEFT JOIN {$students_table} s ON fs.student_id = s.student_id
                 LEFT JOIN {$users_table} u ON fs.cfi_id = u.ID
                 WHERE fs.student_id = %d
                 ORDER BY fs.start_time DESC
                 LIMIT 100",
                $student['student_id']
            ),
            ARRAY_A
        );

        wp_send_json_success(array(
            'student' => $student,
            'flights' => $flights,
        ));
    }

    public function update_aircraft_status() {
        check_ajax_referer('amelia_hub_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $tail_number = isset($_POST['tail_number']) ? sanitize_text_field(wp_unslash($_POST['tail_number'])) : '';
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';

        if ($tail_number === '' || $status === '') {
            wp_send_json_error(array('message' => 'Tail number and status are required.'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aircraft';
        $updated = $wpdb->update(
            $table,
            array('status' => $status),
            array('tail_number' => $tail_number),
            array('%s'),
            array('%s')
        );

        if ($updated === false) {
            wp_send_json_error(array('message' => 'Unable to update aircraft status.'));
        }

        wp_send_json_success(array('message' => 'Aircraft status updated.'));
    }
}

new Amelia_Hub_Plugin();
