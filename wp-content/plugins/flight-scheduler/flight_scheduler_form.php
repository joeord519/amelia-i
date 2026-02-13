<?php
require_once('db_connect.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flight Scheduler</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.18/dist/sweetalert2.all.min.js"></script>
    <script src="js/flight-scheduler.js"></script>
</head>
<body>

<form id="flightBookingForm">
    <label>Mobile Number:</label>
    <input type="text" id="mobileNumber" required>

    <label>Security Word:</label>
    <input type="password" id="securityWord" required>

    <button type="button" id="verifyUser">Verify</button>

    <div id="bookingFields" style="display:none;">
        <label>Select Location:</label>
        <select id="location"></select>

        <label>Select Aircraft:</label>
        <select id="aircraft"></select>

        <label>Select Type of Flight:</label>
<select id="flightType">
    <option value="">Select Flight Type</option>
    <option value="dual_training">Dual Training (2 hr)</option>
    <option value="dual_cross_country">Dual Cross Country (4 hr)</option>
    <option value="solo_training">Solo Training (2 hr)</option>
    <option value="solo_cross_country">Solo Cross Country (4 hr)</option>
    <option value="rental_solo">Rental Solo - Local (2 hr)</option>
    <option value="rental_cross_country">Rental Cross Country (4 hr)</option>
    <option value="checkride">Checkride (8 hr)</option>
</select>


        <div id="cfiField" style="display:none;">
            <label>Select CFI:</label>
            <select id="cfi"></select>
        </div>

        <div id="adminCodeField" style="display:none;">
            <label>Admin Security Code:</label>
            <input type="password" id="adminCode">
        </div>

        <label>Select Date:</label>
        <input type="date" id="date">

        <label>Select Start Time:</label>
        <input type="time" id="startTime">

        <button type="submit" id="bookFlight">Book Flight</button>
    </div>
</form>

</body>
</html>
