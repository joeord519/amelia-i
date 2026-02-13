<?php
/**
 * Flight Scheduler Page
 * This file should be inside your active theme folder.
 */

global $wpdb;

// Fetch available locations
$available_locations = $wpdb->get_results("SELECT DISTINCT home_location FROM " . $wpdb->prefix . "aircraft");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flight Scheduler</title>
</head>
<body>

<!-- ✅ Flight Scheduler Form -->
<form id="scheduler-form">
    <label for="location">Select Location:</label>
    <select name="location" id="location" required>
        <option value="">-- Select --</option>
        <?php foreach ($available_locations as $location): ?>
            <option value="<?php echo esc_attr($location->home_location); ?>">
                <?php echo esc_html($location->home_location); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="aircraft">Select Aircraft:</label>
    <select name="aircraft" id="aircraft" required>
        <option value="">-- Select --</option>
    </select>

    <label for="cfi">Select CFI:</label>
    <select name="cfi" id="cfi" required>
        <option value="">-- Select --</option>
    </select>

    <label for="flight_date">Select Date:</label>
    <input type="date" name="flight_date" id="flight_date" required>

    <label for="appointment_time">Available Time Slots:</label>
    <select name="appointment_time" id="appointment_time" required>
        <option value="">-- Select --</option>
    </select>

    <button type="submit">Schedule Flight</button>
</form>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const locationField = document.getElementById("location");
    const aircraftField = document.getElementById("aircraft");
    const cfiField = document.getElementById("cfi");

    locationField.addEventListener("change", function () {
        let location = locationField.value.trim();
        console.log("🟡 DEBUG: Selected Location:", location);

        if (!location) {
            console.error("🔴 ERROR: No location selected.");
            return;
        }

        let formData = new URLSearchParams({
            action: "fetch_aircraft_and_cfis",
            location: location
        });

        console.log("🔵 DEBUG: Sending AJAX Request:", formData.toString());

        fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log("🟢 DEBUG: AJAX Response:", data);
            if (data.success) {
                aircraftField.innerHTML = data.aircraft;
                cfiField.innerHTML = data.cfis;
            } else {
                console.error("🔴 ERROR:", data.data.message);
            }
        })
        .catch(error => console.error("🔴 AJAX Error:", error));
    });

</script>

</body>
</html>
