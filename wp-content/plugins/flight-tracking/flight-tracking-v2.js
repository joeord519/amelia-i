// flight-tracking.js

async function startFlightTracking() {
  const { value: phone } = await Swal.fire({
    title: "📞 Enter Your Phone Number",
    input: "text",
    inputLabel: "Format: (314) 555-1234",
    inputPlaceholder: "(314) 555-1234",
    confirmButtonText: "Verify",
    didOpen: () => {
      const input = Swal.getInput();
      input.addEventListener('input', () => {
        let numbers = input.value.replace(/\D/g, '').substring(0, 10);
        const parts = [];
        if (numbers.length > 0) parts.push('(' + numbers.substring(0, 3));
        if (numbers.length >= 4) parts.push(') ' + numbers.substring(3, 6));
        if (numbers.length >= 7) parts.push('-' + numbers.substring(6, 10));
        input.value = parts.join('');
      });
    },
    inputValidator: (value) => {
      if (!value.match(/^\(\d{3}\) \d{3}-\d{4}$/)) {
        return "Please enter a valid phone number in the format (314) 555-1234";
      }
    }
  });

  if (!phone) return;

  try {
    const response = await fetch('verify_student.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ phone: phone.replace(/\D/g, '') })
    });

    const result = await response.json();

    if (result.status === 'success') {
      const student = result.student;

      if (!student.profile_photo_url) {
        return promptForSelfieUpload(student.student_id);
      }

      localStorage.setItem('flightStudent', JSON.stringify(student));

      const activeFlight = JSON.parse(localStorage.getItem("activeFlight") || "{}");

      if (!activeFlight || !activeFlight.tail_number || activeFlight.checked_in === true) {
        return await launchCheckoutFlow(student);
      } else if (activeFlight.student_id === student.student_id && !activeFlight.checked_in) {
        return await launchCheckinFlow(activeFlight);
      } else {
        const choice = await Swal.fire({
          icon: 'success',
          title: `Welcome back, ${student.first_name}!`,
          text: "You are now verified. What would you like to do?",
          showDenyButton: true,
          confirmButtonText: '🛩️ Check Out Aircraft',
          denyButtonText: '📥 Check In Aircraft'
        });

        if (choice.isConfirmed) return await launchCheckoutFlow(student);
        if (choice.isDenied) return await launchCheckinFlow(student);
      }
    } else {
      await Swal.fire({
  icon: 'error',
  title: 'Not Found',
  text: 'No student found with that phone number.',
  confirmButtonText: 'Try Again'
});
return startFlightTracking(); // Go back to phone entry
    }

  } catch (err) {
    console.error("❌ Error parsing or processing verification:", err);
    await Swal.fire({
  icon: 'error',
  title: 'Server Error',
  text: 'There was a problem verifying your phone number. Please try again.',
  confirmButtonText: 'Retry'
});
return startFlightTracking(); // Go back to phone entry

  }
}

async function promptForSelfieUpload(student_id) {
  const { isConfirmed } = await Swal.fire({
    title: '📸 First Flight? Let’s verify you!',
    html: `
      <p>Please take a selfie (face only, no Hobbs needed).</p>
      <button id="takeSelfieBtn" class="swal2-confirm swal2-styled" style="margin-bottom: 10px;">📷 Take Selfie</button>
      <input type="file" id="selfieInput" accept="image/*" capture="user" style="display:none;" />
      <img id="selfiePreview" style="margin-top:10px; display:none; width: 100px; border-radius: 10px;">
    `,
    confirmButtonText: 'Upload Selfie',
    didOpen: () => {
      const input = document.getElementById('selfieInput');
      const preview = document.getElementById('selfiePreview');
      const button = document.getElementById('takeSelfieBtn');
      button.addEventListener('click', () => input.click());
      input.addEventListener('change', () => {
        const file = input.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = () => {
            preview.src = reader.result;
            preview.style.display = 'block';
          };
          reader.readAsDataURL(file);
        }
      });
    },
    preConfirm: async () => {
      const fileInput = document.getElementById('selfieInput');
      const file = fileInput.files[0];
      if (!file) {
        Swal.showValidationMessage("Please take a selfie to continue.");
        return false;
      }

      const formData = new FormData();
      formData.append('student_id', student_id);
      formData.append('selfie', file);

      const res = await fetch('selfie_upload.php', {
        method: 'POST',
        body: formData
      });
      const data = await res.json();

      if (data.status !== 'success') {
        Swal.showValidationMessage(`Upload failed: ${data.error}`);
        return false;
      }

      return data;
    }
  });

  if (isConfirmed) {
    const updatedStudent = {
      ...JSON.parse(localStorage.getItem("flightStudent")),
      profile_photo_url: 'SET'
    };
    localStorage.setItem("flightStudent", JSON.stringify(updatedStudent));

    const next = await Swal.fire({
      icon: 'success',
      title: `✅ Selfie Uploaded!`,
      text: 'You are now verified for flight check-outs.',
      showDenyButton: true,
      confirmButtonText: '🛩️ Check Out Aircraft',
      denyButtonText: '📥 Check In Aircraft'
    });

    if (next.isConfirmed) return await launchCheckoutFlow(updatedStudent);
    if (next.isDenied) return await launchCheckinFlow(updatedStudent);
  }
}

async function launchCheckoutFlow(student) {
  Swal.fire({
  title: '🚀 Checkout Started',
  text: 'Let’s get this flight rolling!',
  icon: 'info',
  timer: 1500,
  showConfirmButton: false,
  position: 'center'
});

  let selectedAirport = student.airport_code || '1H0';
  let selectedTail = null;
  let selectedType = null;

  const response = await fetch(`fetch_aircraft.php?airport=${selectedAirport}`);
  const aircraft = await response.json();

  if (aircraft.status !== 'success' || !aircraft.aircraft.length) {
    return Swal.fire('No Aircraft', 'No available aircraft at this airport.', 'error');
  }

  let htmlOptions = '';
  for (const ac of aircraft.aircraft) {
    const label = `${ac.tail_number} – ${ac.aircraft_type}`;
    htmlOptions += `<option value="${ac.tail_number}">${label}</option>`;
  }

  const { value: tail } = await Swal.fire({
    title: '✈️ Select Aircraft',
    html: `<select id="tailSelect" class="swal2-select">${htmlOptions}</select>`,
    preConfirm: () => document.getElementById('tailSelect').value,
    confirmButtonText: 'Next'
  });

  selectedTail = tail;

  const { value: type } = await Swal.fire({
    title: '🧭 Select Flight Type',
    input: 'select',
    inputOptions: {
      Solo: 'Solo',
      Dual: 'Dual Training',
      Rental: 'Rental Flight'
    },
    inputPlaceholder: 'Choose flight type',
    confirmButtonText: 'Next',
    showCancelButton: true
  });

  selectedType = type;

  if (selectedType === 'Solo') {
    const match = await matchFace(student.student_id);
    if (!match) return;
  }

  const { value: hobbs } = await Swal.fire({
    title: '⛽ Start Hobbs',
    input: 'number',
    inputPlaceholder: 'e.g. 345.6',
    confirmButtonText: 'Next',
    inputValidator: (value) => {
      if (!value) return 'Required';
    }
  });

  const { value: tach } = await Swal.fire({
    title: '⛽ Start Tach',
    input: 'number',
    inputPlaceholder: 'e.g. 2001.1',
    confirmButtonText: 'Finish',
    inputValidator: (value) => {
      if (!value) return 'Required';
    }
  });

  const payload = {
    student_id: student.student_id,
    tail_number: selectedTail,
    flight_type: selectedType,
    hobbs_start: parseFloat(hobbs),
    tach_start: parseFloat(tach)
  };

  const checkout = await fetch('checkout_flight.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });

  const result = await checkout.json();

  if (result.status === 'success') {
    localStorage.setItem('activeFlight', JSON.stringify({
      student_id: student.student_id,
      tail_number: selectedTail,
      pin_code: result.pin_code,
      checked_in: false
    }));

    await Swal.fire({
      icon: 'success',
      title: '✅ Aircraft Checked Out!',
      html: `
        Tail: <b>${selectedTail}</b><br>
        PIN: <b>${result.pin_code}</b><br>
        Expires in 30 minutes.
      `
    });
  } else {
    Swal.fire('Error', result.error || 'Checkout failed.', 'error');
  }
}

async function matchFace(student_id) {
  const student = JSON.parse(localStorage.getItem("flightStudent"));
  const profileUrl = student.profile_photo_url;

  const { value: selfieFile } = await Swal.fire({
    title: '📸 Face Match Required',
    html: `<input type="file" id="selfieInput" accept="image/*" capture="user" />`,
    confirmButtonText: 'Submit',
    preConfirm: () => {
      const input = document.getElementById('selfieInput');
      return input?.files[0] || Swal.showValidationMessage('Please upload a selfie');
    }
  });

  if (!selfieFile) return false;

  // Upload selfie
  const formData = new FormData();
  formData.append('student_id', student_id);
  formData.append('selfie', selfieFile);

  const selfieUpload = await fetch('selfie_upload.php', {
    method: 'POST',
    body: formData
  });

  const selfieData = await selfieUpload.json();
  const selfieUrl = selfieData.url;

  if (!selfieUrl) {
    await Swal.fire('Upload Failed', 'Could not upload selfie.', 'error');
    return false;
  }

  // Get faceId for profile photo
  const profileRes = await fetch(`detect_face_id.php?url=${encodeURIComponent(profileUrl)}`);
  const profile = await profileRes.json();

  if (profile.status !== 'success') {
    await Swal.fire('Face Detection Failed', 'No face found in profile photo.', 'error');
    return false;
  }

  // Get faceId for uploaded selfie
  const selfieRes = await fetch(`detect_face_id.php?url=${encodeURIComponent(selfieUrl)}`);
  const selfie = await selfieRes.json();

  if (selfie.status !== 'success') {
    await Swal.fire('Face Detection Failed', 'No face found in uploaded selfie.', 'error');
    return false;
  }

  // Verify
  const verify = await fetch('verify_face_match.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
      faceId1: profile.faceId,
      faceId2: selfie.faceId
    })
  });

  const result = await verify.json();

  if (result.status === 'success' && result.isIdentical && result.confidence > 0.75) {
  await Swal.fire({
    icon: 'success',
    title: '✅ Face Verified',
    text: `Match confidence: ${(result.confidence * 100).toFixed(1)}%`,
    timer: 1000,
    showConfirmButton: false
  });
  return true;
} else {
  await Swal.fire({
    icon: 'error',
    title: '❌ Face Match Failed',
    text: `Match confidence: ${(result.confidence * 100).toFixed(1)}%. You must match your profile photo to check out.`,
    confirmButtonText: 'Try Again'
  });
  return false;
}

}

async function launchCheckinFlow(flight) {
  await Swal.fire({
    icon: 'info',
    title: `✅ Check-In Flow`,
    text: `Simulated check-in for aircraft ${flight.tail_number}.`,
    confirmButtonText: 'Great!'
  });

  localStorage.removeItem('activeFlight');
}

window.startFlightTracking = startFlightTracking;
