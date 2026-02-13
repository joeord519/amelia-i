<?php
/**
 * Plugin Name: Piston Spinner
 * Description: Gamified “Spin-to-Claim” rewards with 24-hour purchase window.
 * Version: 0.1.0
 * Author: Piston Aviation
 */

if (!defined('ABSPATH')) { exit; }

define('PISTON_SPINNER_DIR', plugin_dir_path(__FILE__));
define('PISTON_SPINNER_URL', plugin_dir_url(__FILE__));

require_once PISTON_SPINNER_DIR . 'config.php';

/**
 * Activation: create tables if not exist
 */
register_activation_hook(__FILE__, function () {
  global $wpdb;
  $charset = $wpdb->get_charset_collate();

  $sql_rewards = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spin_rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    profile_key VARCHAR(50) NOT NULL,
    prize_code VARCHAR(50) NOT NULL,
    prize_label VARCHAR(255) NOT NULL,
    discount_percent DECIMAL(5,2) DEFAULT 0,
    bonus_ac_hours DECIMAL(5,2) DEFAULT 0,
    bonus_cfi_hours DECIMAL(5,2) DEFAULT 0,
    minimum_purchase_hours DECIMAL(5,2) DEFAULT 0,
    token CHAR(36) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    claimed_at DATETIME DEFAULT NULL,
    status ENUM('provisional','claimed','expired','void') DEFAULT 'provisional',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX(student_id),
    INDEX(token),
    INDEX(expires_at),
    INDEX(status)
  ) $charset;";

  $sql_profiles = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spin_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    active TINYINT(1) DEFAULT 1,
    slices_json JSON NOT NULL,
    monthly_cap INT DEFAULT 0, -- 0 = unlimited
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) $charset;";

  $sql_elig = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spin_eligibility (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    context VARCHAR(40) NOT NULL, -- booking_success | checkout_success | milestone | promo
    eligible TINYINT(1) NOT NULL,
    reason VARCHAR(80) DEFAULT '',
    cooldown_until DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX(student_id),
    INDEX(context),
    INDEX(eligible)
  ) $charset;";

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  dbDelta($sql_rewards);
  dbDelta($sql_profiles);
  dbDelta($sql_elig);

  // Seed a default wheel profile if missing
  $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}spin_profiles WHERE `key`=%s", 'standard_nudge'));
  if (!$exists) {
    $slices = [
  { "code": "FREE_1_0_AIR", "label": "1 FREE Aircraft Hour",      "w": 1,  "disc": 0,  "ac": 1.0, "cfi": 0,   "min": 0 },
  { "code": "FREE_1_0_CFI", "label": "1 FREE Instructor Hour",     "w": 1,  "disc": 0,  "ac": 0,   "cfi": 1.0, "min": 0 },
  { "code": "FREE_0_5_AIR", "label": "0.5 FREE Aircraft Hour",     "w": 1,  "disc": 0,  "ac": 0.5, "cfi": 0,   "min": 0 },
  { "code": "FREE_0_5_CFI", "label": "0.5 FREE Instructor Hour",   "w": 2,  "disc": 0,  "ac": 0,   "cfi": 0.5, "min": 0 },
  { "code": "MERCH_TEE",    "label": "FREE Merch Tee",             "w": 5,  "disc": 0,  "ac": 0,   "cfi": 0,   "min": 0 },
  { "code": "NO_WIN",       "label": "Almost! (No prize)",         "w": 5,  "disc": 0,  "ac": 0,   "cfi": 0,   "min": 0 },
  { "code": "DISC_5",       "label": "5% Off Hour Purchase",       "w": 45, "disc": 5,  "ac": 0,   "cfi": 0,   "min": 5 },
  { "code": "DISC_10",      "label": "10% Off Hour Purchase",      "w": 40, "disc": 10, "ac": 0,   "cfi": 0,   "min": 5 }
];
    $wpdb->insert("{$wpdb->prefix}spin_profiles", [
      'key' => 'standard_nudge',
      'name'=> 'Standard Nudge Wheel',
      'active'=> 1,
      'slices_json' => wp_json_encode($slices),
      'monthly_cap' => 0
    ]);
  }
});

/**
 * Serve JS to pages (optional enable here; otherwise you can load manually)
 */
add_action('wp_enqueue_scripts', function () {
  wp_register_script('piston-spinner', PISTON_SPINNER_URL . 'js/spinner.js', [], '0.1.0', true);
  wp_localize_script('piston-spinner', 'PistonSpinnerConfig', [
    'BASE' => PISTON_SPINNER_URL,
    'API'  => [
      'eligibility' => site_url('/wp-content/plugins/spinner/api/eligibility.php'),
      'create'      => site_url('/wp-content/plugins/spinner/api/create_reward.php'),
      'claim'       => site_url('/wp-content/plugins/spinner/api/claim.php'),
    ],
    'HOURS_URL' => PISTON_SPINNER_PURCHASE_URL,
  ]);
  // Do not enqueue globally; you can enqueue on your success pages when needed:
  // wp_enqueue_script('piston-spinner');
});
