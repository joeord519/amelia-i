<?php
/**
 * Plugin Name: Piston Deal Agent - Joey
 * Description: Flight hour deal desk agent ("Joey") for negotiating and selling hour packages to students.
 * Version: 0.1.0
 * Author: Piston Aviation
 */

if ( ! defined('ABSPATH') ) {
    exit;
}

define('PISTON_DEAL_AGENT_VERSION', '0.1.0');
define('PISTON_DEAL_AGENT_PATH', plugin_dir_path(__FILE__));
define('PISTON_DEAL_AGENT_URL', plugin_dir_url(__FILE__));

/**
 * Auto-load DB helper on init.
 */
function piston_deal_agent_init() {
    require_once PISTON_DEAL_AGENT_PATH . 'includes/class-deal-agent-db.php';
    Piston_Deal_Agent_DB::maybe_create_tables();
}
add_action('plugins_loaded', 'piston_deal_agent_init');

/**
 * Run DB creation on activation as a safety.
 */
function piston_deal_agent_activate() {
    require_once PISTON_DEAL_AGENT_PATH . 'includes/class-deal-agent-db.php';
    Piston_Deal_Agent_DB::maybe_create_tables();
}
register_activation_hook(__FILE__, 'piston_deal_agent_activate');
