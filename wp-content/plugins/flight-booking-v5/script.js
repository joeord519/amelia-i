document.addEventListener("DOMContentLoaded", function () {
    startBookingProcess();
});

/** ====================================
 *  ✅ Utility Functions (Reusable)
 *  ==================================== */

/** ✅ General Fetch Function */
function fetchData(url, body = null) {
    const options = {
        method: body ? "POST" : "GET",
        headers: { "Content-Type": "application/json" },
    };

    if (body) options.body = JSON.stringify(body);

    return fetch(url, options)
        .then(response => response.json())
        .catch(error => {
            console.error(`❌ Fetch error from ${url}:`, error);
            Swal.fire({ icon: "error", title: "Error", text: `Failed to fetch data from ${url}` })
                .then(() => showFlightTypeSelection());
        });
}

/** ✅ Render Dropdown Options */
function renderDropdown(options, targetElementId) {
    const dropdown = document.getElementById(targetElementId);
    if (!dropdown) return console.error(`❌ Dropdown ${targetElementId} not found.`);
    
    dropdown.innerHTML = options.map(option =>
        `<option value="${option.value}">${option.text}</option>`
    ).join("");
}

/** ✅ Format Phone Number */
function formatPhoneNumber(input) {
    let numbers = input.replace(/\D/g, "").substring(0, 10);
    if (numbers.length >= 7) {
        return `(${numbers.substring(0, 3)}) ${numbers.substring(3, 6)}-${numbers.substring(6)}`;
    } else if (numbers.length >= 4) {
        return `(${numbers.substring(0, 3)}) ${numbers.substring(3)}`;
    } else if (numbers.length > 0) {
        return `(${numbers}`;
    }
    return "";
}

/** ====================================
 *  ✅ Step 1: Student Verification
 *  ==================================== */

function startBookingProcess() {
    Swal.fire({
        title: "Flight Booking Access Verification",
        html: `
            <label for='swal-phone'>Phone Number:</label>
            <input type='text' id='swal-phone' class='swal2-input' placeholder='(XXX) XXX-XXXX'>
            
            <label for='swal-security'>Security Word:</label>
            <input type='password' id='swal-security' class='swal2-input' placeholder='Enter Security Word'>
        `,
        confirmButtonText: "Verify",
        allowOutsideClick: false,
        didOpen: () => {
            let phoneInput = document.getElementById("swal-phone");
            phoneInput.addEventListener("input", function () {
                let formatted = formatPhoneNumber(this.value);
                if (this.value !== formatted) this.value = formatted;
            });
        },
        preConfirm: () => {
            const phone = document.getElementById("swal-phone").value.trim();
            const securityWord = document.getElementById("swal-security").value.trim();

            if (!phone || !securityWord) {
                Swal.showValidationMessage("⚠️ Please enter both your phone number and security word.");
                return false;
            }

            return { phone: phone.replace(/\D/g, ""), securityWord };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            verifyStudent(result.value.phone, result.value.securityWord);
        }
    });
}

/** ✅ Step 2: Verify Student */
function verifyStudent(phone, securityWord) {
    let formattedPhone = formatPhoneNumber(phone);

    console.log("🚀 Sending verification request:", { phone: formattedPhone, securityWord });

    fetchData("verify_student.php", { phone: formattedPhone, securityWord })
        .then(data => {
            console.log("✅ Full Server Response:", data); // ✅ Debugging - Log full response

            if (data.success) {
                // ✅ Ensure `assigned_cfi_id` is stored
                data.assigned_cfi_id = data.assigned_cfi_id || "No CFI Assigned";
                sessionStorage.setItem("studentData", JSON.stringify(data));

                console.log("✅ Assigned CFI ID Stored:", data.assigned_cfi_id); // ✅ Debugging

                Swal.fire({
                    title: "Flight Eligibility Check",
                    html: `
                        <p><strong>${data.name}, here is your status:</strong></p>
                        <p>✅ You are eligible to book flights!</p>
                        <p><strong>Aircraft Hours Remaining:</strong> ${data.aircraft_hours}</p>
                        <p><strong>Instructor Hours Remaining:</strong> ${data.instructor_hours}</p>
                    `,
                    confirmButtonText: "Continue",
                    showConfirmButton: true,
                    timer: 3000,
                    timerProgressBar: true,
                    willClose: () => showFlightTypeSelection()
                }).then((result) => {
                    if (result.isConfirmed) showFlightTypeSelection();
                });
            } else {
                console.error("❌ Verification failed. Server message:", data.message);
                Swal.fire({ icon: "error", title: "Verification Failed", text: data.message });
            }
        });
}

/** ✅ Immediately Load Flight Selection Instead of Showing Buttons */
function showFlightTypeSelection() {
    showFlightLessonSelection(); // ✅ Skip the button selection screen and go straight to flight selection
}

/** ✅ Show Flight Selection & Guide Users Through the Flow */
function showFlightLessonSelection() {
    let studentData = JSON.parse(sessionStorage.getItem("studentData"));
    let selectedLocation = studentData?.home_airport || "Unknown";

    if (!selectedLocation || selectedLocation === "Unknown") {
        console.error("❌ Error: No valid location available. Cannot proceed.");
        return;
    }

    Swal.fire({
        title: "Flight Selections",
        html: `
            <div class="selection-container">

                <!-- ✅ Step 1: Flight Type (Always Visible First) -->
                <label for="swal-flight-type">Choose Flight Type:</label>
                <select id="swal-flight-type" class="swal2-input" onchange="updateFlightType()">
                    <option value="" disabled selected>Select Flight Type</option>
                    <option value="flight">Flight Lesson</option>
                    <option value="ground">Ground Lesson</option>
                    <option value="simulator">Simulator Lesson</option>
                    <option value="rental">Rental Flight</option>
                    <option value="review">Flight Review</option>
                </select>

                <!-- ✅ Step 2: Airfield Selection (Hidden Until Flight Type is Selected) -->
                <div id="airfield-section" style="display: none;">
                    <p><strong>Current Location:</strong> <span id="selected-location">${selectedLocation}</span></p>
                    <div class="button-container">
                        <button id="change-location-btn" class="swal2-confirm btn-wide" onclick="toggleLocationChange()">Change Location</button>
                    </div>
                    <div id="location-dropdown" style="display: none;">
                        <select id="swal-location" class="swal2-input"></select>
                        <div class="button-container">
                            <button class="swal2-confirm btn-wide" onclick="confirmLocationSelection()">Confirm Airfield</button>
                        </div>
                    </div>
                </div>

                <!-- ✅ Step 3: Lesson Type (Hidden Until Airfield is Confirmed) -->
                <div id="lesson-type-section" style="display: none;">
                    <label for="swal-lesson-type">Choose Lesson Type:</label>
                    <select id="swal-lesson-type" class="swal2-input" onchange="updateFlightType()">
                        <option value="" disabled selected>Select Lesson Type</option>
                        <option value="Dual Training">Dual Training</option>
                        <option value="Dual Cross Country">Dual Cross Country</option>
                        <option value="Solo - Local">Solo Flight - Local</option>
                        <option value="Solo - Cross Country">Solo Flight - Cross Country</option>
                    </select>
                </div>

                <!-- ✅ Aircraft Selection -->
                <div id="aircraft-section" style="display: none;">
                    <label>Choose Your Aircraft:</label>
                    <p id="aircraft-display" style="font-weight: bold; display: none;"></p>
                    <select id="swal-aircraft" class="swal2-input">
                        <option value="" disabled selected>Select Aircraft</option>
                    </select>
                    <div class="button-container">
                        <button id="confirm-aircraft-btn" class="swal2-confirm btn-wide" onclick="confirmAircraftSelection()">Confirm Aircraft</button>
                    </div>
                </div>

                <!-- ✅ Simulator Selection -->
                <div id="simulator-section" style="display: none;">
                    <label>Choose Your Simulator:</label>
                    <select id="swal-simulator" class="swal2-input">
                        <option value="" disabled selected>Select Simulator</option>
                    </select>
                </div>

                <!-- ✅ CFI Selection -->
                <div id="cfi-selection" class="selection-container" style="display: none;">
                    <label>Your CFI:</label>
                    <p id="assigned-cfi">${studentData.assigned_cfi_name || "None"}</p>
                    <div class="button-container">
                        <button id="change-cfi-btn" class="swal2-confirm btn-wide" onclick="showCFIDropdown()">Change CFI</button>
                    </div>
                </div>

                <!-- ✅ Final Confirmation Button (Hidden Until All Selections Are Made) -->
                <div class="button-container">
                    <button id="confirm-flight-time-btn" class="swal2-confirm btn-wide" style="display: none;" onclick="confirmFlightTime()">Pick Your Flight Time</button>
                </div>

                <!-- ✅ Final Confirmation Button (Hidden Until All Selections Are Made) -->
                <div class="button-container">
                    <button id="confirm-selections-btn" class="swal2-confirm btn-wide" style="display: none;" onclick="confirmFlightTypeSelection()">Pick Your Flight Time</button>
                </div>

            </div>
        `,
        didOpen: () => {
            fetchAvailableLocations();
            fetchAvailableAircraft(selectedLocation);
            checkForAssignedCFI(selectedLocation);
        },
        showConfirmButton: false
    });
}

/** ✅ Dynamically Show/Hide Fields Based on Flight Type */
function updateFlightType() {
    let flightType = document.getElementById("swal-flight-type").value;
    
    let airfieldSection = document.getElementById("airfield-section");
    let lessonTypeSection = document.getElementById("lesson-type-section");
    let aircraftSection = document.getElementById("aircraft-section");
    let simulatorSection = document.getElementById("simulator-section");
    let cfiSelection = document.getElementById("cfi-selection");
    let confirmFlightTimeBtn = document.getElementById("confirm-flight-time-btn");

    // ✅ Step 1: Show Airfield Selection Only After Flight Type is Picked
    if (flightType) {
        airfieldSection.style.display = "block";
    } else {
        airfieldSection.style.display = "none";
        lessonTypeSection.style.display = "none";
        aircraftSection.style.display = "none";
        simulatorSection.style.display = "none";
        cfiSelection.style.display = "none";
        confirmFlightTimeBtn.style.display = "none";
        return;
    }

    // ✅ Step 2: If Ground Lesson, Skip Lesson Type Selection
    if (flightType === "ground") {
        lessonTypeSection.style.display = "none"; // Hide Lesson Type for Ground Lessons
        cfiSelection.style.display = "block"; // CFI selection should still show
        confirmFlightTimeBtn.style.display = "block"; // Immediately show Pick Your Flight Time button
        return;
    } else if (flightType === "simulator") {
        lessonTypeSection.style.display = "none"; // ✅ Hide Lesson Type for Simulators
        simulatorSection.style.display = "block"; // ✅ Show Simulator Selection
        confirmFlightTimeBtn.style.display = "none"; // ✅ Hide Pick Your Flight Time until confirmed
    } else {
        lessonTypeSection.style.display = "block";
    }

    // ✅ Step 3: Show Aircraft or Simulator Selection Only After Lesson Type is Picked
    let lessonTypeDropdown = document.getElementById("swal-lesson-type");
    let lessonType = lessonTypeDropdown ? lessonTypeDropdown.value : "";

    if (lessonType || flightType === "simulator") {
        if (["flight", "rental", "review"].includes(flightType)) {
            aircraftSection.style.display = "block";
        } else {
            aircraftSection.style.display = "none";
        }

        if (flightType === "simulator") {
            simulatorSection.style.display = "block";
            fetchAvailableSimulators(document.getElementById("selected-location").innerText);
        } else {
            simulatorSection.style.display = "none";
        }
    } else {
        aircraftSection.style.display = "none";
        simulatorSection.style.display = "none";
        cfiSelection.style.display = "none";
        confirmFlightTimeBtn.style.display = "none";
    }

    // ✅ Step 4: Show CFI Section for Dual Training, Simulators, & Other Flights That Require It
    if (["ground", "simulator", "review"].includes(flightType) || (flightType === "flight" && lessonType.includes("Dual"))) {
        cfiSelection.style.display = "block";
    } else {
        cfiSelection.style.display = "none";
    }

    // ✅ Step 5: Show "Pick Your Flight Time" Button When All Selections Are Made
    let aircraftConfirmed = document.getElementById("aircraft-display")?.innerText.trim() !== "";
    let simulatorConfirmed = document.getElementById("simulator-display")?.innerText.trim() !== "";
    let cfiConfirmed = document.getElementById("assigned-cfi")?.innerText.trim() !== "" && cfiSelection.style.display === "block";

    // ✅ Ensure Pick Your Flight Time appears only after confirming an Aircraft OR Simulator & CFI
    if (
        flightType &&
        airfieldSection.style.display === "block" &&
        (flightType === "ground" || 
        (lessonType && (aircraftConfirmed || simulatorConfirmed) && (lessonType.includes("Solo") || cfiConfirmed)) || 
        (flightType === "simulator" && simulatorConfirmed && cfiConfirmed))
    ) {
        confirmFlightTimeBtn.style.display = "block";
    } else {
        confirmFlightTimeBtn.style.display = "none";
    }
}

/** ✅ Confirm Simulator Selection */
function confirmSimulatorSelection() {
    let simulatorDropdown = document.getElementById("swal-simulator");
    let simulatorDisplay = document.getElementById("simulator-display");
    let confirmSimulatorBtn = document.getElementById("confirm-simulator-btn");
    let changeSimulatorBtn = document.getElementById("change-simulator-btn");
    let confirmFlightTimeBtn = document.getElementById("confirm-flight-time-btn");

    if (!simulatorDropdown || !simulatorDisplay || !confirmSimulatorBtn) {
        console.error("❌ Simulator selection elements not found.");
        return;
    }

    let selectedSimulator = simulatorDropdown.value;
    let selectedSimulatorText = simulatorDropdown.options[simulatorDropdown.selectedIndex].text;

    if (!selectedSimulator) {
        Swal.fire({ icon: "error", title: "Error", text: "Please select a simulator before confirming." });
        return;
    }

    // ✅ Store the selection and update UI
    sessionStorage.setItem("selectedSimulator", selectedSimulator);
    simulatorDisplay.innerText = `Selected Simulator: ${selectedSimulatorText}`;
    simulatorDisplay.style.display = "block";

    // ✅ Hide dropdown and confirm button, show "Change Simulator" button
    simulatorDropdown.style.display = "none";
    confirmSimulatorBtn.style.display = "none";

    // ✅ Ensure "Change Simulator" button appears below the selected simulator
    if (!changeSimulatorBtn) {
        changeSimulatorBtn = document.createElement("button");
        changeSimulatorBtn.id = "change-simulator-btn";
        changeSimulatorBtn.classList.add("swal2-confirm", "btn-wide");
        changeSimulatorBtn.innerText = "Change Simulator";
        changeSimulatorBtn.onclick = showSimulatorDropdown;

        simulatorDisplay.insertAdjacentElement("afterend", changeSimulatorBtn);
    } else {
        changeSimulatorBtn.style.display = "block";
    }

    // ✅ Ensure "Pick Your Flight Time" button updates after confirming both simulator and CFI
    updateFlightType();
}

/** ✅ Show Simulator Dropdown for Reselection */
function showSimulatorDropdown() {
    let simulatorDropdown = document.getElementById("swal-simulator");
    let simulatorDisplay = document.getElementById("simulator-display");
    let confirmSimulatorBtn = document.getElementById("confirm-simulator-btn");
    let changeSimulatorBtn = document.getElementById("change-simulator-btn");

    if (!simulatorDropdown || !simulatorDisplay || !confirmSimulatorBtn || !changeSimulatorBtn) {
        console.error("❌ Simulator selection elements not found.");
        return;
    }

    // ✅ Show the dropdown and confirm button again
    simulatorDropdown.style.display = "block";
    confirmSimulatorBtn.style.display = "block";

    // ✅ Hide the selected simulator text and "Change Simulator" button
    simulatorDisplay.style.display = "none";
    changeSimulatorBtn.style.display = "none";
}

/** ✅ Fetch Available Simulators Based on Location */
function fetchAvailableSimulators(location) {
    let simulatorDropdown = document.getElementById("swal-simulator");

    if (!simulatorDropdown) {
        console.error("❌ Simulator dropdown not found.");
        return;
    }

    // ✅ Reset dropdown before fetching new options
    simulatorDropdown.innerHTML = `<option value="" disabled selected>Loading Simulators...</option>`;

    // ✅ Fetch simulators for the selected location
    fetchData(`fetch_simulators.php?location=${location}`).then(data => {
        if (!data || !data.simulators || !Array.isArray(data.simulators)) {
            console.error("❌ Error: Simulator data is invalid", data);
            return;
        }

        let simulatorOptions = data.simulators.map(sim => ({
            value: sim.id, 
            text: `${sim.name} - ${sim.model}`
        }));

        renderDropdown(simulatorOptions, "swal-simulator");

        // ✅ Reset the dropdown text
        simulatorDropdown.innerHTML = `<option value="" disabled selected>Select Simulator</option>` +
            simulatorOptions.map(opt => `<option value="${opt.value}">${opt.text}</option>`).join("");
    });
}

/** ✅ Handles Flight Type Selection */
function selectFlightType(type) {
    console.log("Selected Flight Type:", type);

    if (type === "flight") {
        showFlightLessonSelection();
    } else if (type === "ground") {
        showGroundLessonSelection();
    } else if (type === "simulator") {
        showSimulatorLessonSelection();
    } else if (type === "rental") {
        showRentalFlightSelection();
    } else if (type === "review") {
        showFlightReviewSelection();
    }
}

/** ✅ Step 4: Fetch Locations */
function fetchAvailableLocations() {
    let studentData = JSON.parse(sessionStorage.getItem("studentData"));
    let selectedLocation = studentData?.home_airport || "Unknown";

    if (!selectedLocation || selectedLocation === "Unknown") {
        console.error("❌ Error: No valid location available.");
        return;
    }

    fetchData("fetch_locations.php").then(data => {
        if (!data || !data.locations || !Array.isArray(data.locations)) {
            console.error("❌ Error: Locations data is invalid", data);
            return;
        }

        let locationOptions = data.locations.map(loc => ({ value: loc.airport_code, text: loc.name }));
        renderDropdown(locationOptions, "swal-location");
    });
}


/** ✅ Show Location Dropdown */
function showLocationDropdown() {
    let changeLocationBtn = document.getElementById("change-location-btn");
    let locationDropdown = document.getElementById("location-dropdown");

    if (!changeLocationBtn || !locationDropdown) {
        console.error("❌ Error: Location dropdown elements not found.");
        return;
    }

    changeLocationBtn.style.display = "none"; // ✅ Hide "Change Location" button
    locationDropdown.style.display = "block"; // ✅ Show dropdown & confirm button
}

/** ✅ Toggle Location Change (Fix Double Button Issue) */
function toggleLocationChange() {
    let locationDropdown = document.getElementById("location-dropdown");
    let changeLocationBtn = document.getElementById("change-location-btn");

    if (!locationDropdown || !changeLocationBtn) {
        console.error("❌ Missing location elements.");
        return;
    }

    // ✅ If dropdown is currently hidden, show it and switch button to "Confirm Airfield"
    if (locationDropdown.style.display === "none") {
        locationDropdown.style.display = "block";
        changeLocationBtn.style.display = "none"; // ✅ Hide "Change Location" button
    } else {
        locationDropdown.style.display = "none";
        changeLocationBtn.style.display = "block"; // ✅ Show "Change Location" button
    }
}

/** ✅ Confirm New Location Selection & Ensure CFI & UI Updates Work Correctly */
function confirmLocationSelection() {
    let newLocation = document.getElementById("swal-location").value;
    let selectedLocationText = document.getElementById("selected-location");
    let locationDropdown = document.getElementById("location-dropdown");
    let changeLocationBtn = document.getElementById("change-location-btn");
    let lessonTypeSection = document.getElementById("lesson-type-section");
    let cfiSelection = document.getElementById("cfi-selection");
    let assignedCFIText = document.getElementById("assigned-cfi");
    let cfiDropdown = document.getElementById("swal-cfi");
    let changeCFIBtn = document.getElementById("change-cfi-btn");
    let confirmFlightTimeBtn = document.getElementById("confirm-flight-time-btn");
    let aircraftDropdown = document.getElementById("swal-aircraft");
    let aircraftDisplay = document.getElementById("aircraft-display");

    if (!newLocation) {
        Swal.fire({ icon: "error", title: "Error", text: "Please select a location before confirming." });
        return;
    }

    // ✅ Update displayed location
    selectedLocationText.innerText = newLocation;

    // ✅ Hide location dropdown after confirmation
    locationDropdown.style.display = "none";

    // ✅ Toggle button text & functionality back to "Change Location"
    changeLocationBtn.style.display = "block";
    changeLocationBtn.innerText = "Change Location";
    changeLocationBtn.onclick = toggleLocationChange;

    let flightType = document.getElementById("swal-flight-type")?.value || "";

    // ✅ Ensure Ground Lessons Skip Lesson Type Selection & Reset CFI
    if (flightType === "ground") {
        lessonTypeSection.style.display = "none"; // Hide Lesson Type for Ground Lessons
        assignedCFIText.innerText = "None"; // ✅ Reset CFI selection
        confirmFlightTimeBtn.style.display = "none"; // Hide "Pick Your Flight Time" until a new CFI is selected
    } else {
        lessonTypeSection.style.display = "block"; // Show for other lessons
    }

    // ✅ Reset Aircraft Selection UI for all flights EXCEPT Ground Lessons
    if (["flight", "rental", "review", "simulator"].includes(flightType)) {
        if (aircraftDropdown) {
            aircraftDropdown.innerHTML = `<option value="" disabled selected>Select Aircraft</option>`;
            aircraftDropdown.style.display = "block"; // Show dropdown again
        }
        if (aircraftDisplay) {
            aircraftDisplay.innerText = "";
            aircraftDisplay.style.display = "none";
        }
    }

    // ✅ Fetch new CFIs (Resets selection only for applicable flight types)
    if (["flight", "ground", "simulator", "review"].includes(flightType)) {
        fetchAvailableCFIs(newLocation);

        // ✅ Keep default CFI but hide dropdown until "Change CFI" is clicked
        assignedCFIText.style.display = "block";
        if (cfiDropdown) cfiDropdown.style.display = "none"; // ✅ Hide dropdown
        if (changeCFIBtn) {
            changeCFIBtn.style.display = "block"; // ✅ Show button below CFI name
            assignedCFIText.insertAdjacentElement("afterend", changeCFIBtn); // ✅ Ensure correct order
        }
    }

    // ✅ Fetch new aircraft for updated location (if applicable)
    if (["flight", "rental", "review"].includes(flightType)) {
        fetchAvailableAircraft(newLocation, false);
    }
    if (flightType === "simulator") {
        fetchAvailableAircraft(newLocation, true);
    }
}

/** ✅ Step 5: Fetch Aircraft (Exclude Simulators) */
function fetchAvailableAircraft(location) {
    if (!location || location === "Unknown") {
        console.error("❌ Error: No valid location available for aircraft fetch.");
        return;
    }

    fetchData(`fetch_aircraft.php?location=${location}`).then(data => {
        if (!data || !data.aircraft || !Array.isArray(data.aircraft)) {
            console.error("❌ Error: Aircraft data is invalid", data);
            return;
        }

        // ✅ Filter out aircraft with type "Simulator"
        let filteredAircraft = data.aircraft.filter(ac => ac.aircraft_type !== "Simulator");

        if (filteredAircraft.length === 0) {
            console.warn("⚠️ No available aircraft found that are not simulators.");
        }

        let aircraftOptions = filteredAircraft.map(ac => ({
            value: ac.tail_number, 
            text: `${ac.tail_number} - ${ac.model}`
        }));

        renderDropdown(aircraftOptions, "swal-aircraft");
    });
}

/** ✅ Fetch Assigned CFI & Display Name */
function checkForAssignedCFI(location) {
    let studentData = JSON.parse(sessionStorage.getItem("studentData"));
    let assignedCFIId = studentData.assigned_cfi_id;
    let assignedCFIText = document.getElementById("assigned-cfi");

    if (!assignedCFIId) {
        assignedCFIText.innerText = "None";
        return;
    }

    fetchData(`fetch_cfis.php?location=${location}`).then(data => {
        if (!data || !data.cfis || !Array.isArray(data.cfis)) {
            console.error("❌ Error: CFI data is invalid", data);
            assignedCFIText.innerText = "None";
            return;
        }

        // ✅ Keep previous CFI selection until "Change CFI" is clicked
        let assignedCFI = data.cfis.find(cfi => cfi.cfi_id == assignedCFIId);
        if (assignedCFI) {
            assignedCFIText.innerText = `${assignedCFI.first_name} ${assignedCFI.last_name}`;
        }
    });
}


/** ✅ Fetch Available CFIs Based on Location (Fix Missing Dropdown Issue) */
function fetchAvailableCFIs(location) {
    let cfiDropdown = document.getElementById("swal-cfi");
    let cfiSelection = document.getElementById("cfi-selection");

    if (!cfiSelection) {
        console.error("❌ CFI selection container not found.");
        return;
    }

    // ✅ Ensure CFI dropdown exists before fetching
    if (!cfiDropdown) {
        console.warn("⚠️ CFI Dropdown not found, regenerating...");
        
        // ✅ Create CFI dropdown dynamically if it doesn't exist
        let newDropdown = document.createElement("select");
        newDropdown.id = "swal-cfi";
        newDropdown.classList.add("swal2-input");

        let defaultOption = document.createElement("option");
        defaultOption.value = "";
        defaultOption.selected = true;
        defaultOption.disabled = true;
        defaultOption.innerText = "Select a CFI";

        newDropdown.appendChild(defaultOption);
        cfiSelection.appendChild(newDropdown);
        cfiDropdown = newDropdown;
    }

    fetchData(`fetch_cfis.php?location=${location}`).then(data => {
        if (!data || !data.cfis || !Array.isArray(data.cfis)) {
            console.error("❌ Error: CFI data is invalid", data);
            return;
        }

        let cfiOptions = data.cfis.map(cfi => ({
            value: cfi.cfi_id,
            text: `${cfi.first_name} ${cfi.last_name}`
        }));

        renderDropdown(cfiOptions, "swal-cfi");
    });
}

/** ✅ Show/Hide CFI Selection Based on Lesson Type & Load Assigned CFI */
function updateCFIVisibility() {
    let lessonType = document.getElementById("swal-lesson-type").value;
    let cfiSelection = document.getElementById("cfi-selection");
    let selectedLocation = document.getElementById("selected-location").innerText;

    if (!cfiSelection) return;

    if (lessonType.includes("Dual")) {
        cfiSelection.style.display = "block";
        checkForAssignedCFI(selectedLocation); // ✅ Ensure CFI loads when Dual is selected
    } else {
        cfiSelection.style.display = "none";
    }
}

/** ✅ Show CFI Dropdown & Ensure Button Stays Below */
function showCFIDropdown() {
    let selectedLocation = document.getElementById("selected-location").innerText;
    let cfiDropdown = document.getElementById("swal-cfi");
    let cfiSelection = document.getElementById("cfi-selection");
    let changeCFIBtn = document.getElementById("change-cfi-btn");

    if (!cfiSelection) {
        console.error("❌ CFI selection container not found.");
        return;
    }

    // ✅ If dropdown doesn't exist, create it
    if (!cfiDropdown) {
        console.warn("⚠️ CFI Dropdown not found, regenerating...");
        
        // ✅ Create dropdown dynamically
        let newDropdown = document.createElement("select");
        newDropdown.id = "swal-cfi";
        newDropdown.classList.add("swal2-input");

        let defaultOption = document.createElement("option");
        defaultOption.value = "";
        defaultOption.selected = true;
        defaultOption.disabled = true;
        defaultOption.innerText = "Select a CFI";

        newDropdown.appendChild(defaultOption);
        cfiSelection.appendChild(newDropdown);
        cfiDropdown = newDropdown;
    }

    // ✅ Fetch available CFIs
    fetchAvailableCFIs(selectedLocation);

    // ✅ Ensure dropdown appears BELOW the button
    changeCFIBtn.insertAdjacentElement("afterend", cfiDropdown);
    cfiDropdown.style.display = "block";

    // ✅ Change button to "Confirm CFI" and keep it BELOW the dropdown
    changeCFIBtn.innerText = "Confirm CFI";
    changeCFIBtn.onclick = confirmCFISelection;
    cfiDropdown.insertAdjacentElement("afterend", changeCFIBtn);
}


/** ✅ Confirm CFI Selection & Ensure "Pick Your Flight Time" Button Updates */
function confirmCFISelection() {
    let cfiDropdown = document.getElementById("swal-cfi");
    let assignedCFIText = document.getElementById("assigned-cfi");
    let changeCFIBtn = document.getElementById("change-cfi-btn");
    let confirmFlightTimeBtn = document.getElementById("confirm-flight-time-btn");

    if (!cfiDropdown || !assignedCFIText || !changeCFIBtn) {
        console.error("❌ CFI selection elements not found.");
        return;
    }

    let selectedCFI = cfiDropdown.value;
    let selectedCFIText = cfiDropdown.options[cfiDropdown.selectedIndex].text;

    if (!selectedCFI) {
        Swal.fire({ icon: "error", title: "Error", text: "Please select a CFI before confirming." });
        return;
    }

    // ✅ Store selection and update UI
    sessionStorage.setItem("selectedCFI", selectedCFI);
    assignedCFIText.innerText = selectedCFIText;

    // ✅ Hide dropdown and revert button
    cfiDropdown.style.display = "none";
    changeCFIBtn.innerText = "Change CFI";
    changeCFIBtn.onclick = showCFIDropdown;

    // ✅ Move the button BELOW the updated CFI name
    assignedCFIText.insertAdjacentElement("afterend", changeCFIBtn);

    // ✅ Ensure "Pick Your Flight Time" reappears after CFI is selected
    let flightType = document.getElementById("swal-flight-type")?.value || "";
    if (flightType === "ground") {
        confirmFlightTimeBtn.style.display = "block";
    }
}

/** ✅ Confirm Flight Type Selection & Store Appointment Length */
function confirmFlightTypeSelection() {
    let flightType = document.getElementById("swal-flight-type").value;
    let lessonType = document.getElementById("swal-lesson-type")?.value || "";
    let aircraft = document.getElementById("swal-aircraft")?.value || "";
    let simulator = document.getElementById("swal-simulator")?.value || "";
    let cfi = document.getElementById("assigned-cfi")?.innerText || "N/A";

    if (!flightType) {
        Swal.fire({ icon: "error", title: "Error", text: "Please select a flight type." });
        return;
    }

    let appointmentLengthCFI = (["Dual Training", "Solo Flight - Local", "Simulator Appointment", "Rental - Local", "Ground Lesson"].includes(lessonType)) ? 2 : 4;
    let appointmentLengthAircraft = (flightType === "review") ? 2 : appointmentLengthCFI;

    let flightLessonData = {
        location: document.getElementById("selected-location").innerText,
        lessonType: lessonType,
        flightType: flightType,
        aircraft: aircraft,
        simulator: simulator,
        cfi: lessonType.includes("Dual") ? cfi : "N/A",
        appointmentLengthCFI: appointmentLengthCFI,
        appointmentLengthAircraft: appointmentLengthAircraft
    };

    sessionStorage.setItem("flightLessonData", JSON.stringify(flightLessonData));
    console.log("🚀 Flight Lesson Data Stored:", flightLessonData);
    showDateSelection();
}

/** ✅ Confirm Aircraft Selection & Ensure "Change Aircraft" Button Works */
function confirmAircraftSelection() {
    let aircraftDropdown = document.getElementById("swal-aircraft");
    let aircraftDisplay = document.getElementById("aircraft-display");
    let confirmAircraftBtn = document.getElementById("confirm-aircraft-btn");
    let changeAircraftBtn = document.getElementById("change-aircraft-btn");

    if (!aircraftDropdown || !aircraftDisplay || !confirmAircraftBtn) {
        console.error("❌ Aircraft selection elements not found.");
        return;
    }

    let selectedAircraft = aircraftDropdown.value;
    let selectedAircraftText = aircraftDropdown.options[aircraftDropdown.selectedIndex].text;

    if (!selectedAircraft) {
        Swal.fire({ icon: "error", title: "Error", text: "Please select an aircraft before confirming." });
        return;
    }

    // ✅ Store the selection and update UI
    sessionStorage.setItem("selectedAircraft", selectedAircraft);
    aircraftDisplay.innerText = `Selected Aircraft: ${selectedAircraftText}`;
    aircraftDisplay.style.display = "block";

    // ✅ Hide dropdown and confirm button, show "Change Aircraft" button
    aircraftDropdown.style.display = "none";
    confirmAircraftBtn.style.display = "none";

    // ✅ Ensure "Change Aircraft" button appears
    if (!changeAircraftBtn) {
        changeAircraftBtn = document.createElement("button");
        changeAircraftBtn.id = "change-aircraft-btn";
        changeAircraftBtn.classList.add("swal2-confirm", "btn-wide");
        changeAircraftBtn.innerText = "Change Aircraft";
        changeAircraftBtn.onclick = showAircraftDropdown;

        confirmAircraftBtn.parentNode.appendChild(changeAircraftBtn);
    } else {
        changeAircraftBtn.style.display = "block";
    }

    // ✅ Ensure "Pick Your Flight Time" button updates immediately
    updateFlightType();
}

/** ✅ Show Aircraft Dropdown for Reselection */
function showAircraftDropdown() {
    let aircraftDropdown = document.getElementById("swal-aircraft");
    let aircraftDisplay = document.getElementById("aircraft-display");
    let confirmAircraftBtn = document.getElementById("confirm-aircraft-btn");
    let changeAircraftBtn = document.getElementById("change-aircraft-btn");

    if (!aircraftDropdown || !aircraftDisplay || !confirmAircraftBtn || !changeAircraftBtn) {
        console.error("❌ Aircraft selection elements not found.");
        return;
    }

    // ✅ Show the dropdown and confirm button again
    aircraftDropdown.style.display = "block";
    confirmAircraftBtn.style.display = "block";

    // ✅ Hide the selected aircraft text and "Change Aircraft" button
    aircraftDisplay.style.display = "none";
    changeAircraftBtn.style.display = "none";
}

/** ✅ Confirm Flight Lesson Selection */
function confirmFlightLessonSelection() {
    let selectedLessonType = document.getElementById("swal-lesson-type").value;
    let selectedAircraft = document.getElementById("swal-aircraft").value;
    let selectedCFI = document.getElementById("assigned-cfi").innerText;

    if (!selectedLessonType || !selectedAircraft) {
        Swal.fire({ icon: "error", title: "Error", text: "Please complete all required selections." });
        return;
    }

    let appointmentLength = selectedLessonType.includes("Cross Country") ? 4 : 2;

    let flightLessonData = {
        location: document.getElementById("selected-location").innerText,
        lessonType: selectedLessonType,
        aircraft: selectedAircraft,
        cfi: selectedLessonType.includes("Dual") ? selectedCFI : "N/A",
        appointmentLength: appointmentLength
    };

    sessionStorage.setItem("flightLessonData", JSON.stringify(flightLessonData));
    showDateSelection(); // ✅ Move to next step
}
