<?php
/**
 * Neve Default functions.php (Minimal Safe Version)
 *
 * - This file restores WordPress without any extra features
 * - No FullCalendar, No Aircraft Docs—just enough to get the site working
 */

define('NEVE_VERSION', '3.8.16');

/**
 * ✅ Load Neve's Core Functions (Ensures Header Loads)
 */
if (!function_exists('neve_body_attrs')) {
    if (file_exists(get_template_directory() . '/inc/core/class-theme.php')) {
        require_once get_template_directory() . '/inc/core/class-theme.php';
    }
}

/**
 * ✅ Standard Neve Theme Includes
 */
require_once get_template_directory() . '/header-footer-grid/loader.php';
require_once get_template_directory() . '/start.php';
