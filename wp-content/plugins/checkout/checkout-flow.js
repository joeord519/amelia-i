console.log("✅ JS file loaded from checkout-flow.js");

// Initialize or increment the counter each time the page is loaded
let cfiOrderCounter = parseInt(localStorage.getItem('cfiOrderCounter') || '0', 10);
cfiOrderCounter = (cfiOrderCounter + 1) % 2; // Toggle between 0 and 1
localStorage.setItem('cfiOrderCounter', cfiOrderCounter);

// === Moment libraries (single import) ===
import {
  phonePrompts,
  noPhonePrompts,
  noStudentFoundPrompts,
  oopsMessages,
  comicBackgrounds,
  checkoutReminders,
  profilePhotoTips,
  selfieRetakePrompts,
  securityWordSetupTips,
  securityWordConfirmPrompts,
  taglines,
  spinnerGIFs
} from './momentPrompts.js';

// === Wizard helpers ===
import {
  cleanPhoneInput,
  getSmartRandomGroup,
  getUniqueRandomItem
} from './utils.js';

// === Flight Type Options ===
const flightTypeOptions = [
  { label: 'Dual Training Flight (with an Instructor)', value: 'dual_training' },
  { label: 'Solo Training Flight', value: 'solo_training' },
  { label: 'Dual Cross Country Flight (with an Instructor)', value: 'dual_cross_country' },
  { label: 'Solo Cross Country Flight', value: 'solo_cross_country' },
  { label: 'Rental Flight - Local - 2 Hours Max', value: 'rental_local' },
  { label: 'Rental Flight - Cross Country - 4 Hours Max', value: 'rental_cross_country' }
];

document.addEventListener('DOMContentLoaded', () => {
  console.log("✅ DOM Ready, launching flow...");
  startCheckOutFlow();
});

// === Helper functions ===
function getRandomComicBackground() {
  const randomIndex = Math.floor(Math.random() * comicBackgrounds.length);
  return comicBackgrounds[randomIndex];
}

function formatPhoneInput(input) {
  const cleaned = input.replace(/\D/g, '');
  const match = cleaned.match(/^(\d{0,3})(\d{0,3})(\d{0,4})$/);
  if (!match) return '';
  return !match[2] ? match[1] : `(${match[1]}) ${match[2]}${match[3] ? '-' + match[3] : ''}`;
}

function formatInstructorOptions(cfi1, cfi2, cfiNames) {
  const options = {};

  // If the order is 0, show cfi1 first, then cfi2
  if (cfiOrderCounter === 0) {
    if (cfi1) options[cfi1] = cfiNames[cfi1] || `Instructor #1 (Unknown)`;
    if (cfi2 && cfi2 !== cfi1) options[cfi2] = cfiNames[cfi2] || `Instructor #2 (Unknown)`;
  }
  // If the order is 1, show cfi2 first, then cfi1
  else {
    if (cfi2) options[cfi2] = cfiNames[cfi2] || `Instructor #2 (Unknown)`;
    if (cfi1 && cfi1 !== cfi2) options[cfi1] = cfiNames[cfi1] || `Instructor #1 (Unknown)`;
  }

  return options;
}

function showOopsPopup() {
  const randomOops = oopsMessages[Math.floor(Math.random() * oopsMessages.length)];
  Swal.fire({
    title: '😬 Oops!',
    html: `
      <h2 style="font-family: 'Bangers', cursive; font-size: 24px;">${randomOops}</h2>
      <p style="font-size: 16px;">We couldn't find a flight scheduled for you today.</p>
    `,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Checking Availability',
    cancelButtonText: 'I had a flight scheduled!',
    backdrop: `
      rgba(0,0,123,0.4)
      url("${getRandomComicBackground()}")
      center center
      no-repeat
    `,
    customClass: { popup: 'swal-theme' },
    didOpen: () => {
      const container = Swal.getContainer();
      if (container) container.classList.add('show-background');
    }
  }).then(result => {
    if (result.isConfirmed) {
      // ✅ If student clicks "Checking Availability" ➔ Launch full manual Pick Flight Flow
      startPickFlightType();
    } else {
      // ✅ If student says "I Had Flight Scheduled!" ➔ Show See Staff Message ➔ Back to phone
      Swal.fire({
        icon: 'info',
        title: 'ℹ️ Please See Staff',
        text: 'A team member will help you figure it out!',
        confirmButtonText: 'OK',
        backdrop: `
          rgba(0,0,123,0.4)
          url("${getRandomComicBackground()}")
          center center
          no-repeat
        `,
        customClass: { popup: 'swal-theme' },
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
          const container = Swal.getContainer();
          if (container) container.classList.add('show-background');
        }
      }).then(() => {
        startCheckOutFlow(); // ✅ Restart flow back to phone input after OK
      });
    }
  });
}

// === Main Check-Out Flow ===
function startCheckOutFlow() {
  Swal.fire({
    html: `
      <h3>Student Check-Out</h3>
      <h4 style="font-family: 'Bangers', cursive; font-size:20px; font-weight:600; color:#555; margin-top:-10px;">
        ${taglines[Math.floor(Math.random() * taglines.length)]}
      </h4>
      <h2 style="font-size:22px; font-weight:500; color:#333;">
        ${getUniqueRandomItem('phonePromptsSeen', phonePrompts)}
      </h2>
    `,
    input: 'text',
    inputPlaceholder: '(###) ###-####',
    confirmButtonText: 'Next',
    showCancelButton: false,
    backdrop: `
      rgba(0,0,123,0.4)
      url("${getRandomComicBackground()}")
      center center
      no-repeat
    `,
    customClass: { popup: 'swal-theme' },
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: () => {
      const container = Swal.getContainer();
      if (container) container.classList.add('show-background');
      const input = Swal.getInput();
      if (input) {
        input.focus();
        input.addEventListener('input', () => {
          input.value = formatPhoneInput(input.value);
        });
      }
    },
    preConfirm: (input) => {
      const cleanedPhone = input.replace(/\D/g, '');
      if (cleanedPhone.length !== 10) {
        Swal.showValidationMessage('📵 Please enter a valid 10-digit phone number!');
        return false;
      }
      return cleanedPhone;
    }
  }).then(result => {
    if (!result.isConfirmed || !result.value) {
      startCheckOutFlow();
      return;
    }

    const inputPhone = result.value;

        // ✅ 1. Check student existence via face_login.php
    fetch('face_login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ login_input: inputPhone })
    })
    .then(response => response.json())
    .then(studentData => {
      console.log('✅ Student Data:', studentData);
  if (!studentData || !studentData.student_id) {
    Swal.fire({
      icon: 'error',
      title: '📵 Student Not Found',
      html: `<p>We couldn't find a student linked to that phone number.</p>
             <p>Please double-check your number or see a team member!</p>`,
      confirmButtonText: 'Retry',
      backdrop: `rgba(0,0,123,0.4) url("${getRandomComicBackground()}") center center no-repeat`,
      customClass: { popup: 'swal-theme' },
      allowOutsideClick: false,
      allowEscapeKey: false
    }).then(() => {
      startCheckOutFlow();
    });
    return;
  }

  // ✅ Student *was* found — so we assign CFIs
  localStorage.setItem('assignedCfi1', studentData.cfi_1_id || '');
  localStorage.setItem('assignedCfi2', studentData.cfi_2_id || '');

  // 🛫 Continue your flow from here, like loading aircraft...

  // ✅ Correct placement HERE: Student home airport handling clearly defined
  let studentHomeLocation = studentData.home_airport && studentData.home_airport.trim() 
    ? studentData.home_airport.trim() 
    : '1H0';

  // ✅ Call loadAircraft with the verified or default location
  localStorage.setItem('studentHomeLocation', studentHomeLocation);
  localStorage.setItem('assignedCfi1', studentData.cfi_1_id || '');
  localStorage.setItem('assignedCfi2', studentData.cfi_2_id || '');


  // ✅ Continue existing spinner logic
  const randomSpinner = spinnerGIFs[Math.floor(Math.random() * spinnerGIFs.length)];
  Swal.fire({
    title: '✈️ Searching for Your Flight...',
    html: `
      <img src="${randomSpinner}" width="100" alt="Loading..." style="margin-top:20px;">
      <p style="margin-top:20px; font-size:16px;">Hold tight, we're contacting Dispatch...</p>
    `,
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    backdrop: `
      rgba(0,0,123,0.4)
      url("${getRandomComicBackground()}")
      center center
      no-repeat
    `,
    customClass: { popup: 'swal-theme' }
  });

  // ✅ Then continue your SkylistPro fetch logic...

      // ✅ 2. Student found ➔ show spinner
            Swal.fire({
        title: '✈️ Searching for Your Flight...',
        html: `
          <img src="${randomSpinner}" width="100" alt="Loading..." style="margin-top:20px;">
          <p style="margin-top:20px; font-size:16px;">Hold tight, we're contacting Dispatch...</p>
        `,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        backdrop: `
          rgba(0,0,123,0.4)
          url("${getRandomComicBackground()}")
          center center
          no-repeat
        `,
        customClass: { popup: 'swal-theme' }
      });

      // ✅ 3. Fetch SkylistPro for today's flight
      fetch(`https://skylistpro.com/api/get-bookly-appointments.php?key=pistonops123&q=${inputPhone}`)
      .then(response => response.json())
      .then(appointmentData => {
        if (appointmentData.status !== 'success') {
          // 🚨 No flight found ➔ Show funny Oops
          showOopsPopup();
          return;
        }

        // ✅ 4. Flight found ➔ Launch Face Verification next (next phase)
        startFaceVerification(studentData.student_id, appointmentData);

      })
      .catch(error => {
        console.error('❌ Error fetching appointment:', error);
        Swal.fire({
          title: '⚠️ Error Reaching Scheduling System',
          html: `
            <p>We couldn’t contact the scheduling system.</p>
            <p>Please check your connection or see staff for assistance.</p>
          `,
          icon: 'error',
          confirmButtonText: 'Retry',
          showCancelButton: true,
          cancelButtonText: 'See Staff',
          backdrop: `
            rgba(0,0,123,0.4)
            url("${getRandomComicBackground()}")
            center center
            no-repeat
          `,
          customClass: { popup: 'swal-theme' },
          didOpen: () => {
            const container = Swal.getContainer();
            if (container) container.classList.add('show-background');
          }
        }).then(result => {
          if (result.isConfirmed) {
            startCheckOutFlow();
          }
        });
      });

    })
    .catch(error => {
      console.error('❌ Error reaching student database:', error);
      Swal.fire({
        title: '⚠️ Database Connection Error',
        html: `
          <p>We couldn’t verify your student record.</p>
          <p>Please try again or see a team member!</p>
        `,
        icon: 'error',
        confirmButtonText: 'Retry',
        showCancelButton: true,
        cancelButtonText: 'See Staff',
        backdrop: `
          rgba(0,0,123,0.4)
          url("${getRandomComicBackground()}")
          center center
          no-repeat
        `,
        customClass: { popup: 'swal-theme' }
      }).then(result => {
        if (result.isConfirmed) {
          startCheckOutFlow();
        }
      });
    });

  });
}

function startPickFlightType() {
  Swal.fire({
    title: '✈️ Pick Your Flight Type',
    input: 'select',
    inputOptions: {
      'dual_training': 'Dual Training Flight (with Instructor)',
      'solo_training': 'Solo Training Flight',
      'dual_cross_country': 'Dual Cross Country Flight (with Instructor)',
      'solo_cross_country': 'Solo Cross Country Flight',
      'rental_local': 'Rental Flight - Local',
      'rental_cross_country': 'Rental Flight - Cross Country'
    },
    inputPlaceholder: 'Select Flight Type',
    showCancelButton: true,
    confirmButtonText: 'Next',
    backdrop: `
      rgba(0,0,123,0.4)
      url("${getRandomComicBackground()}")
      center center
      no-repeat
    `,
    customClass: { popup: 'swal-theme' },
    didOpen: () => {
      const container = Swal.getContainer();
      if (container) container.classList.add('show-background');
    },
    preConfirm: (selectedType) => {
      if (!selectedType) {
        Swal.showValidationMessage('Please select a flight type to continue.');
        return false;
      }
      return selectedType;
    }
  }).then(result => {
    if (result.isConfirmed) {
      const selectedFlightType = result.value;
      startPickAircraft(selectedFlightType);
    } else {
      startCheckOutFlow(); // Cancelled ➔ Back to phone
    }
  });
}

function startPickAircraft(selectedFlightType) {
  const studentLocation = localStorage.getItem('studentHomeLocation') || '1H0';

  fetch(`fetch-aircraft-by-location.php?location=${encodeURIComponent(studentLocation)}`)
    .then(res => res.json())
    .then(data => {
      if (!data || data.length === 0) {
        Swal.fire({
          icon: 'error',
          title: 'No Aircraft Available!',
          text: 'We could not find any aircraft for your location. Please see staff!',
        }).then(() => {
          startCheckOutFlow();
        });
        return;
      }

      const aircraftOptions = {};
      data.forEach(ac => {
        aircraftOptions[ac.tail_number] = `${ac.tail_number} — ${ac.model}`;
      });

      Swal.fire({
        title: '✈️ Pick Your Aircraft',
        html: `<p style="font-size:16px;margin-bottom:10px;">Your location is <strong>${studentLocation}</strong></p>`,
        input: 'select',
        inputOptions: aircraftOptions,
        inputPlaceholder: 'Select Aircraft',
        showCancelButton: true,
        confirmButtonText: 'Next',
        cancelButtonText: 'Change Location',
        backdrop: `
          rgba(0,0,123,0.4)
          url("${getRandomComicBackground()}") 
          center center
          no-repeat
        `,
        customClass: { popup: 'swal-theme' },
        didOpen: () => {
          const container = Swal.getContainer();
          if (container) container.classList.add('show-background');
        },
        preConfirm: (selectedAircraft) => {
          if (!selectedAircraft) {
            Swal.showValidationMessage('Please select an aircraft to continue.');
            return false;
          }
          return selectedAircraft;
        }
      }).then(result => {
        if (result.isConfirmed) {
          const selectedAircraft = result.value;
          if (selectedFlightType.includes('dual')) {
            startPickInstructor(selectedFlightType, selectedAircraft);
          } else {
            startPickFlightTime(selectedFlightType, selectedAircraft, null);
          }
        } else {
          // 🛫 User clicked "Change Location" ➔ Open location picker
          promptChangeLocation(selectedFlightType);
        }
      });
    })
    .catch(err => {
      console.error('Error loading aircraft:', err);
      Swal.fire({
        icon: 'error',
        title: 'Error Loading Aircraft',
        text: 'There was a problem loading the aircraft list. Please try again or see staff.',
      }).then(() => {
        startCheckOutFlow();
      });
    });
}

function startPickInstructor(selectedFlightType, selectedAircraft) {
  const cfi1 = localStorage.getItem('assignedCfi1');
  const cfi2 = localStorage.getItem('assignedCfi2');

  // If neither is set, auto-fallback to full instructor list
  if (!cfi1 && !cfi2) {
    console.warn('No assigned CFIs — falling back to full list');
    promptChangeCFI(selectedFlightType, selectedAircraft);
    return;
  }

  // Toggle the order between cfi1 and cfi2 based on localStorage counter
  let orderedCFIs = [cfi1, cfi2];
  if (cfiOrderCounter === 1) {
    // Swap CFIs if counter is 1
    orderedCFIs = [cfi2, cfi1];
  }

  const fetchURL = `fetch-cfi-names.php?cfi1=${encodeURIComponent(orderedCFIs[0] || '')}&cfi2=${encodeURIComponent(orderedCFIs[1] || '')}`;

  fetch(fetchURL)
    .then(res => res.json())
    .then(cfiNames => {
      const dropdownOptions = {};

      // Add CFIs in toggled order
      orderedCFIs.forEach(cfi => {
        if (cfi && cfiNames[cfi]) {
          dropdownOptions[cfi] = cfiNames[cfi];
        }
      });

      Swal.fire({
        title: 'Select Your Instructor',
        input: 'select',
        inputOptions: dropdownOptions,
        showCancelButton: true,
        confirmButtonText: 'Next',
        cancelButtonText: 'Change Instructors',
        backdrop: `
          rgba(0,0,123,0.4)
          url("${getRandomComicBackground()}")
          center center
          no-repeat
        `,
        customClass: { popup: 'swal-theme' },
        preConfirm: (selectedCfi) => {
          if (!selectedCfi || selectedCfi === '') {
            Swal.showValidationMessage('Please select your instructor.');
            return false;
          }
          return selectedCfi;
        }
      }).then(result => {
        if (result.isConfirmed) {
          const selectedInstructor = result.value;
          startPickFlightTime(selectedFlightType, selectedAircraft, selectedInstructor);
        } else {
          promptChangeCFI(selectedFlightType, selectedAircraft); // Manual override
        }
      });

    })
    .catch(err => {
      console.error('Error loading CFIs:', err);
      Swal.fire({
        icon: 'error',
        title: 'Error Loading Instructors',
        text: 'Please see staff or try again.',
      }).then(() => startCheckOutFlow());
    });
}

function checkSkylistProAvailability(selectedFlightType, selectedAircraft, selectedInstructor, selectedTime) {
  console.log('Checking availability for:', selectedFlightType, selectedAircraft, selectedInstructor, selectedTime);

  const isDual = selectedFlightType?.startsWith('dual');
  if (!isDual && !selectedAircraft && selectedInstructor?.startsWith('N')) {
    selectedAircraft = selectedInstructor;
    selectedInstructor = null;
  }

  const requestData = {
    aircraft_id: selectedAircraft,
    instructor_id: isDual ? selectedInstructor : null,
    start_time: selectedTime, // "08:00"
    date: new Date().toISOString().slice(0, 10),
    flight_type: selectedFlightType
  };

  // Helper to retry fetch once
  function safeFetchWithRetry(url, options, retries = 1) {
    return fetch(url, options).catch(err => {
      if (retries > 0) {
        console.warn(`⚠️ Retrying fetch to ${url}...`);
        return safeFetchWithRetry(url, options, retries - 1);
      } else {
        throw err;
      }
    });
  }

  safeFetchWithRetry('https://skylistpro.com/api/check-availability.php?key=pistonops123', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(requestData)
})

  .then(res => {
    if (!res.ok) throw new Error(`Skylist API failed: ${res.status}`);
    return res.json();
  })
  .then(data => {
    console.log('📡 Skylist API response:', data);

    if (data?.isAvailable) {
      console.log('✅ Aircraft and Instructor available!');
      const manualAppointmentData = {
        flight_type: selectedFlightType,
        staff: [selectedInstructor, selectedAircraft].filter(Boolean),
        start_time: `${requestData.date}T${selectedTime}:00`,
        date: requestData.date,
        student: localStorage.getItem('studentName') || "Manual Booking"
      };
      startFaceVerification(null, manualAppointmentData);
    } else {
      console.warn('❌ Conflict! Not available.');
      handleFlightConflict();
    }
  })
  .catch(err => {
    console.error('❌ Skylist Availability Check Failed:', err);

    Swal.fire({
      icon: 'error',
      title: 'Skylist Connection Issue',
      html: `
        <p>We couldn’t reach the scheduling system just now.</p>
        <p>This might be temporary, but no availability check was made.</p>
      `,
      confirmButtonText: 'Retry',
      cancelButtonText: 'See Staff',
      showCancelButton: true,
      backdrop: `rgba(0,0,123,0.4)
                 url("${getRandomComicBackground()}") center center no-repeat`,
      customClass: { popup: 'swal-theme' }
    }).then(result => {
      if (result.isConfirmed) {
        startCheckOutFlow();
      }
    });
  });
}

function showAppointmentConfirmation(appointmentData, student_id) {
  const { flight_type, staff, start_time, student } = appointmentData;
  const cfiName = staff[0] || 'N/A';
  const aircraft = staff[1] || 'N/A';

  // 🔊 Play success ding
  const audio = new Audio('http://amelia-i.com/wp-content/uploads/2025/04/ding-sfx-330333.mp3');
  audio.play();

  Swal.fire({
    title: '✈️ We Found Your Flight!',
    html: `
      <p><strong>Flight Type:</strong> ${flight_type}</p>
      <p><strong>Instructor:</strong> ${cfiName}</p>
      <p><strong>Aircraft:</strong> ${aircraft}</p>
      <p><strong>Student:</strong> ${student}</p>
    `,
    showCancelButton: true,
    confirmButtonText: 'Confirm',
    cancelButtonText: 'Change Details',
    backdrop: `
      rgba(0,0,123,0.4)
      url("${getRandomComicBackground()}")
      center center
      no-repeat
    `,
    customClass: { popup: 'swal-theme' },
    didOpen: () => {
      const container = Swal.getContainer();
      if (container) container.classList.add('show-background');
    }
  }).then(result => {
    if (result.isConfirmed) {
      continueToCheckout(student_id, student, null);
    } else {
      askFlightType();
    }
  });
}

function askFlightType() {
  const inputOptions = {};
  flightTypeOptions.forEach(option => {
    inputOptions[option.value] = option.label;
  });

  Swal.fire({
    title: '✈️ What type of flight are you here for today?',
    input: 'select',
    inputOptions: inputOptions,
    inputPlaceholder: 'Select flight type',
    showCancelButton: true,
    confirmButtonText: 'Next',
    backdrop: `
      rgba(0,0,123,0.4)
      url("${getRandomComicBackground()}") 
      center center
      no-repeat
    `,
    customClass: { popup: 'swal-theme' },
    didOpen: () => {
      const container = Swal.getContainer();
      if (container) container.classList.add('show-background');
    },
    preConfirm: (selectedValue) => {
      if (!selectedValue) {
        Swal.showValidationMessage('Please select your flight type to continue.');
        return false;
      }
      return selectedValue;
    }
  }).then(result => {
    if (result.isConfirmed) {
      const selectedFlightType = result.value;
      console.log('✅ Flight Type Selected:', selectedFlightType);

      // ✅ LAUNCH NEW FLOW
      startPickAircraft(selectedFlightType);
    } else {
      startCheckOutFlow(); // Cancel goes back to phone
    }
  });
}

function startFaceVerification(student_id, appointmentData) {
  console.log("✅ Starting Face Verification flow...");
  console.log("Student ID:", student_id);
  console.log("Appointment Data:", appointmentData);

  // For now, just auto proceed to showAppointmentConfirmation
  showAppointmentConfirmation(appointmentData, student_id);
}

function promptChangeLocation(selectedFlightType) {
  fetch('fetch-locations.php')
    .then(res => res.json())
    .then(locations => {
      const locationOptions = {};
      locations.forEach(loc => {
        locationOptions[loc.location_code] = `${loc.location_code} — ${loc.location_name}`;
      });

      Swal.fire({
        title: '📍 Change Your Location',
        input: 'select',
        inputOptions: locationOptions,
        inputPlaceholder: 'Select New Location',
        showCancelButton: true,
        confirmButtonText: 'Set Location',
        backdrop: `
          rgba(0,0,123,0.4)
          url("${getRandomComicBackground()}") 
          center center
          no-repeat
        `,
        customClass: { popup: 'swal-theme' }
      }).then(result => {
        if (result.isConfirmed) {
          const newLocation = result.value;
          localStorage.setItem('studentHomeLocation', newLocation);
          startPickAircraft(selectedFlightType); // Relaunch aircraft picker with new location
        } else {
          startCheckOutFlow(); // If they cancel, restart flow
        }
      });
    })
    .catch(err => {
      console.error('Error loading locations:', err);
      Swal.fire({
        icon: 'error',
        title: 'Error Loading Locations',
        text: 'There was a problem loading locations. Please see staff!',
      }).then(() => {
        startCheckOutFlow();
      });
    });
}

function promptChangeCFI(selectedFlightType, selectedAircraft, isChange = false) {
  const studentLocation = localStorage.getItem('studentHomeLocation') || '1H0';

  fetch(`fetch-cfis.php?location=${encodeURIComponent(studentLocation)}`)
    .then(res => res.json())
    .then(cfiData => {
      if (!cfiData || cfiData.length === 0) {
        Swal.fire({
          icon: 'error',
          title: 'No CFIs Available!',
          text: 'No instructors found for your location. Please see staff!',
        }).then(() => {
          startCheckOutFlow();
        });
        return;
      }

      const cfiOptions = {};

      cfiData.forEach(cfi => {
        cfiOptions[cfi.cfi_id] = cfi.cfi_name;
      });

      Swal.fire({
        title: 'Pick a New Instructor',
        input: 'select',
        inputOptions: cfiOptions,
        inputPlaceholder: isChange ? 'Select New CFI' : 'Which Instructor Today?', // Only add placeholder if NOT changing instructor
        showCancelButton: true,
        confirmButtonText: 'Next',
        cancelButtonText: 'Change Instructors',
        backdrop: `
          rgba(0,0,123,0.4)
          url("${getRandomComicBackground()}")
          center center
          no-repeat
        `,
        customClass: { popup: 'swal-theme' },
        preConfirm: (selectedCfi) => {
          if (!selectedCfi || selectedCfi === '') {
            Swal.showValidationMessage('Please select a CFI to continue.');
            return false;
          }
          return selectedCfi;
        }
      }).then(result => {
        if (result.isConfirmed) {
          const selectedInstructor = result.value;
          startPickFlightTime(selectedFlightType, selectedAircraft, selectedInstructor);
        } else {
          startCheckOutFlow(); // Manual override
        }
      });

    })
    .catch(err => {
      console.error('Error loading CFIs:', err);
      Swal.fire({
        icon: 'error',
        title: 'Error Loading CFIs',
        text: 'There was a problem loading instructors. Please see staff!',
      }).then(() => {
        startCheckOutFlow();
      });
    });
}

function startPickFlightTime(selectedFlightType, selectedAircraft, selectedInstructor) {
  const hourOptions = {};
  for (let h = 6; h <= 22; h++) {
    const label = `${h.toString().padStart(2, '0')}:00`;
    hourOptions[label] = label;
  }

  Swal.fire({
    title: '🕑 Pick Your Flight Start Time',
    input: 'select',
    inputOptions: hourOptions,
    inputPlaceholder: 'Select Time',
    showCancelButton: true,
    confirmButtonText: 'Check Availability',
    cancelButtonText: 'Cancel',
    backdrop: `
      rgba(0,0,123,0.4)
      url("${getRandomComicBackground()}")
      center center
      no-repeat
    `,
    customClass: { popup: 'swal-theme' },
    preConfirm: (selectedTime) => {
      if (!selectedTime) {
        Swal.showValidationMessage('Please select a valid start time.');
        return false;
      }
      return selectedTime;
    }
  }).then(result => {
    if (result.isConfirmed) {
      const selectedTime = result.value;
      checkSkylistProAvailability(selectedFlightType, selectedAircraft, selectedInstructor, selectedTime);
    } else {
      startCheckOutFlow(); // cancelled
    }
  });
}
