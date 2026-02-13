/** 
 * ground.js - Handles Ground Lesson Selection & Booking
 */

// ✅ Start Ground Lesson Booking Process
function startGroundLessonBooking() {
    console.log("🚀 Ground Lesson Booking Process Started");

    Swal.fire({
        title: "Ground Lesson Selection",
        html: `
            <label for='swal-ground-topic'>Choose Topic:</label>
            <select id='swal-ground-topic' class='swal2-input'>
                <option value='' disabled selected>Select Topic</option>
                <option value='Weather'>Weather</option>
                <option value='Airspace & Regulations'>Airspace & Regulations</option>
                <option value='Flight Planning'>Flight Planning</option>
                <option value='Emergency Procedures'>Emergency Procedures</option>
            </select>
        `,
        confirmButtonText: "Next",
        allowOutsideClick: false,
        preConfirm: () => {
            const groundTopic = document.getElementById("swal-ground-topic").value;
            if (!groundTopic) {
                Swal.showValidationMessage("⚠️ Please select a topic.");
                return false;
            }
            return { groundTopic };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            saveToSession("selectedGroundTopic", result.value.groundTopic);
            selectGroundLessonTime();
        }
    });
}

// ✅ Step 2: Select Lesson Time
function selectGroundLessonTime() {
    Swal.fire({
        title: "Choose Your Lesson Time",
        html: `<input type='datetime-local' id='swal-ground-time' class='swal2-input'>`,
        confirmButtonText: "Confirm Time",
    }).then((result) => {
        if (result.isConfirmed) {
            const selectedTime = document.getElementById("swal-ground-time").value;
            saveToSession("selectedGroundTime", selectedTime);
            confirmGroundLessonBooking();
        }
    });
}

// ✅ Final Step: Confirm Ground Lesson Booking
function confirmGroundLessonBooking() {
    let groundTopic = getSessionData("selectedGroundTopic");
    let lessonTime = getSessionData("selectedGroundTime");

    Swal.fire({
        title: "Confirm Your Ground Lesson",
        html: `
            <p><strong>Topic:</strong> ${groundTopic}</p>
            <p><strong>Time:</strong> ${lessonTime}</p>
        `,
        confirmButtonText: "Confirm Booking",
        showCancelButton: true,
    }).then((result) => {
        if (result.isConfirmed) {
            // ✅ Submit Booking
            fetchData("submitBooking", { lessonType: "Ground Lesson", groundTopic, lessonTime }).then(response => {
                if (response.success) {
                    Swal.fire({ icon: "success", title: "Lesson Booked!", text: "Your ground lesson has been successfully booked." });
                } else {
                    Swal.fire({ icon: "error", title: "Booking Failed", text: response.message });
                }
            });
        }
    });
}
