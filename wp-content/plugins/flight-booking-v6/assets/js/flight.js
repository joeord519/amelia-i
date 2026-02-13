/** 
 * flight.js - Handles Flight Lesson Selection & Booking
 */

// ✅ Start Flight Lesson Booking Process
function startFlightBooking() {
    console.log("🚀 Flight Booking Process Started");

    Swal.fire({
        title: "Flight Lesson Selection",
        html: `
            <label for='swal-flight-type'>Choose Flight Type:</label>
            <select id='swal-flight-type' class='swal2-input'>
                <option value='' disabled selected>Select Flight Type</option>
                <option value='Dual Training'>Dual Training</option>
                <option value='Dual Cross Country'>Dual Cross Country</option>
                <option value='Solo - Local'>Solo Flight - Local</option>
                <option value='Solo - Cross Country'>Solo Flight - Cross Country</option>
            </select>
        `,
        confirmButtonText: "Next",
        allowOutsideClick: false,
        preConfirm: () => {
            const flightType = document.getElementById("swal-flight-type").value;
            if (!flightType) {
                Swal.showValidationMessage("⚠️ Please select a flight type.");
                return false;
            }
            return { flightType };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            saveToSession("selectedFlightType", result.value.flightType);
            selectFlightAircraft();
        }
    });
}

// ✅ Step 2: Select Aircraft
function selectFlightAircraft() {
    let location = getSessionData("selectedLocation") || "1H0";

    fetchData("fetchAircraft", { location }).then(data => {
        if (!data || !data.aircraft) {
            console.error("❌ No aircraft found.");
            Swal.fire({ icon: "error", title: "Error", text: "No aircraft available for this location." });
            return;
        }

        Swal.fire({
            title: "Choose Your Aircraft",
            html: `
                <select id='swal-aircraft' class='swal2-input'>
                    ${data.aircraft.map(ac => `<option value="${ac.tail_number}">${ac.tail_number} - ${ac.model}</option>`).join("")}
                </select>
            `,
            confirmButtonText: "Confirm",
        }).then((result) => {
            if (result.isConfirmed) {
                const selectedAircraft = document.getElementById("swal-aircraft").value;
                saveToSession("selectedAircraft", selectedAircraft);
                selectFlightTime();
            }
        });
    });
}

// ✅ Step 3: Select Flight Time
function selectFlightTime() {
    Swal.fire({
        title: "Choose Your Flight Time",
        html: `<input type='datetime-local' id='swal-flight-time' class='swal2-input'>`,
        confirmButtonText: "Confirm Time",
    }).then((result) => {
        if (result.isConfirmed) {
            const selectedTime = document.getElementById("swal-flight-time").value;
            saveToSession("selectedFlightTime", selectedTime);
            confirmFlightBooking();
        }
    });
}

// ✅ Final Step: Confirm Flight Booking
function confirmFlightBooking() {
    let flightType = getSessionData("selectedFlightType");
    let aircraft = getSessionData("selectedAircraft");
    let flightTime = getSessionData("selectedFlightTime");

    Swal.fire({
        title: "Confirm Your Flight",
        html: `
            <p><strong>Flight Type:</strong> ${flightType}</p>
            <p><strong>Aircraft:</strong> ${aircraft}</p>
            <p><strong>Time:</strong> ${flightTime}</p>
        `,
        confirmButtonText: "Confirm Booking",
        showCancelButton: true,
    }).then((result) => {
        if (result.isConfirmed) {
            // ✅ Submit Booking
            fetchData("submitBooking", { flightType, aircraft, flightTime }).then(response => {
                if (response.success) {
                    Swal.fire({ icon: "success", title: "Flight Booked!", text: "Your flight has been successfully booked." });
                } else {
                    Swal.fire({ icon: "error", title: "Booking Failed", text: response.message });
                }
            });
        }
    });
}
