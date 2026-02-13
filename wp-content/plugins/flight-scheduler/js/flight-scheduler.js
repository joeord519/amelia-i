$(document).ready(function () {
    // Fetch Locations on Page Load
    $.get("fetch_locations.php", function (response) {
        console.log("Raw Fetch Locations Response:", response);

        try {
            response = JSON.parse(response);
        } catch (e) {
            console.error("JSON Parsing Error (Locations):", e, response);
            return;
        }

        console.log("Parsed Fetch Locations Response:", response);

        if (response.success && Array.isArray(response.locations)) {
            $("#location").empty().append(`<option value="">Select Location</option>`);
            response.locations.forEach(loc => {
                $("#location").append(`<option value="${loc.airport_code}">${loc.airport_code} - ${loc.name}</option>`);
            });
        } else {
            console.error("Invalid JSON structure for locations:", response);
        }
    });

    // Fetch Aircraft Based on Selected Location
    $("#location").change(function () {
    let location = $("#location").val();
    if (!location) {
        console.error("Location ID is missing.");
        return;
    }

    console.log("Fetching aircraft for location:", location);

    $.post("fetch_aircraft.php", { location: location }, function (response) {
        console.log("🛠 RAW Aircraft Response:", response); // Log before parsing

        try {
            if (typeof response === "string") {
                response = JSON.parse(response); // Convert if it's a string
            }
        } catch (e) {
            console.error("❌ JSON Parsing Error (Aircraft):", e, response);
            return;
        }

        console.log("✅ Parsed Aircraft Response:", response);

        if (response.success && Array.isArray(response.aircraft)) {
            $("#aircraft").empty().append(`<option value="">Select Aircraft</option>`);
            response.aircraft.forEach(ac => {
                $("#aircraft").append(`<option value="${ac.id}">${ac.id} - ${ac.name}</option>`);
            });
        } else {
            console.warn("⚠ No aircraft data available:", response);
        }
    }).fail(function (xhr, status, error) {
        console.error("AJAX Error (fetch_aircraft.php):", error);
    });
});


    // Format phone number input dynamically
    $("#mobileNumber").on("input", function () {
        let phone = $(this).val().replace(/\D/g, "").slice(0, 10);
        let formattedPhone = phone.replace(/^(\d{3})(\d{3})(\d{4})$/, "($1) $2-$3");
        $(this).val(formattedPhone);
    });

    // Verify User
    $("#verifyUser").click(function () {
        let mobile = $("#mobileNumber").val();
        let securityWord = $("#securityWord").val();

        if (!mobile || !securityWord) {
            Swal.fire("Error", "Please enter your mobile number and security word.", "error");
            return;
        }

        $.post("verify_user.php", { mobile, securityWord }, function (response) {
            try {
                response = JSON.parse(response);
            } catch (e) {
                console.error("JSON Parsing Error (verify_user):", e, response);
                return;
            }

            if (response.success) {
                Swal.fire("Success", "User verified!", "success");
                $("#bookingFields").show();
            } else {
                Swal.fire("Error", response.message, "error");
            }
        }).fail(function (xhr, status, error) {
            console.error("AJAX Error (verify_user.php):", error);
        });
    });

    // Fetch CFIs
    function fetchCFIs() {
        let flightType = $("#flightType").val();
        let location = $("#location").val();

        if (!location) {
            console.error("No location selected for CFI lookup.");
            return;
        }

        if (["dual_training", "dual_cross_country"].includes(flightType)) {
            console.log("Fetching CFIs for home_airport:", location);

            $.post("fetch_cfis.php", { location }, function (response) {
                try {
                    response = JSON.parse(response);
                } catch (e) {
                    console.error("JSON Parsing Error (CFIs):", e, response);
                    return;
                }

                if (response.success && Array.isArray(response.cfis)) {
                    $("#cfi").empty().append(`<option value="">Select CFI</option>`);
                    response.cfis.forEach(cfi => {
                        $("#cfi").append(`<option value="${cfi.id}">${cfi.name}</option>`);
                    });
                    $("#cfiField").show();
                } else {
                    console.warn("No CFIs available:", response);
                    $("#cfiField").hide();
                }
            }).fail(function (xhr, status, error) {
                console.error("AJAX Error (fetch_cfis.php):", error);
            });
        } else {
            $("#cfiField").hide();
            $("#cfi").empty().append(`<option value="">Select CFI</option>`);
        }
    }

    // Fetch Available Time Slots
    function fetchTimeSlots() {
        let aircraft = $("#aircraft").val();
        let flightType = $("#flightType").val();
        let cfi = $("#cfi").val() || null;
        let date = $("#date").val();

        if (!aircraft || !flightType || !date) {
            console.warn("Missing required fields for timeslot fetch.");
            return;
        }

        if (["dual_training", "dual_cross_country"].includes(flightType) && !cfi) {
            console.warn("CFI required for dual flight but not selected.");
            return;
        }

        console.log("Fetching available time slots for:", { aircraft, cfi, flightType, date });

        $.post("fetch_timeslots.php", { aircraft, cfi, flightType, date }, function (response) {
            console.log("Raw Fetch Time Slots Response:", response);

            try {
                response = JSON.parse(response);
            } catch (e) {
                console.error("JSON Parsing Error (Time Slots):", e, response);
                return;
            }

            let dropdown = $("#startTime");
            dropdown.empty().append(`<option value="">Select Start Time</option>`);

            if (response.success && Array.isArray(response.slots) && response.slots.length > 0) {
                response.slots.forEach(slot => {
                    dropdown.append(`<option value="${slot.start_time}">${slot.start_time}</option>`);
                });

                console.log("Time slots successfully added to dropdown.");
            } else {
                console.warn("No available slots found.");
                dropdown.append(`<option value="">No Available Slots</option>`);
            }
        }).fail(function (xhr, status, error) {
            console.error("AJAX Error (fetch_timeslots.php):", error);
        });
    }

    // Validate Required Fields Before Fetching Time Slots
    function validateAndFetchTimeSlots() {
        console.log("Validating fields for timeslot fetch...");
        fetchTimeSlots();
    }

    // Trigger Fetch When Any Key Field Changes
    $("#flightType, #aircraft, #cfi, #date").change(validateAndFetchTimeSlots);

    // Show/Hide CFI Field & Fetch CFIs When Needed
    $("#flightType").change(fetchCFIs);

    // Fetch CFIs on Page Load If Dual Training is Already Selected
    if (["dual_training", "dual_cross_country"].includes($("#flightType").val())) {
        fetchCFIs();
    }
});

