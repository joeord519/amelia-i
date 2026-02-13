/** 
 * rental.js - Handles Rental Flight Selection & Booking
 */

// ✅ Start Rental Flight Booking Process
function startRentalBooking() {
    console.log("🚀 Rental Flight Booking Process Started");

    Swal.fire({
        title: "Rental Aircraft Selection",
        html: `
            <label for='swal-rental-aircraft'>Choose Aircraft:</label>
            <select id='swal-rental-aircraft' class='swal2-input'>
                <option value='' disabled selected>Select Aircraft</option>
            </select>
        `,
        confirmButtonText: "Next",
        allowOutsideClick: false,
        didOpen: () => {
            let location = getSessionData("selectedLocation") || "1H0";
            fetchData("fetchAircraft", { location }).then(data => {
                if (data && data.aircraft) {
                    let options = data.aircraft.map(ac => `<option value="${ac.tail_number}">${ac.tail_number} - ${ac.model}</option>`).join("");
                    document.getElementById("swal-rental-aircraft").innerHTML += options;
                }
            });
        },
        preConfirm: () => {
            const selectedAircraft = document.getElementById("swal-rental-aircraft").value;
            if (!selectedAircraft) {
                Swal.showValidationMessage("⚠️ Please select an aircraft.");
                return false;
            }
            return { selectedAircraft };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            saveToSession("selectedRentalAircraft", result.value.selectedAircraft);
            selectRentalTime();
        }
    });
}

// ✅ Step 2: Select Rental Time
function selectRentalTime() {
    Swal.fire({
        title: "Choose Your Rental Flight Time",
        html: `<input type='datetime-local' id='swal-rental-time' class='swal2-input'>`,
        confirmButtonText: "Confirm Time",
    }).then((result) => {
        if (result.isConfirmed) {
            const selectedTime = document.getElementById("swal-rental-time").value;
            saveToSession("selectedRentalTime", selectedTime);
            confirmRentalBooking();
        }
    });
}

// ✅ Final Step: Confirm Rental Flight Booking
function confirmRentalBooking() {
    let aircraft = getSessionData("selectedRentalAircraft");
    let rentalTime = getSessionData("selectedRentalTime");

    Swal.fire({
        title: "Confirm Your Rental Flight",
        html: `
            <p><strong>Aircraft:</strong> ${aircraft}</p>
            <p><strong>Time:</strong> ${rentalTime}</p>
        `,
        confirmButtonText: "Confirm Booking",
        showCancelButton: true,
    }).then((result) => {
        if (result.isConfirmed) {
            // ✅ Submit Booking
            fetchData("submitBooking", { lessonType: "Rental", aircraft, rentalTime }).then(response => {
                if (response.success) {
                    Swal.fire({ icon: "success", title: "Flight Booked!", text: "Your rental flight has been successfully booked." });
                } else {
                    Swal.fire({ icon: "error", title: "Booking Failed", text: response.message });
                }
            });
        }
    });
}
