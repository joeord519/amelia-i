/** ✅ Auto-Trigger Student Verification on Page Load */
document.addEventListener("DOMContentLoaded", function () {
    showVerificationModal();
});

/** ✅ Show SweetAlert2 Verification Modal */
function showVerificationModal() {
    Swal.fire({
        title: "Flight Booking Access Verification",
        html: `
            <label for='swal-phone'>Phone Number:</label>
            <input type='text' id='swal-phone' class='swal2-input' placeholder='(XXX) XXX-XXXX' maxlength="14">

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

            return { phone, securityWord };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            verifyStudent(result.value.phone, result.value.securityWord);
        }
    });
}

/** ✅ Verify Student & Show Flight Type Menu */
function verifyStudent(phone, securityWord) {
    console.log("🔍 Sending API Request for Verification...");

    fetchData("verifyStudent", { phone, securityWord })
    .then(data => {
        console.log("🔍 API Response from verifyStudent:", data);

        if (!data || typeof data !== "object") {
            console.error("❌ Invalid API response format:", data);
            Swal.fire({
                icon: "error",
                title: "System Error",
                text: "Invalid response from server. Please try again."
            });
            return;
        }

        if (data.success) {
            sessionStorage.setItem("studentData", JSON.stringify(data));
            showFlightTypeMenu();
        } else {
            console.error("❌ Verification Failed - Response:", data);
            Swal.fire({
                icon: "error",
                title: "Verification Failed",
                text: data.message || "Invalid credentials. Please try again."
            }).then(() => {
                showVerificationModal();
            });
        }
    })
    .catch(error => {
        console.error("❌ Verification API Error:", error);
        Swal.fire({
            icon: "error",
            title: "System Error",
            text: "There was an issue verifying your credentials. Please try again."
        }).then(() => {
            showVerificationModal();
        });
    });
}


/** ✅ Show Flight Type Selection in SweetAlert2 */
function showFlightTypeMenu() {
    Swal.fire({
        title: "Choose Your Flight Type",
        html: `
            <div class="flight-menu">
                <button class="flight-type-btn" onclick="startFlightLesson()">
                    ✈️ <span>Flight Lesson</span>
                </button>
                <button class="flight-type-btn" onclick="startGroundLesson()">
                    📚 <span>Ground Lesson</span>
                </button>
                <button class="flight-type-btn" onclick="startSimulatorLesson()">
                    🖥️ <span>Simulator Lesson</span>
                </button>
                <button class="flight-type-btn" onclick="startRentalFlight()">
                    🛩️ <span>Rental Flight</span>
                </button>
                <button class="flight-type-btn" onclick="startFlightReview()">
                    ✅ <span>Flight Review</span>
                </button>
            </div>
        `,
        showConfirmButton: false,
        allowOutsideClick: false,
        customClass: {
            popup: 'flight-menu-popup'
        }
    });
}

function startFlightLesson() {
    console.log("✈️ Starting Flight Lesson Selection...");
    Swal.close();

    let studentData = JSON.parse(sessionStorage.getItem("studentData"));
    let location = studentData?.home_airport;

    if (!location) {
        console.error("❌ No location found for student.");
        Swal.fire({
            icon: "error",
            title: "Location Error",
            text: "Your home airport location is missing. Please contact support."
        });
        return;
    }

    console.log("📍 Using Location:", location);
    showFlightLessonSelection(location);
}

function updateCFIDefault(assignedCFI) {
    const lessonType = document.getElementById("swal-lesson-type").value;
    const cfiDropdown = document.getElementById("swal-cfi");

    if (lessonType.includes("dual") && assignedCFI) {
        cfiDropdown.value = assignedCFI;
    }
}

/** ✅ Show Flight Lesson Selection Modal */
function showFlightLessonSelection(location, assignedCFI = null) {
    console.log("📍 Opening Flight Lesson Selection with Location:", location);

    Swal.fire({
        title: "Flight Lesson Selection",
        html: `
            <p><strong>Current Location:</strong> ${location}</p>

            <label for="swal-lesson-type">Choose Lesson Type:</label>
            <select id="swal-lesson-type" class="swal2-input">
                <option value="" disabled selected>Select Lesson Type</option>
                <option value="dual-local">Dual Training - Local (2hr)</option>
                <option value="dual-xc">Dual Training - Cross Country (4hr)</option>
                <option value="solo-local">Solo - Local (2hr)</option>
                <option value="solo-xc">Solo - Cross Country (4hr)</option>
            </select>

            <label for="swal-aircraft">Choose Your Aircraft:</label>
            <select id="swal-aircraft" class="swal2-input">
                <option value="" disabled selected>Loading aircraft...</option>
            </select>

            <label for="swal-cfi">Choose Your CFI:</label>
            <select id="swal-cfi" class="swal2-input">
                <option value="" disabled selected>Loading CFIs...</option>
            </select>
        `,
        showConfirmButton: true,
        confirmButtonText: "Next",
        allowOutsideClick: false,
        didOpen: () => {
            fetchAvailableAircraft(location);
            fetchAvailableCFIs(location);
            document.getElementById("swal-lesson-type").addEventListener("change", () => updateCFIDefault(assignedCFI));
        },
        preConfirm: () => {
            const lessonType = document.getElementById("swal-lesson-type").value;
            const tail_number = document.getElementById("swal-aircraft").value;
            const cfi_id = document.getElementById("swal-cfi").value;

            if (!lessonType || !tail_number || (lessonType.includes("dual") && !cfi_id)) {
                Swal.showValidationMessage("⚠️ Please complete all selections.");
                return false;
            }

            return { lessonType, tail_number, cfi_id };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            showFlightDateTimeSelection(result.value);
        }
    });
}

function showFlightDateTimeSelection(details) {
    Swal.fire({
        title: "Select Flight Date & Time",
        html: `
            <label for="swal-date">Choose a Date:</label>
            <input type="date" id="swal-date" class="swal2-input">

            <label for="swal-time">Choose a Time Slot:</label>
            <select id="swal-time" class="swal2-input">
                <option value="" disabled selected>Select Time</option>
            </select>
        `,
        showConfirmButton: true,
        confirmButtonText: "Confirm Booking",
        allowOutsideClick: false,
        didOpen: () => {
            document.getElementById("swal-date").addEventListener("change", () => fetchAvailableTimeSlots(details.tail_number, details.cfi_id));
        },
        preConfirm: () => {
            const date = document.getElementById("swal-date").value;
            const time = document.getElementById("swal-time").value;

            if (!date || !time) {
                Swal.showValidationMessage("⚠️ Please select a date and time.");
                return false;
            }

            return { ...details, date, time };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            confirmFlightLesson(result.value);
        }
    });
}
