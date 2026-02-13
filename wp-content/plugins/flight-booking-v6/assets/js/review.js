/** 
 * review.js - Handles Flight Review Selection & Booking
 */

// ✅ Start Flight Review Booking Process
function startFlightReviewBooking() {
    console.log("🚀 Flight Review Booking Process Started");

    Swal.fire({
        title: "Flight Review Selection",
        html: `
            <label for='swal-review-aircraft'>Choose Aircraft:</label>
            <select id='swal-review-aircraft' class='swal2-input'>
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
                    document.getElementById("swal-review-aircraft").innerHTML += options;
                }
            });
        },
        preConfirm: () => {
            const selectedAircraft = document.getElementById("swal-review-aircraft").value;
            if (!selectedAircraft) {
                Swal.showValidationMessage("⚠️ Please select an aircraft.");
                return false;
            }
            return { selectedAircraft };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            saveToSession("selectedReviewAircraft", result.value.selectedAircraft);
            selectReviewTime();
        }
    });
}

// ✅ Step 2: Select Flight Review Time
function selectReviewTime() {
    Swal.fire({
        title: "Choose Your Flight Review Time",
        html: `<input type='datetime-local' id='swal-review-time' class='swal2-input'>`,
        confirmButtonText: "Confirm Time",
    }).then((result) => {
        if (result.isConfirmed) {
            const selectedTime = document.getElementById("swal-review-time").value;
            saveToSession("selectedReviewTime", selectedTime);
            confirmFlightReviewBooking();
        }
    });
}

// ✅ Final Step: Confirm Flight Review Booking
function confirmFlightReviewBooking() {
    let aircraft = getSessionData("selectedReviewAircraft");
    let reviewTime = getSessionData("selectedReviewTime");

    Swal.fire({
        title: "Confirm Your Flight Review",
        html: `
            <p><strong>Aircraft:</strong> ${aircraft}</p>
            <p><strong>Time:</strong> ${reviewTime}</p>
        `,
        confirmButtonText: "Confirm Booking",
        showCancelButton: true,
    }).then((result) => {
        if (result.isConfirmed) {
            // ✅ Submit Booking
            fetchData("submitBooking", { lessonType: "Flight Review", aircraft, reviewTime }).then(response => {
                if (response.success) {
                    Swal.fire({ icon: "success", title: "Flight Review Booked!", text: "Your flight review has been successfully booked." });
                } else {
                    Swal.fire({ icon: "error", title: "Booking Failed", text: response.message });
                }
            });
        }
    });
}
