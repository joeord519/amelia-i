/** 
 * simulator.js - Handles Simulator Lesson Selection & Booking
 */

// ✅ Start Simulator Lesson Booking Process
function startSimulatorBooking() {
    console.log("🚀 Simulator Booking Process Started");

    Swal.fire({
        title: "Simulator Selection",
        html: `
            <label for='swal-simulator'>Choose Simulator:</label>
            <select id='swal-simulator' class='swal2-input'>
                <option value='' disabled selected>Select Simulator</option>
            </select>
        `,
        confirmButtonText: "Next",
        allowOutsideClick: false,
        didOpen: () => {
            let location = getSessionData("selectedLocation") || "1H0";
            fetchData("fetchSimulators", { location }).then(data => {
                if (data && data.simulators) {
                    let options = data.simulators.map(sim => `<option value="${sim.id}">${sim.name} - ${sim.model}</option>`).join("");
                    document.getElementById("swal-simulator").innerHTML += options;
                }
            });
        },
        preConfirm: () => {
            const selectedSimulator = document.getElementById("swal-simulator").value;
            if (!selectedSimulator) {
                Swal.showValidationMessage("⚠️ Please select a simulator.");
                return false;
            }
            return { selectedSimulator };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            saveToSession("selectedSimulator", result.value.selectedSimulator);
            selectSimulatorTime();
        }
    });
}

// ✅ Step 2: Select Lesson Time
function selectSimulatorTime() {
    Swal.fire({
        title: "Choose Your Simulator Lesson Time",
        html: `<input type='datetime-local' id='swal-simulator-time' class='swal2-input'>`,
        confirmButtonText: "Confirm Time",
    }).then((result) => {
        if (result.isConfirmed) {
            const selectedTime = document.getElementById("swal-simulator-time").value;
            saveToSession("selectedSimulatorTime", selectedTime);
            confirmSimulatorBooking();
        }
    });
}

// ✅ Final Step: Confirm Simulator Lesson Booking
function confirmSimulatorBooking() {
    let simulator = getSessionData("selectedSimulator");
    let lessonTime = getSessionData("selectedSimulatorTime");

    Swal.fire({
        title: "Confirm Your Simulator Lesson",
        html: `
            <p><strong>Simulator:</strong> ${simulator}</p>
            <p><strong>Time:</strong> ${lessonTime}</p>
        `,
        confirmButtonText: "Confirm Booking",
        showCancelButton: true,
    }).then((result) => {
        if (result.isConfirmed) {
            // ✅ Submit Booking
            fetchData("submitBooking", { lessonType: "Simulator", simulator, lessonTime }).then(response => {
                if (response.success) {
                    Swal.fire({ icon: "success", title: "Lesson Booked!", text: "Your simulator session has been successfully booked." });
                } else {
                    Swal.fire({ icon: "error", title: "Booking Failed", text: response.message });
                }
            });
        }
    });
}
