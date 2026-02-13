<?php
/* Spinner standalone config (no WordPress required) */

/** Debug */
define('SPINNER_DEBUG', true); // set false in prod

/** CORS (optional) */
define('SPINNER_ALLOW_ORIGIN', 'https://amelia-i.com'); // or '*' for testing

/** DB table prefix (your WP prefix, usually 'wp_') */
define('SPINNER_DB_PREFIX', 'wp_'); // <-- change if your prefix differs

/** Purchase page (where students buy hours) */
define('PISTON_SPINNER_PURCHASE_URL', 'https://skylistpro.com/need-more-hours/');

/** Cooldowns / caps */
define('PISTON_SPINNER_CHECKOUT_COOLDOWN_HOURS', 24);
define('PISTON_SPINNER_BOOKING_COOLDOWN_HOURS', 72);
define('PISTON_SPINNER_MONTHLY_REWARD_LIMIT', 3);

/** Blocked programs */
$GLOBALS['PISTON_SPINNER_BLOCKED_PROGRAMS'] = ['ACCEL_ALLIN'];

/** Minimum purchase hours required to claim (0 = none) */
define('PISTON_SPINNER_MIN_PURCHASE_HOURS', 0.0);

/** Security (optional): shared secret for HMAC (set later if you want) */
define('SPINNER_HMAC_SECRET', ''); // e.g., 'a_very_secret_string'

/** Local PDO connector path (must set $pdo) */
define('PISTON_SPINNER_LOCAL_DBCONNECT', __DIR__ . '/db_connect.php');

