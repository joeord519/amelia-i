<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/db_connect.php";

// ✅ Capture JSON input
$inputJSON = file_get_contents("php://input");
$input = json_decode($inputJSON, true);

if (!isset($input['student_id'], $input['tail_number'], $input['start_time'], $input['end_time'], $input['flight_type'])) {
    echo json_encode(["success" => false, "message" => "Missing required fields."]);
    exit();
}

$student_id = mysqli_real_escape_string($con, $input['student_id']);
$tail_number = mysqli_real_escape_string($con, $input['tail_number']);
$cfi_id = isset($input['cfi_id']) ? mysqli_real_escape_string($con, $input['cfi_id']) : null;
$start_time = mysqli_real_escape_string($con, $input['start_time']);
$end_time = mysqli_real_escape_string($con, $input['end_time']);
$flight_type = mysqli_real_escape_string($con, $input['flight_type']);

// ✅ Prevent Double Booking (Check for Conflicts)
$conflictQuery = "SELECT * FROM wp_flight_schedule 
                  WHERE (tail_number = '$tail_number' OR cfi_id = '$cfi_id')
                  AND start_time < '$end_time' 
                  AND end_time > '$start_time' 
                  AND status = 'Scheduled'";

$conflictResult = mysqli_query($con, $conflictQuery);

if (mysqli_num_rows($conflictResult) > 0) {
    echo json_encode(["success" => false, "message" => "This aircraft or instructor is already booked during this time."]);
    exit();
}

// ✅ Insert Booking into Database
$query = "INSERT INTO wp_flight_schedule (student_id, tail_number, cfi_id, start_time, end_time, flight_type, status) 
          VALUES ('$student_id', '$tail_number', " . ($cfi_id ? "'$cfi_id'" : "NULL") . ", '$start_time', '$end_time', '$flight_type', 'Scheduled')";

if (mysqli_query($con, $query)) {
    echo json_encode(["success" => true, "message" => "Booking successfully created."]);
} else {
    echo json_encode(["success" => false, "message" => "Database error: " . mysqli_error($con)]);
}

/** ✅ Submit Flight Booking */
function submitBooking(student_id, tail_number, cfi_id, start_time, end_time, flight_type) {
    console.log("📅 Sending booking request...");

    fetchData("save_booking", {
        student_id,
        tail_number,
        cfi_id,
        start_time,
        end_time,
        flight_type
    }).then(data => {
        console.log("📅 Booking Response:", data);

        if (data.success) {
            Swal.fire({
                icon: "success",
                title: "Booking Confirmed!",
                text: data.message,
                confirmButtonText: "OK"
            }).then(() => {
                // Refresh page or redirect to a confirmation page
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: "error",
                title: "Booking Failed",
                text: data.message || "An error occurred. Please try again."
            });
        }
    }).catch(error => {
        console.error("❌ Booking API Error:", error);
        Swal.fire({
            icon: "error",
            title: "System Error",
            text: "There was an issue saving your booking. Please try again."
        });
    });
}

?>
