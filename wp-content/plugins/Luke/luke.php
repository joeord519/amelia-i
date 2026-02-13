<?php
/*
Plugin Name: Luke - AI Sales Agent (Bridge)
Description: Small bridge that embeds the standalone Luke UI into WordPress pages.
Version: 0.2.0
Author: Piston Aviation
*/

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shortcode: [luke_chat]
 * Embeds the standalone luke-ui.php in an iframe.
 */
function luke_chat_shortcode() {
    // Build absolute URL to luke-ui.php
    $ui_url = plugins_url( 'luke-ui.php', __FILE__ );

    ob_start();
    ?>
    <div style="position:relative; min-height:400px;">
        <iframe
            src="<?php echo esc_url( $ui_url ); ?>"
            style="width:100%; min-height:600px; border:0;"
            loading="lazy"
            title="Luke – Piston Aviation AI Sales Agent"
        ></iframe>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'luke_chat', 'luke_chat_shortcode' );

