<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flight Booking</title>
    
    <!-- ✅ Load jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- ✅ Load Custom Styles -->
    <link rel="stylesheet" href="/wp-content/plugins/flight-booking-v2/assets/css/style.css">

    <!-- ✅ Load Custom Scripts -->
    <script src="/wp-content/plugins/flight-booking-v2/assets/js/scripts.js" defer></script>

    <!-- ✅ Load Flatpickr CSS & JS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

</head>
<body>

<!-- User Verification -->
<label for="mobile">Mobile Number:</label>
<input type="tel" id="mobile" name="mobile" required>

<label for="security_word">Security Word:</label>
<input type="password" id="security_word" name="security_word" required>

<button type="button" id="verifyUser" class="styled-button">Verify</button>

<!-- Location Selection (Disabled Until Verified) -->
<label for="location">Select Location:</label>
<select id="location" name="location" disabled>
    <option value="">-- Select --</option>
    <option value="1H0">Creve Coeur (1H0)</option>
    <option value="KALN">St. Louis Regional (KALN)</option>
</select>

<!-- Aircraft Selection (Disabled Until Location Selected) -->
<label for="aircraft">Select Aircraft:</label>
<select id="aircraft" name="aircraft" disabled></select>

<!-- CFI Selection (Disabled Until Location Selected) -->
<label for="cfi">Select CFI:</label>
<select id="cfi" name="cfi" disabled></select>

<label for="date">Select Date:</label>
<input type="text" id="date" name="date" required readonly>
<button type="button" id="openDateModal" class="styled-button">Choose Date</button>

<label for="time_slot">Available Time Slots:</label>
<input type="text" id="time_slot" name="time_slot" required readonly>
<button type="button" id="openTimeModal" class="styled-button">Choose Time</button>

<?php include 'templates/date_picker_modal.php'; ?>
<?php include 'templates/time_picker_modal.php'; ?>
