/** 
 * global.js - Contains shared functions used across all lesson types
 */

// ✅ Fetch Data Utility Function (Used Everywhere)
function fetchData(endpoint, body = null) {
    const url = API_ENDPOINTS[endpoint] || endpoint; 
    const options = {
        method: body ? "POST" : "GET",
        headers: { "Content-Type": "application/json" },
    };

    if (body) {
        options.body = JSON.stringify(body);
        console.log("📡 Sending API Request:", endpoint, options);
    }

    return fetch(url, options)
        .then(response => response.json())
        .then(data => {
            console.log("📡 API Response from:", endpoint, data);
            if (!data.success) {
                console.error(`❌ API error from ${endpoint}:`, data);
                Swal.fire({ icon: "error", title: "API Error", text: data.message || "Unknown API error." });
            }
            return data;
        })
        .catch(error => {
            console.error(`❌ Fetch error from ${endpoint}:`, error);
            Swal.fire({ icon: "error", title: "Error", text: `Failed to fetch data from ${url}` });
        });
}

/** ✅ Format Phone Number Input as (###) ###-#### */
function formatPhoneNumber(input) {
    let numbers = input.replace(/\D/g, "").substring(0, 10);
    let formattedNumber = "";

    if (numbers.length > 6) {
        formattedNumber = `(${numbers.substring(0, 3)}) ${numbers.substring(3, 6)}-${numbers.substring(6)}`;
    } else if (numbers.length > 3) {
        formattedNumber = `(${numbers.substring(0, 3)}) ${numbers.substring(3)}`;
    } else if (numbers.length > 0) {
        formattedNumber = `(${numbers}`;
    }

    return formattedNumber;
}

// ✅ Render Dropdown Options
function renderDropdown(options, targetElementId) {
    const dropdown = document.getElementById(targetElementId);
    if (!dropdown) return console.error(`❌ Dropdown ${targetElementId} not found.`);
    
    dropdown.innerHTML = options.map(option =>
        `<option value="${option.value}">${option.text}</option>`
    ).join("");
}

// ✅ Save Session Data
function saveToSession(key, data) {
    sessionStorage.setItem(key, JSON.stringify(data));
}

// ✅ Retrieve Session Data
function getSessionData(key) {
    let data = sessionStorage.getItem(key);
    return data ? JSON.parse(data) : null;
}

/** ✅ Fetch Available Locations */
function fetchAvailableLocations() {
    fetchData("fetchLocations").then(data => {
        if (!data || !data.locations || !Array.isArray(data.locations)) {
            console.error("❌ Error: Locations data is invalid", data);
            return;
        }
        console.log("📍 Available Locations:", data.locations);
    });
}

/** ✅ Fetch Available Aircraft for Selected Location */
function fetchAvailableAircraft(airport_code) {
    if (!airport_code) {
        console.error("❌ No airport_code provided for aircraft fetch.");
        return;
    }

    console.log("📡 Fetching aircraft for airport:", airport_code);

    fetchData("fetchAircraft", { airport_code: airport_code }).then(data => {
        if (!data || !data.aircraft || !Array.isArray(data.aircraft)) {
            console.error("❌ Error: Aircraft data is invalid", data);
            Swal.fire({
                icon: "error",
                title: "No Aircraft Available",
                text: "No aircraft found for this airport."
            });
            return;
        }

        let aircraftOptions = data.aircraft.map(ac => ({
            value: ac.tail_number, 
            text: `${ac.tail_number} - ${ac.model}`
        }));

        renderDropdown(aircraftOptions, "swal-aircraft");
    }).catch(error => {
        console.error("❌ Aircraft Fetch Error:", error);
    });
}

/** ✅ Fetch Available CFIs for Selected Location */
function fetchAvailableCFIs(location) {
    if (!location) {
        console.error("❌ No location provided for CFI fetch.");
        return;
    }

    console.log("📡 Fetching CFIs for location:", location);

    fetchData("fetchCFIs", { location: location }).then(data => {
        if (!data || !data.cfis || !Array.isArray(data.cfis)) {
            console.error("❌ Error: CFI data is invalid", data);
            return;
        }

        let cfiDropdown = document.getElementById("swal-cfi");
        if (!cfiDropdown) {
            console.error("❌ Dropdown swal-cfi not found in DOM.");
            Swal.fire({
                icon: "error",
                title: "UI Error",
                text: "CFI dropdown not found. Please contact support."
            });
            return;
        }

        let cfiOptions = data.cfis.map(cfi => ({
            value: cfi.cfi_id, 
            text: `${cfi.first_name} ${cfi.last_name}`
        }));

        renderDropdown(cfiOptions, "swal-cfi");
    }).catch(error => {
        console.error("❌ CFI Fetch Error:", error);
    });
}


/** ✅ Check Assigned CFI */
function checkForAssignedCFI(location) {
    fetchData("fetchAssignedCFI", { location }).then(data => {
        if (data && data.cfi_name) {
            document.getElementById("assigned-cfi").innerText = data.cfi_name;
        }
    });
}

/** ✅ Fetch Available Time Slots */
function fetchAvailableTimeSlots(location) {
    const date = document.getElementById("swal-date").value;
    const tail_number = document.getElementById("swal-aircraft").value;
    const cfi_id = document.getElementById("swal-cfi").value;

    if (!date || !tail_number || !cfi_id) return;

    fetchData("fetchAvailableSlots", { date, location, tail_number, cfi_id }).then(data => {
        if (!data || !data.time_slots || !Array.isArray(data.time_slots)) {
            console.error("❌ Error: Time slot data is invalid", data);
            return;
        }

        let timeOptions = data.time_slots.map(slot => ({
            value: slot.start_time,
            text: `${slot.start_time} - ${slot.end_time}`
        }));

        renderDropdown(timeOptions, "swal-time");
    });
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


