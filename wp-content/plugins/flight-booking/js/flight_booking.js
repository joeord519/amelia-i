function formatDate(inputDate) {
    let dateObj = new Date(inputDate);
    let month = (dateObj.getMonth() + 1).toString().padStart(2, '0'); // Convert to MM format
    let day = dateObj.getDate().toString().padStart(2, '0'); // Convert to DD format
    let year = dateObj.getFullYear(); // YYYY format
    return `${month}/${day}/${year}`; // Output MM/DD/YYYY
}

$(document).ready(function () {
    // ✅ Verify User Function
    $('#verifyUser').click(function () {
        let mobile = $('#mobile').val().trim();
        let securityWord = $('#security_word').val().trim();

        if (!mobile || !securityWord) {
            Swal.fire("Missing Info", "Please enter your Mobile Number and Security Word.", "warning");
            return;
        }

        $.post("/wp-content/plugins/flight-booking/verify_user.php", { mobile, security_word: securityWord }, function (response) {
            console.log("🟢 Verification Response:", response);

            if (response.status === "success") {
                Swal.fire("Success", `User verified: ${response.student_name}`, "success");
                $("#flightBookingForm").data("student_id", response.student_id);
                $('#location, #aircraft, #flight_type, #cfi, #date, #start_time, #bookFlight').prop('disabled', false);
            } else {
                Swal.fire("Verification Failed", response.message || "Incorrect security word.", "error");
            }
        }, "json").fail(function (jqXHR, textStatus, errorThrown) {
            console.error("❌ AJAX ERROR:", textStatus, errorThrown);
            Swal.fire("Error", "Failed to verify user!", "error");
        });
    });

    $("#cfi, #aircraft").change(function () {
        let aircraft = $("#aircraft").val();
        let cfi = $("#cfi").val();

        if (!aircraft) return;

        console.log("🔵 Fetching available slots for aircraft:", aircraft, "CFI:", cfi || "N/A (Solo Flight)");
        $("#date").prop("disabled", false).html('<option value="">-- Loading Dates... --</option>');
        $("#start_time").prop("disabled", true).html('<option value="">-- Select Start Time --</option>');

        $.post("/wp-content/plugins/flight-booking/fetch_available_slots.php", 
        { aircraft, cfi: cfi || "N/A" }, 
        function (response) {
            console.log("🟢 RAW RESPONSE RECEIVED:", response);

            if (typeof response !== "object") {
                console.error("❌ ERROR: Response is not valid JSON!", response);
                Swal.fire("Error", "Invalid response format!", "error");
                return;
            }

            if (response.status !== "success") {
                console.error("❌ ERROR: Invalid response format!", response);
                Swal.fire("Error", "Invalid response format!", "error");
                return;
            }

            let slots = response.slots;
            let dateOptions = '<option value="">-- Select Date --</option>';
            let groupedSlots = {};

            console.log("🔍 Raw slots data:", slots);

            if (!slots || slots.length === 0) {
                console.warn("⚠️ WARNING: No available slots returned.");
                $("#date").html('<option value="">No available dates</option>').prop("disabled", true);
                return;
            }

            // ✅ Group slots by formatted date
            slots.forEach(slot => {
                let formattedDate = formatDate(slot.date);
                if (!groupedSlots[formattedDate]) {
                    groupedSlots[formattedDate] = [];
                }
                groupedSlots[formattedDate].push(slot.start_time);
            });

            console.log("📌 Grouped slots by formatted date:", groupedSlots);

            // ✅ Clear "Loading Dates..." and update dropdown
            $("#date").empty();
            Object.keys(groupedSlots).forEach(date => {
                dateOptions += `<option value="${date}">${date}</option>`;
            });

            if (Object.keys(groupedSlots).length > 0) {
                $("#date").html(dateOptions).prop("disabled", false);
                $("#start_time").html('<option value="">-- Select Start Time --</option>').prop("disabled", true);
                console.log("✅ Date dropdown updated successfully!");
            } else {
                $("#date").html('<option value="">No available dates</option>').prop("disabled", true);
                $("#start_time").html('<option value="">No available times</option>').prop("disabled", true);
                console.log("❌ No available dates found.");
            }
        }, "json").fail(function (jqXHR, textStatus, errorThrown) {
            console.error("❌ AJAX ERROR:", textStatus, errorThrown);
            Swal.fire("Error", "Failed to fetch slots from server!", "error");
        });
    });

    // ✅ Booking Submission Logic
    $("#flightBookingForm").submit(function (event) {
        event.preventDefault();
        let aircraft = $("#aircraft").val();
        let cfi = $("#cfi").val();
        let flight_type = $("#flight_type").val();
        let student_id = $("#flightBookingForm").data("student_id");
        let date = $("#date").val();
        let start_time = $("#start_time").val();
        let formattedTime = start_time.length === 5 ? start_time + ":00" : start_time;

        console.log("🟢 Debugging Form Submission Data:");
        console.log("✈ Aircraft:", aircraft);
        console.log("👨‍🏫 CFI:", cfi);
        console.log("📋 Flight Type:", flight_type);
        console.log("🆔 Student ID:", student_id);
        console.log("📅 Date:", date);
        console.log("⏰ Start Time:", formattedTime);

        if (!aircraft || !flight_type || !student_id || !date || !start_time) {
            console.error("❌ ERROR: Missing fields!");
            Swal.fire("Error", "All fields are required!", "error");
            return;
        }

        if (!cfi && (flight_type.includes("solo") || flight_type.includes("rental_solo"))) {
            console.log("⚠️ Solo flight detected. Setting CFI to 'N/A'");
            cfi = "N/A";
        }

        $.post("/wp-content/plugins/flight-booking/process_booking.php", 
        { aircraft, cfi, flight_type, student_id, date, start_time: formattedTime }, 
        function (response) {
            console.log("🟢 Booking Response:", response);

            if (response.status === "success") {
                Swal.fire("Success!", "Booking confirmed!", "success").then(() => {
                    location.reload();
                });
            } else {
                Swal.fire("Error", response.message, "error");
            }
        }, "json").fail(function (jqXHR, textStatus, errorThrown) {
            console.error("❌ AJAX ERROR:", textStatus, errorThrown);
            Swal.fire("Error", "Failed to submit booking!", "error");
        });
    });
});

