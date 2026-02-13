<?php
// ✅ Updated Flight Booking Form with Full Functionality

session_start();
require_once __DIR__ . '/db_connect.php'; // ✅ Ensure Correct Path

// ✅ Fetch Locations
$locations = [
    '1H0' => 'Creve Coeur (1H0)',
    'KALN' => 'St. Louis Regional (KALN)'
];

// ✅ Fetch Available Aircraft
$aircraft_query = "SELECT tail_number, home_location FROM wp_aircraft WHERE status = 'Available' AND home_location IN ('1H0', 'KALN')";
$aircraft_result = $conn->query($aircraft_query);
if (!$aircraft_result) {
    die("❌ Aircraft Query Failed: " . $conn->error);
}

$aircraft_list = [];
while ($row = $aircraft_result->fetch_assoc()) {
    $aircraft_list[$row['home_location']][] = [
        'tail_number' => $row['tail_number'],
        'home_location' => $row['home_location']
    ];
}

// ✅ Fetch Active CFIs
$cfi_query = "SELECT cfi_id, first_name, last_name, home_airport FROM wp_cfis WHERE status = 'Active' AND home_airport IN ('1H0', 'KALN')";
$cfi_result = $conn->query($cfi_query);
if (!$cfi_result) {
    die("❌ CFI Query Failed: " . $conn->error);
}

$cfi_list = [];
while ($row = $cfi_result->fetch_assoc()) {
    $cfi_list[$row['home_airport']][] = [
        'cfi_id' => $row['cfi_id'],
        'name' => $row['first_name'] . ' ' . $row['last_name']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flight Booking</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script type="text/javascript">
        var aircraft_list = <?php echo json_encode($aircraft_list); ?>;
        var cfi_list = <?php echo json_encode($cfi_list); ?>;
    </script>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        form { display: flex; flex-direction: column; width: 300px; gap: 10px; }
        button { background-color: #007bff; color: white; padding: 10px; border: none; cursor: pointer; }
        button:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <h2>Book a Flight</h2>
    <form id="flightBookingForm">
        <label for="mobile">Mobile Number:</label>
        <input type="tel" id="mobile" name="mobile" required>

        <label for="security_word">Security Word:</label>
        <input type="password" id="security_word" name="security_word" required>

        <button type="button" id="verifyUser">Verify</button>

        <label for="location">Select Location:</label>
        <select id="location" name="location" disabled>
            <option value="">-- Select --</option>
            <?php foreach ($locations as $code => $name) { ?>
                <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
            <?php } ?>
        </select>

        <label for="aircraft">Select Aircraft:</label>
        <select id="aircraft" name="aircraft" disabled></select>

        <label for="flight_type">Select Type of Flight:</label>
        <select id="flight_type" name="flight_type" disabled>
            <option value="">-- Select --</option>
            <option value="dual_training">Dual Training (2hr)</option>
            <option value="dual_cross_country">Dual Cross Country (4hr)</option>
            <option value="solo_training">Solo Training Flight (2hr)</option>
            <option value="solo_cross_country">Solo Cross Country (4hr)</option>
            <option value="rental_solo">Rental Solo - Local (2hr)</option>
            <option value="rental_cross_country">Rental Cross Country (4hr)</option>
            <option value="checkride">Checkride (8hr)</option>
        </select>

        <label for="cfi">Select CFI:</label>
        <select id="cfi" name="cfi" disabled></select>

        <label for="date">Select Date:</label>
        <select id="date" name="date" disabled>
            <option value="">-- Select Date --</option>
        </select>

        <label for="start_time">Select Start Time:</label>
        <select id="start_time" name="start_time" disabled>
            <option value="">-- Select Start Time --</option>
        </select>

        <button type="submit" id="bookFlight">Book Flight</button>
    </form>

    <script>
    $(document).ready(function() {
        $('#verifyUser').click(function() {
            let mobile = $('#mobile').val().trim();
            let securityWord = $('#security_word').val().trim();
            if (!mobile || !securityWord) {
                Swal.fire("Missing Info", "Please enter your Mobile Number and Security Word.", "warning");
                return;
            }
            $.post("/wp-content/plugins/flight-booking/verify_user.php", { mobile, security_word: securityWord }, function(response) {
                if (response.status === "success") {
                    Swal.fire("Success", "User verified!", "success");
                    $('#location, #aircraft, #flight_type, #cfi, #date, #start_time, #bookFlight').prop('disabled', false);
                } else {
                    Swal.fire("Verification Failed", response.error || "Incorrect security word.", "error");
                }
            }, "json");
        });

        $('#location').change(function() {
            let location = $(this).val();
            $('#aircraft').html('<option value="">-- Select Aircraft --</option>');
            if (aircraft_list[location]) {
                aircraft_list[location].forEach(aircraft => {
                    $('#aircraft').append(`<option value="${aircraft.tail_number}">${aircraft.tail_number}</option>`);
                });
                $('#aircraft').prop('disabled', false);
            }
        });

        $('#flight_type').change(function() {
            let flightType = $(this).val();
            let location = $('#location').val();
            $('#cfi').html('<option value="">-- Select CFI --</option>').prop('disabled', flightType.includes("solo") || flightType.includes("rental"));
            if (!flightType.includes("solo") && !flightType.includes("rental") && cfi_list[location]) {
                cfi_list[location].forEach(cfi => {
                    $('#cfi').append(`<option value="${cfi.cfi_id}">${cfi.name}</option>`);
                });
                $('#cfi').prop('disabled', false);
            }
        });
    });
    </script>

    <script src="/wp-content/plugins/flight-booking/js/flight_booking.js"></script>
</body>
</html>