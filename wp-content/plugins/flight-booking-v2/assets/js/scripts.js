$(document).ready(function() {
    console.log("✅ Scripts Loaded");

    // ✅ Open Date Picker Modal
    $('#openDateModal').click(function() {
        $('#dateModal').fadeIn();
    });

    // ✅ Confirm Date Selection
    $('#confirmDate').click(function() {
        let selectedDate = $('#datePicker').val();
        if (!selectedDate) {
            alert("Please select a date!");
            return;
        }
        $('#date').val(selectedDate);
        $('#dateModal').fadeOut();
    });

    // ✅ Open Time Slot Modal
    $('#openTimeModal').click(function() {
        $('#timeModal').fadeIn();
    });

    // ✅ Confirm Time Slot Selection
    $('#confirmTime').click(function() {
        let selectedTime = $('#timePicker').val();
        if (!selectedTime) {
            alert("Please select a time slot!");
            return;
        }
        $('#time_slot').val(selectedTime);
        $('#timeModal').fadeOut();
    });

    // ✅ Close modals when clicking outside of modal content
    $('.modal').click(function(e) {
        if ($(e.target).is('.modal')) {
            $(this).fadeOut();
        }
    });

    // ✅ Initialize Flatpickr Date Picker
    $(".flatpickr").flatpickr({
        enableTime: false,
        dateFormat: "Y-m-d",
        minDate: "today"
    });

    // ✅ User Verification Logic - Now inside `document.ready()`
    $('#verifyUser').off('click').on('click', function() {
        let mobile = $('#mobile').val().trim();
        let securityWord = $('#security_word').val().trim();

        if (!mobile || !securityWord) {
            alert("Please enter your Mobile Number and Security Word.");
            return;
        }

        console.log("📡 Sending AJAX request to verify user...");
        
        $.ajax({
    url: "/wp-content/plugins/flight-booking-v2/api/verify_user.php",
    type: "POST",
    data: { mobile: mobile, security_word: securityWord },
    beforeSend: function() {
        console.log("📡 Sending AJAX request with data:", { mobile: mobile, security_word: securityWord });
    },
    success: function(response) {
        console.log("✅ Verification Response:", response);


                let data;
                try {
                    data = typeof response === "string" ? JSON.parse(response) : response;
                } catch (e) {
                    console.error("❌ JSON Parsing Error:", e, "Raw Response:", response);
                    alert("Error verifying user. Invalid server response.");
                    return;
                }

                if (data.status === "success") {
                    console.log("✅ User Verified - Unlocking Location Dropdown");

                    // Force a small delay to ensure DOM updates
                    setTimeout(function() {
                        $('#location').prop('disabled', false);
                        console.log("✅ Location dropdown should now be enabled.");
                    }, 300);
                } else {
                    console.error("❌ Verification Failed:", data.error);
                    alert("Verification Failed: " + data.error);
                }
            },
            error: function(xhr, status, error) {
                console.error("❌ AJAX Error:", error);
                alert("Error verifying user. Please check console for details.");
            }
        });
    });
});
