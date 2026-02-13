$(document).ready (function () {
    // Global variables to store assigned CFI and home airport
    let assignedCfiId = null;
    let assignedCfiName = "None";
    let homeAirportName = "Unknown";
    let StudentName = "response.student_name";
    let student_name = "response.student_name";

    console.log("✅ Document Ready - Script Loaded");

    // ✅ Define `fetchCFIs()` Before Calling It
    function fetchCFIs(selectedAirport, forceReselect = false) {
        if (!selectedAirport || selectedAirport === "null" || selectedAirport === "Unknown") {
            console.warn("⚠️ Missing or invalid home_airport. CFIs cannot be loaded.");
            return;
        }

        console.log(`🚀 Fetching CFIs for airport: ${selectedAirport}`);

        $.ajax({
            url: 'fetch_cfis.php',
            type: 'GET',
            data: { home_airport: selectedAirport },
            dataType: 'json',
            success: function (response) {
                console.log("✅ CFI API Response:", response);

                if (!response || response.length === 0 || response.error) {
                    Swal.fire({ icon: 'error', title: 'No CFIs Available', text: response.error || 'No CFIs found for this airport.' });
                    return;
                }

                let cfiList = response;
                console.log("Full CFIs received:", cfiList);

                if (forceReselect) {
                    console.log(`🔄 Airport changed: Showing CFI dropdown without a preselected CFI`);
                    assignedCfiId = null;
                    assignedCfiName = null;
                } else {
                    let assignedCfi = cfiList.find(cfi => String(cfi.cfi_id) === String(assignedCfiId));

                    if (assignedCfi) {
                        assignedCfiName = assignedCfi.full_name;
                        console.log(`✅ Assigned CFI Persisted: ${assignedCfiId} - ${assignedCfiName}`);
                    }
                }

                let cfiDisplayHtml = assignedCfiId ? `
                    <label for="cfi"><strong>Selected CFI:</strong></label><br>
                    <span id="selectedCfi">${assignedCfiName} (Primary CFI)</span><br>
                    <button id="changeCfiBtn" class="styled-btn">👨‍✈️ Change CFI</button>
                    <div id="cfiDropdownContainer" style="display:none; margin-top: 10px;"></div>
                ` : `
                    <label for="cfi"><strong>Select a CFI:</strong></label><br>
                    <select id="cfi">
                        ${cfiList.map(cfi => `<option value="${cfi.cfi_id}">${cfi.full_name}</option>`).join('')}
                    </select>
                    <button id="confirmCfiBtn" class="styled-btn">Confirm CFI</button>
                `;

                $('#cfiSelectionContainer').html(cfiDisplayHtml);
            },
            error: function (xhr, status, error) {
                console.error("CFI Fetch Error:", error, xhr.responseText);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load CFIs.' });
            }
        });
    }

    // ✅ Ensure `fetchAircraft()` is Defined Before Calling It
    function fetchAircraft(selectedLocation, selectedLessonType = "flight_lesson") {
        console.log("🚀 Fetching aircraft for airport:", selectedLocation, "Lesson Type:", selectedLessonType);

        if (!selectedLessonType) {
            console.error("❌ ERROR: Lesson Type is undefined! Defaulting to flight_lesson.");
            selectedLessonType = "flight_lesson";
        }

        $.ajax({
            url: 'fetch_aircraft.php',
            type: 'GET',
            data: { home_location: selectedLocation, lesson_type: selectedLessonType },
            dataType: 'json',
            success: function (aircraftList) {
                console.log("✅ Aircraft received:", aircraftList);
                let aircraftLabel = selectedLessonType === "simulator_lesson" ? "Select Simulator" : "Select Aircraft";

                let aircraftOptions = `<option value="" disabled selected>✈️ ${aircraftLabel}</option>`;

                if (aircraftList.length === 0) {
                    Swal.fire({ icon: 'error', title: 'No Aircraft Available', text: 'No aircraft found for this selection.' });
                }

                aircraftList.forEach(aircraft => {
                    aircraftOptions += `<option value="${aircraft.tail_number}" data-type="${aircraft.aircraft_type}">${aircraft.model} (${aircraft.tail_number})</option>`;
                });

                $('#aircraftSelect').html(aircraftOptions).prop('disabled', false);
            },
            error: function () {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load aircraft for this location.' });
            }
        });
    }

    // ✅ Fix: Ensure `fetchCFIs()` & `fetchAircraft()` Work on Airport Change
    $(document).on('click', '#confirmAirportBtn', function () {
        let newAirport = $('#airportSelect').val();
        let newAirportName = $('#airportSelect option:selected').text();

        if (!newAirport) {
            Swal.fire({ icon: 'error', title: 'Location Required', text: 'Please select an airport before proceeding.' });
            return;
        }

        sessionStorage.setItem("home_airport", newAirport);
        sessionStorage.setItem("selected_airport", newAirportName);
        sessionStorage.setItem("student_id", response.student_id);
        sessionStorage.setItem("student_name", response.student_name);  // ✅ Store student name


        $('#selectedAirport').text(newAirportName).show();
        $('#changeAirportBtn').show();
        $('#airportDropdownContainer').hide();

        console.log(`🚀 Airport changed to: ${newAirport} - Updating Aircraft & CFIs`);

        let selectedLessonType = sessionStorage.getItem("selected_lesson") || "flight_lesson";
        if (selectedLessonType === "simulator_lesson") {
            selectedLessonType = "simulator_lesson";
        }

        fetchCFIs(newAirport, true);
        fetchAircraft(newAirport, selectedLessonType);

        $('#changeCfiBtn').prop('disabled', false);
        $('#aircraftSelect').html('<option value="" disabled selected>✈️ Select an Aircraft</option>').prop('disabled', false);
    });

    // ✅ Ensure CFIs Load on Initial Page Load
    let homeAirport = sessionStorage.getItem("home_airport");
    if (homeAirport) {
        fetchCFIs(homeAirport);
        $('#changeCfiBtn').prop('disabled', false);
    }
});

    // Format phone number input (XXX) XXX-XXXX
    $('#phone').on('input', function () {
        let cleaned = $(this).val().replace(/\D/g, ''); // Remove non-numeric characters
        if (cleaned.length > 10) cleaned = cleaned.substring(0, 10);

        let formatted = '';
        if (cleaned.length > 6) {
            formatted = `(${cleaned.substring(0, 3)}) ${cleaned.substring(3, 6)}-${cleaned.substring(6)}`;
        } else if (cleaned.length > 3) {
            formatted = `(${cleaned.substring(0, 3)}) ${cleaned.substring(3)}`;
        } else if (cleaned.length > 0) {
            formatted = `(${cleaned}`;
        }

        $(this).val(formatted);
    });

    // Ensure phone is formatted properly before submission
    $('#verifyStudentForm').on('submit', function (e) {
        let phone = $('#phone').val().trim().replace(/\D/g, ''); // Remove formatting before sending

        if (phone.length !== 10) {
            Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Please enter a valid phone number.' });
            $('#phone').addClass('error');
            e.preventDefault();
            return;
        }

        $('#phone').val(phone); // Store raw digits before submitting
    });

    // Submit verification form
    $('#verifyStudentForm').on('submit', function (e) {
        e.preventDefault();

        let phone = $('#phone').val().trim();
        let securityWord = $('#security_word').val().trim();

        // Remove formatting before sending
        phone = phone.replace(/\D/g, '');

        // Reset error styles
        $('input').removeClass('error');

        if (phone.length !== 10 || securityWord === "") {
            Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Please enter a valid phone number and security word.' });
            if (phone.length !== 10) $('#phone').addClass('error');
            if (securityWord === "") $('#security_word').addClass('error');
            return;
        }

        $.ajax({
            url: 'verify_student.php',
            type: 'POST',
            data: { phone: phone, security_word: securityWord },
            dataType: 'json',
            success: function (response) {
                console.log("AJAX Response:", response);

                if (response.success) {
                    assignedCfiId = response.assigned_cfi_id || null;
                    assignedCfiName = response.assigned_cfi_name || "None";
                    homeAirport = response.home_airport || null;
                    homeAirportName = response.home_airport_name || "Unknown";


                    console.log("Assigned CFI ID:", assignedCfiId);
                    console.log("Assigned CFI Name:", assignedCfiName);
                    console.log("Home Airport:", homeAirport);
                    console.log("Student/Renter:", response.student_name);

                    Swal.fire({
                        icon: 'success',
                        title: 'Access Verified!',
                        html: `<strong>Welcome, ${response.name}</strong><br>
                               Aircraft Hours: ${response.aircraft_hours}<br>
                               Instructor Hours: ${response.instructor_hours}<br>
                               Contract Signed: ${response.latest_contract_file && response.latest_contract_file !== "No" ? '✅ Yes' : '❌ No'}`
                    }).then(() => {
                        $('#verification-section').hide();
                        $('#eligibility-section').show().empty().append(`
                            <h2>Flight Eligibility Check</h2>
                            <p>Checking your flight eligibility...</p>
                        `);
                        checkFlightEligibility(response.name, response.aircraft_hours, response.instructor_hours, response.latest_contract_file);
                    });
function checkFlightEligibility(name, aircraftHours, instructorHours, latestContractFile) {
    let eligibilityMessage = '';
    let eligibility = false;

    // Check if agreement is signed
    if (!latestContractFile || latestContractFile.trim().toLowerCase() === '' || latestContractFile.trim().toLowerCase() === 'no') {
        eligibilityMessage = `❌ You must sign the latest training agreement before booking.<br>
                             <button id="signAgreementBtn" class="sign-agreement-button">📝 Sign Agreement</button>`;
    } else if (aircraftHours < 1) {
        eligibilityMessage = `❌ You do not have enough aircraft hours to book a flight.<br>
                             <button id="addHoursBtn" class="add-hours-button">🔄 Add Hours</button>`;
    } else if (instructorHours < 1) {
        eligibilityMessage = `❌ You do not have enough instructor hours for dual training.<br>
                             <button id="addHoursBtn" class="add-hours-button">🔄 Add Hours</button>`;
    } else {
        eligibility = true;
    }

    // If eligible, show event selection
    if (eligibility) {
        eligibilityMessage = `✅ You are eligible to book flights!<br>
                             <h3>Select Event Type</h3>
                             <div id="eventTypeSelection">
                                <button class="event-btn" data-event="flight_lesson">🛩️ Flight Lesson</button>
                                <button class="event-btn" data-event="ground_lesson">📚 Ground Lesson</button>
                                <button class="event-btn" data-event="simulator_lesson">🕹️ Simulator Lesson</button>
                                <button class="event-btn rental-btn" data-event="rental_flight">🛩️ Rental Flight</button>
                                <button class="event-btn" data-event="flight_review">✅ Flight Review (BFR)</button>
                             </div>`;
    }

    $('#eligibility-section').show().empty().append(`
        <h2>Flight Eligibility Check</h2>
        <p><strong>${name}</strong>, here is your status:</p>
        <p>${eligibilityMessage}</p>
    `);
}

                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Access Denied',
                        text: 'Incorrect phone number or security word.',
                    });

                    $('#phone, #security_word').addClass('error');
                }
            },
            error: function () {
                Swal.fire({
                    icon: 'error',
                    title: 'Server Error',
                    text: 'Please try again later.',
                });
            }
        });

    });

   // Handle Event Selection
$(document).on('click', '.event-btn', function () {
    let selectedEvent = $(this).data('event');

    // Show/Hide CFI section dynamically
    if (selectedEvent === "rental_flight") {
        $('#cfiSelectionContainer').hide(); // Hide for Rental Flights
    } else {
        $('#cfiSelectionContainer').show(); // Show for other selections
    }

// Refresh CFI list based on selected airport
function fetchCFIs(selectedAirport, forceReselect = false) {
    console.log(`Fetching CFIs for airport: ${selectedAirport}`); // ✅ Debugging log

    $.ajax({
        url: 'fetch_cfis.php',
        type: 'GET',
        data: { home_airport: selectedAirport },
        dataType: 'json',
        success: function (response) {
            console.log("CFI API Response:", response); // ✅ Debugging log

            if (!response || response.length === 0 || response.error) {
                Swal.fire({ icon: 'error', title: 'No CFIs Available', text: response.error || 'No CFIs found for this airport.' });
                return;
            }

            let cfiList = response; // ✅ Store the full CFI list
            console.log("Full CFIs received:", cfiList); // ✅ Debugging log

            // ✅ If forcing a reselect (on airport change), do not preselect any CFI
            if (forceReselect) {
                console.log(`🔄 Airport changed: Showing CFI dropdown without a preselected CFI`);
                assignedCfiId = null;
                assignedCfiName = null;
            } else {
                // ✅ On initial load, try to find the student's assigned CFI
                let assignedCfi = cfiList.find(cfi => String(cfi.cfi_id) === String(assignedCfiId));

                if (assignedCfi) {
                    assignedCfiName = assignedCfi.full_name;
                    console.log(`✅ Assigned CFI Persisted: ${assignedCfiId} - ${assignedCfiName}`);
                }
            }

            console.log(`After matching, assignedCfiId: ${assignedCfiId}, assignedCfiName: ${assignedCfiName}`);

            // ✅ If no assigned CFI is set, show the dropdown immediately
            let cfiDisplayHtml = assignedCfiId ? `
                <label for="cfi"><strong>Selected CFI:</strong></label><br>
                <span id="selectedCfi">${assignedCfiName} (Primary CFI)</span><br>
                <button id="changeCfiBtn" class="styled-btn">👨‍✈️ Change CFI</button>
                <div id="cfiDropdownContainer" style="display:none; margin-top: 10px;"></div>
            ` : `
                <label for="cfi"><strong>Select a CFI:</strong></label><br>
                <select id="cfi">
                    ${cfiList.map(cfi => `<option value="${cfi.cfi_id}">${cfi.full_name}</option>`).join('')}
                </select>
                <button id="confirmCfiBtn" class="styled-btn">Confirm CFI</button>
            `;

            $('#cfiSelectionContainer').html(cfiDisplayHtml);
        },
        error: function (xhr, status, error) {
            console.error("CFI Fetch Error:", error, xhr.responseText); // ✅ Debugging log
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load CFIs.' });
        }
    });
}

// Fetch aircraft based on selected airport
function fetchAircraft(selectedLocation, selectedLessonType = "flight_lesson") {
    console.log("🚀 Fetching aircraft for airport:", selectedLocation, "Lesson Type:", selectedLessonType); // Debugging log

    if (!selectedLessonType) {
        console.error("❌ ERROR: Lesson Type is undefined! Defaulting to flight_lesson.");
        selectedLessonType = "flight_lesson"; // Default to flight lessons if undefined
    }

    $.ajax({
        url: 'fetch_aircraft.php',
        type: 'GET',
        data: { home_location: selectedLocation, lesson_type: selectedLessonType },
        dataType: 'json',
        success: function (aircraftList) {
            console.log("✅ Aircraft received:", aircraftList); // Debugging log
            let aircraftLabel = selectedLessonType === "simulator_lesson" ? "Select Simulator" : "Select Aircraft";

            let aircraftOptions = `<option value="" disabled selected>✈️ ${aircraftLabel}</option>`;


            if (aircraftList.length === 0) {
                Swal.fire({ icon: 'error', title: 'No Aircraft Available', text: 'No aircraft found for this selection.' });
            }

            aircraftList.forEach(aircraft => {
                aircraftOptions += `<option value="${aircraft.tail_number}" data-type="${aircraft.aircraft_type}">${aircraft.model} (${aircraft.tail_number})</option>`;
            });

            $('#aircraftSelect').html(aircraftOptions).prop('disabled', false);
        },
        error: function () {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load aircraft for this location.' });
        }
    });
}

    // Ensure Aircraft & CFIs load when the event modal opens
    fetchCFIs(homeAirport);
    fetchAircraft(homeAirport, selectedEvent); 

    let lessonTypeDropdown = '';
    let aircraftLabel = selectedEvent === "simulator_lesson" ? "Select Simulator" : "Select Aircraft";

    let aircraftSelection = `
    <div id="aircraftSelectionContainer" style="text-align: center; margin-top: 15px;">
        <label for="aircraftSelect"><strong>${aircraftLabel}:</strong></label>
        <select id="aircraftSelect" class="styled-dropdown">
            <option value="" disabled selected>✈️ ${aircraftLabel}</option>
        </select>
    </div>`;

    if (selectedEvent === "flight_lesson") {
        lessonTypeDropdown = `
            <div id="lessonTypeContainer" style="text-align: center; margin-top: 15px;">
                <label for="lessonType"><strong>Select Lesson Type:</strong></label>
                <select id="lessonType" class="styled-dropdown">
                    <option value="dual_training">✈️ Dual Training (2hr)</option>
                    <option value="dual_cross_country">🌍 Dual Cross Country (4hr)</option>
                    <option value="solo_local">🛩️ Solo - Local (2hr)</option>
                    <option value="solo_cross_country">📍 Solo - Cross Country (4hr)</option>
                </select>
            </div>`;
    }

    // ❌ Hide aircraft selection for Ground Lessons
    if (selectedEvent === "ground_lesson") {
        aircraftSelection = ""; // Removes the aircraft selection field
    }

    let cfiSelection = "";
    if (selectedEvent !== "rental_flight") { 
        cfiSelection = `
            <div id="cfiSelectionContainer" style="text-align: center;">
                <label for="cfi"><strong>Selected CFI:</strong></label><br>
                <span id="selectedCfi">${assignedCfiName} (Primary CFI)</span><br>
                <button id="changeCfiBtn" class="styled-btn">👨‍✈️ Change CFI</button>
                <div id="cfiDropdownContainer" style="display:none; margin-top: 10px;"></div>
            </div>`;
    }

    let selectionHtml = `
        <div style="display: flex; flex-direction: column; align-items: center; gap: 10px;">
            <div style="text-align: center;">
                <label for="homeAirport"><strong>Selected Airport:</strong></label><br>
                <span id="selectedAirport">${homeAirportName}</span><br>
                <button id="changeAirportBtn" class="styled-btn">🛩️ Change Airport</button>
                <div id="airportDropdownContainer" style="display:none; margin-top: 10px;"></div>
            </div>

            ${lessonTypeDropdown} 

            ${aircraftSelection} <!-- This will be empty for Ground Lesson -->

            ${cfiSelection} <!-- ✅ CFI Section only appears when needed -->

            <!-- ✅ Confirm Flight Selection Button -->
            <div style="text-align: center; margin-top: 15px;">
                <button id="confirmFlightSelection" class="styled-btn">✅ Confirm Flight Selection</button>
            </div>
        </div>`;

    Swal.fire({
        icon: 'success',
        title: 'Flight Event Selected',
        html: `You selected: ${$(this).text()}<br><br>${selectionHtml}`,
        showConfirmButton: false
    });
});


// Confirm Flight Selection & Move to Time Slot Selection
$(document).on("click", "#confirmFlightSelection", function () {
    let selectedAirport = $("#selectedAirport").text().trim();
    let selectedLesson = $("#lessonType").val();
    let selectedAircraft = $("#aircraftSelect").val();
    let selectedCFI = $("#selectedCfi").text().trim();
    let studentName = $("#studentName").length ? $("#studentName").text().trim() : sessionStorage.getItem("student_name") || "⚠️ Not Found - Check Selection";
;

    console.log("🔍 Selection Check:");
    console.log("🏠 Airport:", selectedAirport);
    console.log("📖 Lesson Type:", selectedLesson);
    console.log("🛩 Aircraft:", selectedAircraft);
    console.log("👨‍✈️ CFI:", selectedCFI);
    console.log("👤 Student Name:", studentName);

    // ✅ Check if any required field is missing
    if (!selectedAirport || !selectedLesson || !selectedAircraft || !studentName) {
        Swal.fire("Error", "Please complete all selections before continuing.", "error");
        return;
    }

    // ✅ Store selection in sessionStorage for the next step
    sessionStorage.setItem("selected_airport", selectedAirport);
    sessionStorage.setItem("selected_lesson", selectedLesson);
    sessionStorage.setItem("selected_aircraft", selectedAircraft);
    sessionStorage.setItem("selected_cfi", selectedCFI);
    sessionStorage.setItem("student_name", studentName);

    console.log("✅ All selections stored in sessionStorage. Redirecting...");
    
    // Redirect to time slot selection module
    window.location.href = "bookingui.html";
});

function fetchAvailableTimeSlots() {
    let selectedDate = $("#datePicker").val();

    if (!selectedDate) {
        $("#timeSlotsContainer").hide().html("<p>No available slots for this date.</p>");
        return;
    }

    $.ajax({
        url: "https://amelia-i.com/wp-content/plugins/flight-booking-v4/fetch_timeslots.php",
        type: "GET",
        data: { date: selectedDate },
        dataType: "json",
        success: function (response) {
            console.log("✅ API Response:", response); // Debugging Log

            if (!response || response.length === 0) {
                $("#timeSlotsContainer").show().html("<p>No available slots for this date.</p>");
                return;
            }

            let slotsHtml = "<div class='time-slot-list'>";
            response.forEach(slot => {
                let formattedSlot = `${formatTime(slot.start_time)} - ${formatTime(slot.end_time)}`;
                console.log("Adding Slot:", formattedSlot); // ✅ Debugging log
                slotsHtml += `<button class="timeSlotBtn" data-time="${slot.start_time}">${formattedSlot}</button>`;
            });
            slotsHtml += "</div>";

            // ✅ Display container and append slots
            $("#timeSlotsContainer").show();
            $("#timeSlots").html(slotsHtml);
            console.log("✅ Slots Successfully Appended to UI"); // ✅ Confirm slots are added
        },
        error: function () {
            $("#timeSlotsContainer").show().html("<p>Error loading available slots.</p>");
        }
    });
}

// ✅ Handle Booking Submission (New)
$(document).on("click", "#confirmBookingBtn", function () {
    let selectedDate = $("#datePicker").val();
    let selectedTime = $(".timeSlotBtn.selected").data("time");
    let selectedAircraft = $("#aircraftSelect option:selected").val();
    let studentId = sessionStorage.getItem("student_id");

    if (!selectedDate || !selectedTime || !selectedAircraft || !studentId) {
        Swal.fire("Error", "Please select a valid date, time, and aircraft before booking.", "error");
        return;
    }

    console.log("🔍 Data Sent to submit_booking.php:", {
    date: selectedDate,
    start_time: selectedTime,
    aircraft_tail: selectedAircraft,
    student_id: studentId
});

$.ajax({
        url: "submit_booking.php",
        type: "POST",
        data: {
            date: selectedDate,
            start_time: selectedTime,
            aircraft_tail: selectedAircraft,
            student_id: studentId
        },
        dataType: "json",
        success: function (response) {
            if (response.success) {
                Swal.fire("Success!", "Your flight has been booked!", "success").then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire("Error", response.error, "error");
            }
        },
        error: function () {
            Swal.fire("Error", "Failed to process booking request. Try again.", "error");
        }
    });
});

// Helper function to format time properly
function formatTime(timeStr) {
    let [hours, minutes] = timeStr.split(":");
    let amPm = hours >= 12 ? "PM" : "AM";
    hours = hours % 12 || 12; // Convert 24-hour to 12-hour format
    return `${hours}:${minutes} ${amPm}`;
}


    // Listen for Lesson Type Selection to Hide/Show CFI Section
  $(document).on('click', '.event-btn', function () {
    let selectedEvent = $(this).data('event');

    // Show/Hide CFI section dynamically
    if (selectedEvent === "rental_flight") {
        $('#cfiSelectionContainer').hide(); // Hide for Rental Flights
    } else {
        $('#cfiSelectionContainer').show(); // Show for other selections
    }
  
});

$(document).on('click', '#changeAirportBtn', function () {
    $.ajax({
        url: 'fetch_locations.php',
        type: 'GET',
        dataType: 'json',
        success: function (locations) {
            let airportDropdown = `<label for='airportSelect'>Select a New Airport:</label>
                                   <select id='airportSelect'>`;

            locations.forEach(loc => {
                let selected = (loc.airport_code === homeAirport) ? "selected" : "";
                airportDropdown += `<option value="${loc.airport_code}" ${selected}>${loc.name}</option>`;
            });

            airportDropdown += `</select>
                                <button id='confirmAirportBtn'>Confirm Airport</button>`;

            $('#airportDropdownContainer').html(airportDropdown).show();
            $('#changeAirportBtn').hide();
            $('#selectedAirport').hide();
        }
    });
});

// Handle "Change CFI" Button Click
$(document).on('click', '#changeCfiBtn', function () {
    $.ajax({
        url: 'fetch_cfis.php',
        type: 'GET',
        data: { home_airport: homeAirport }, 
        dataType: 'json',
        success: function (response) {
            if (!response || response.length === 0) {
                Swal.fire({ icon: 'error', title: 'No CFIs Available', text: 'No CFIs found for this airport.' });
                return;
            }

            let cfiList = response;  
            console.log("CFIs received:", cfiList); 

            let cfiDropdown = `<label for="cfi">Select a CFI:</label>
                               <select id="cfi">`;

            cfiList.forEach(cfi => {
                let selected = (cfi.cfi_id === assignedCfiId) ? "selected" : "";
                cfiDropdown += `<option value="${cfi.cfi_id}" ${selected}>${cfi.full_name}</option>`;
            });

            cfiDropdown += `</select>
                            <button id="confirmCfiBtn">Confirm CFI</button>`;

            $('#cfiDropdownContainer').html(cfiDropdown).show();
            $('#changeCfiBtn').hide();  
            $('#selectedCfi').hide();
        },
        error: function () {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load CFIs for this airport.' });
        }
    });
});

    // Handle CFI Confirmation
    $(document).on('click', '#confirmCfiBtn', function () {
    let selectedCfi = $('#cfi').val();
    let selectedCfiText = $('#cfi option:selected').text();

    if (!selectedCfi) {
        Swal.fire({
            icon: 'error',
            title: 'CFI Required',
            text: 'Please select a CFI before proceeding.',
        });
        return;
    }

    // ✅ Store selected CFI in session storage
    sessionStorage.setItem("assigned_cfi_id", selectedCfi);
    sessionStorage.setItem("assigned_cfi_name", selectedCfiText);

    console.log(`✅ CFI Confirmed: ${selectedCfi} - ${selectedCfiText}`);

    // ✅ Duplicate the UI handling logic from when the UI first loads
    let cfiDisplayHtml = `
        <label for="cfi"><strong>Selected CFI:</strong></label><br>
        <span id="selectedCfi">${selectedCfiText}</span><br>
        <button id="changeCfiBtn" class="styled-btn">👨‍✈️ Change CFI</button>
        <div id="cfiDropdownContainer" style="display:none; margin-top: 10px;"></div>
    `;

    $('#cfiSelectionContainer').html(cfiDisplayHtml);
});

function showLocationSelection() {
    console.log("🚀 Triggered showLocationSelection()");
    console.log("🏠 Home Airport:", homeAirport, "Name:", homeAirportName);

    let locationHtml = `
        <label for='selectedLocation'>Home Airport:</label>
        <span id='selectedLocation'>${homeAirportName}</span>
        <button id='changeLocationBtn'>Change Location</button>
        <div id='locationDropdownContainer' style='display:none;'></div>
    `;

    $('#eligibility-section').append(locationHtml);
    console.log("✅ Location selection UI added.");
     showLocationSelection();
}
$(document).on('click', '#changeLocationBtn', function () {
    console.log("🛫 Change Location Clicked. Fetching locations...");

    $.ajax({
        url: 'fetch_locations.php',
        type: 'GET',
        dataType: 'json',
        success: function (locations) {
            console.log("🌍 Locations received:", locations);

            if (!locations || locations.length === 0) {
                console.error("❌ No locations returned!");
                Swal.fire({ icon: 'error', title: 'Error', text: 'No locations available.' });
                return;
            }

            let locationDropdown = `<label for='locationSelect'>Select a New Location:</label>
                                    <select id='locationSelect'>`;

            locations.forEach(loc => {
                let selected = (loc.location_id === homeAirport) ? "selected" : "";
                locationDropdown += `<option value="${loc.location_id}" ${selected}>${loc.location_name}</option>`;
            });

            locationDropdown += `</select>
                                <button id='confirmLocationBtn'>Confirm Location</button>`;

            $('#locationDropdownContainer').html(locationDropdown).show();
            $('#changeLocationBtn').hide();
            $('#selectedLocation').hide();
        },
        error: function (xhr, status, error) {
            console.error("❌ AJAX Error:", error);
            Swal.fire({ icon: 'error', title: 'Server Error', text: 'Could not fetch locations.' });
        }
    });
});

$(document).on('click', '#confirmAirportBtn', function () {
    let newAirport = $('#airportSelect').val();
    let newAirportName = $('#airportSelect option:selected').text();

    if (!newAirport) {
        Swal.fire({ icon: 'error', title: 'Location Required', text: 'Please select an airport before proceeding.' });
        return;
    }

    // ✅ Store the new airport in sessionStorage
    sessionStorage.setItem("home_airport", newAirport);
    sessionStorage.setItem("selected_airport", newAirportName);

    // ✅ Retrieve `homeAirport` again to ensure it's available
    let homeAirport = sessionStorage.getItem("home_airport");

    // ✅ Update UI with the new airport name
    $('#selectedAirport').text(newAirportName).show();
    $('#changeAirportBtn').show();
    $('#airportDropdownContainer').hide();

    console.log(`🚀 Airport changed to: ${homeAirport} - Updating Aircraft & CFIs`);

    let selectedLessonType = sessionStorage.getItem("selected_lesson") || "flight_lesson";
    if (selectedLessonType === "simulator_lesson") {
        selectedLessonType = "simulator_lesson"; // ✅ Ensure correct lesson type for simulators
    }

    // ✅ Check if `fetchCFIs()` and `fetchAircraft()` exist before calling
    if (typeof fetchCFIs === "function") {
        console.log(`🚀 Fetching CFIs for: ${homeAirport}`);
        fetchCFIs(homeAirport, true); // ✅ Ensures homeAirport is always defined before calling
    } else {
        console.error("❌ ERROR: fetchCFIs is not defined!");
    }

    if (typeof fetchAircraft === "function") {
        console.log(`🚀 Fetching aircraft for: ${homeAirport}, Lesson Type: ${selectedLessonType}`);
        fetchAircraft(homeAirport, selectedLessonType); // ✅ Force aircraft to update
    } else {
        console.error("❌ ERROR: fetchAircraft is not defined!");
    }

    $('#changeCfiBtn').prop('disabled', false);
    $('#aircraftSelect').html('<option value="" disabled selected>✈️ Select an Aircraft</option>').prop('disabled', false);
});


function filterAircraft(selectedLesson) {
    $('#aircraftSelect option').each(function () {
        let aircraftType = $(this).data('type');

        if (selectedLesson === "solo_local" || selectedLesson === "solo_cross_country") {
            if (aircraftType !== "trainer") {
                $(this).hide();
            } else {
                $(this).show();
            }
        } else {
            $(this).show();
        }
    });

    // Reset selection when filtering
    $('#aircraftSelect').val("").change();
}  // ✅ Properly closed `filterAircraft()`

// Handle aircraft selection change
$(document).on('change', '#aircraftSelect', function () {
    let selectedAircraft = $(this).find(':selected');
    let aircraftType = selectedAircraft.data('type'); // Get aircraft type from the option

    if (aircraftType) {
        $('#aircraftTypeDisplay').text(`Aircraft Type: ${aircraftType}`).show();
    } else {
        $('#aircraftTypeDisplay').hide();
    }
});



// ==================== END OF FILE (Closing Brackets) ====================
  // ✅ Closes the last `.ajax()` call properly