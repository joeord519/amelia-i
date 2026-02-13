<?php
/**
 * Plugin Name: Suggestion Box
 * Description: A simple standalone suggestion box module.
 */

function suggestion_box_shortcode() {
  ob_start(); ?>
  <form method="POST" action="" id="suggestionForm">
    <label for="category">Category:</label>
    <select name="category" id="category">
      <option value="Calendar">Calendar</option>
      <option value="Scheduler">Scheduler</option>
    </select>

    <label for="suggestion">Your Suggestion:</label><br>
    <textarea name="suggestion" id="suggestion" rows="4" cols="50" required></textarea><br>

    <label for="name">Your Name (optional):</label><br>
    <input type="text" name="name" id="name"><br>

    <label for="email">Your Email (optional):</label><br>
    <input type="email" name="email" id="email"><br>

    <label for="phone">Your Phone (optional):</label><br>
    <input type="text" name="phone" id="phone"><br><br>

    <input type="submit" name="submit_suggestion" value="Submit">
  </form>
  <?php
  if (isset($_POST['submit_suggestion'])) {
    global $wpdb;
    $table_name = $wpdb->prefix . "suggestions";
    $wpdb->insert($table_name, [
      'category' => sanitize_text_field($_POST['category']),
      'suggestion' => sanitize_textarea_field($_POST['suggestion']),
      'name' => sanitize_text_field($_POST['name']),
      'email' => sanitize_email($_POST['email']),
      'phone' => sanitize_text_field($_POST['phone']),
      'submitted_at' => current_time('mysql')
    ]);
    echo "<p>Thank you! Your suggestion has been received.</p>";
  }
  return ob_get_clean();
}
add_shortcode('suggestion_box', 'suggestion_box_shortcode');
?>
