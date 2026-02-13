<?php
// /wp-content/plugins/checkout.basic/checkout.php
?>
<!DOCTYPE html>
<html>
<head>
  <title>Check In / Check Out</title>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
</head>
<body>
<script>

/**
 * Build SkylistPro Updated Agreement URL (Gravity Forms prefill)
 * Page: https://skylistpro.com/updated-agreement/
 * Field IDs:
 *  - input_1 = Name
 *  - input_3 = Phone
 *  - input_4 = Email
 * Also pass sid for webhook chain.
 */
function buildAgreementUrl(student) {
  const base = 'https://skylistpro.com/updated-agreement/';

  const sid   = student.student_id || '';
  const name  = (student.first_name && student.last_name)
    ? (student.first_name + ' ' + student.last_name)
    : '';

  const phone = student.phone || '';
  const email = student.email || '';

  // 👇 Where they should come back to after signing
  const returnUrl = window.location.href;

  const params = new URLSearchParams();
  params.set('sid', sid);
  params.set('input_1', name);
  params.set('input_3', phone);
  params.set('input_4', email);
  params.set('return', returnUrl);

  return base + '?' + params.toString();
}

/**
 * Show "Agreement Required" modal and redirect to signing page.
 */
function forceAgreement(student) {
  const signUrl = buildAgreementUrl(student);

  Swal.fire({
    icon: 'warning',
    title: 'New Agreement Required',
    html: `
      <p style="margin-bottom:10px;">
        You can’t check aircraft out (or check in) until the updated agreement is signed.
      </p>
      <p style="font-size: 0.95rem; color:#666;">
        Click <strong>Sign Now</strong> to verify your info and sign.
      </p>
    `,
    showCancelButton: true,
    confirmButtonText: '✍️ Sign Now',
    cancelButtonText: 'Not Now'
  }).then((res) => {
    if (res.isConfirmed) {
      window.location.href = signUrl;
    } else {
      location.reload();
    }
  });
}

/**
 * Determines if the response indicates the student must sign.
 * Supports either top-level flags or student object flag.
 */
function needsAgreement(response) {
  if (!response) return false;

  // Accept either boolean flag or 0/1
  if (response.contract_required === true) return true;
  if (typeof response.latest_contract_signed !== 'undefined' && parseInt(response.latest_contract_signed) !== 1) return true;

  // If student object exists
  if (response.student && typeof response.student.latest_contract_signed !== 'undefined' && parseInt(response.student.latest_contract_signed) !== 1) return true;

  return false;
}

/**
 * Extract a normalized student object from either response.student or response top-level fields.
 * This lets us prefill the agreement form reliably.
 */
function extractStudent(response) {
  // Prefer nested student object if available
  if (response && response.student) {
    return response.student;
  }

  // Otherwise build from top-level keys (your "already_checked_out" branch uses top-level fields)
  return {
    student_id: response.student_id,
    first_name: response.first_name,
    last_name: response.last_name,
    phone: response.phone,
    email: response.email
  };
}

function startCheckoutFlow() {
  Swal.fire({
    title: 'Enter Your Phone Number',
    html: `<input type="text" id="phone_input" class="swal2-input" placeholder="(###) ###-####">`,
    preConfirm: () => $('#phone_input').val(),
    didOpen: () => {
      $('#phone_input').mask('(000) 000-0000');
      $('#phone_input').focus();
    },
    confirmButtonText: 'Next'
  }).then(result => {
    if (!result.value) return;
    const phone = result.value;

    $.ajax({
      url: 'verify_phone.php',
      type: 'POST',
      data: { phone },
      dataType: 'json'
    }).done(function(response) {

      // -------------------------------
      // ✅ AGREEMENT GATE (GLOBAL)
      // If verify_phone.php indicates contract is required, block immediately.
      // -------------------------------
      if (needsAgreement(response)) {
        const st = extractStudent(response);

        // If any critical fields are missing, still block, but give a cleaner message
        if (!st.student_id || !st.phone || !st.email) {
          Swal.fire({
            icon: 'warning',
            title: 'Agreement Required',
            text: 'You need to sign the updated agreement, but we could not prefill your info. Please contact dispatch.'
          }).then(() => location.reload());
          return;
        }

        forceAgreement(st);
        return;
      }

      if (response.status === 'not_found') {
        Swal.fire('Not Found', 'We couldn’t find that student.', 'error').then(startCheckoutFlow);
        return;
      }

      if (response.status === 'no_flight_today') {
        Swal.fire({
          icon: 'warning',
          title: 'No Flight Scheduled Today',
          text: response.message || 'Please contact dispatch to book a flight.'
        }).then(startCheckoutFlow);
        return;
      }

      if (response.status === 'already_checked_out') {
        localStorage.setItem('student_id', response.student_id);
        localStorage.setItem('student_name', response.first_name + ' ' + response.last_name);
        localStorage.setItem('student_phone', response.phone);

        Swal.fire({
          title: 'Pre-Check-In Checklist',
          html: `
            <div style="text-align:left">
              <input type="checkbox" id="chk1"> Master switch is OFF<br>
              <input type="checkbox" id="chk2"> Trash removed (bottles, wrappers, etc)<br>
              <input type="checkbox" id="chk3"> IT's HOT!, leave the neck fan for the next pilot - Please<br>
              <input type="checkbox" id="chk4"> Personal items collected:<br>
              <ul style="margin-left:20px">
                <li>🎧 Headset</li>
                <li>📕 Logbook</li>
                <li>📱 iPad</li>
                <li>📞 Phone</li>
                <li>💦 Fuel Tester</li>
                <li>🎒 Backpack</li>
              </ul>
              <p style="font-size: 0.9rem; color: #666;">Piston Aviation is not responsible for forgotten items.</p>
              <p style="margin-top:10px; font-size: 0.9rem; color: #555;">📸 Don’t forget to take a cockpit photo before exiting!</p>
            </div>
          `,
          confirmButtonText: 'Continue to Check In',
          preConfirm: () => {
            if (!$('#chk1').is(':checked') || !$('#chk2').is(':checked') || !$('#chk3').is(':checked') || !$('#chk4').is(':checked')) {
              Swal.showValidationMessage('Please check all boxes before continuing.');
              return false;
            }
          }
        }).then(result => {
          if (!result.isConfirmed) return;

          Swal.fire({
            title: 'Check In',
            html: `
              <p>Welcome back, ${response.first_name}!</p>
              <input id="end_hobbs" class="swal2-input" placeholder="End Hobbs">
              <input id="end_tach" class="swal2-input" placeholder="End Tach">
            `,
            confirmButtonText: 'Submit Check In',
            preConfirm: () => {
              const hobbs = $('#end_hobbs').val();
              const tach = $('#end_tach').val();
              if (!hobbs || !tach) {
                Swal.showValidationMessage('Both Hobbs and Tach are required');
                return false;
              }
              return {
                checkout_id: response.checkout_id,
                end_hobbs: hobbs,
                end_tach: tach
              };
            }
          }).then(res => {
            if (!res.value) return;

            const checkinPayload = res.value; // { checkout_id, end_hobbs, end_tach }

            // Helper to perform check-in (normal or override)
            function submitCheckin(payload, isOverride = false) {
              $.ajax({
                url: 'checkin.php',
                method: 'POST',
                data: payload,
                dataType: 'json'
              })
              .done(function(r) {
                console.log('checkin.php response:', r);

                if (!r || typeof r.status === 'undefined') {
                  Swal.fire('Error', 'Unexpected response from checkin.php.', 'error')
                    .then(() => startCheckoutFlow());
                  return;
                }

                if (r.status === 'success') {
                  const totalToday = parseFloat(r.total_flight_time || 0);
                  showRecommendedFlights(r.recommended_flights || [], totalToday);

                } else if (r.status === 'confirm_required' && !isOverride) {
                  const hobbsDelta = r.hobbs_delta ?? '?';
                  const tachDelta  = r.tach_delta ?? '?';

                  Swal.fire({
                    icon: 'warning',
                    title: 'Numbers Look Unusual',
                    html: `
                      <p>Our system noticed a larger difference between Hobbs and Tach than normal.</p>
                      <p><strong>Hobbs Time:</strong> ${hobbsDelta} hrs<br>
                         <strong>Tach Time:</strong> ${tachDelta} hrs</p>
                      <p>If these numbers are correct, you can confirm and continue.<br>
                         Otherwise, choose "Fix Numbers" and re-enter.</p>
                    `,
                    showCancelButton: true,
                    confirmButtonText: '✅ Confirm & Check In',
                    cancelButtonText: '🔁 Fix Numbers'
                  }).then(decision => {
                    if (decision.isConfirmed) {
                      const overridePayload = Object.assign({}, checkinPayload, { override: '1' });
                      submitCheckin(overridePayload, true);
                    } else {
                      startCheckoutFlow();
                    }
                  });

                } else {
                  Swal.fire('Error', r.message || 'Check-in failed.', 'error')
                    .then(() => startCheckoutFlow());
                }
              })
              .fail(function(xhr, status, errorThrown) {
                console.log('checkin.php AJAX fail:', status, xhr.status, xhr.responseText);
                Swal.fire(
                  'Server Error',
                  xhr.responseText || 'Something went wrong (checkin.php).',
                  'error'
                ).then(() => startCheckoutFlow());
              });
            }

            // First attempt (normal check-in, no override)
            submitCheckin(checkinPayload, false);
          });

        });

        return;
      }

      if (response.status === 'not_enough_hours') {
        const s = response.student;
        const requiredAircraft = response.required_aircraft || 1.5;
        const requiredInstructor = response.required_instructor || 0;

        const payUrl = `https://skylistpro.com/need-more-hours/` +
          `?nm=${encodeURIComponent(s.first_name + ' ' + s.last_name)}` +
          `&ph=${encodeURIComponent(s.phone)}` +
          `&email=${encodeURIComponent(s.email)}` +
          `&phase=${encodeURIComponent(s.phase_pay)}` +
          `&type=${encodeURIComponent(s.type)}` +
          `&installment_price=${encodeURIComponent(s.installment_price || '')}` +
          `&iah=${encodeURIComponent(s.installment_flight_hours || '')}` +
          `&iih=${encodeURIComponent(s.installment_instructor_hours || '')}` +
          `&cab=${encodeURIComponent(s.aircraft_hours_remaining)}` +
          `&cib=${encodeURIComponent(s.instructor_hours_remaining)}` +
          `&ileft=${encodeURIComponent(s.installments_remaining || '')}`;

        Swal.fire({
          icon: 'warning',
          title: `Hi ${s.first_name},`,
          html: `
            <h3 style="margin-bottom: 15px;">Not Enough Hours</h3>
            <p>You currently have:</p>
            <ul style="text-align:left">
              <li><strong>Aircraft Hours:</strong> ${s.aircraft_hours_remaining}</li>
              <li><strong>Instructor Hours:</strong> ${s.instructor_hours_remaining}</li>
            </ul>
            <p>Required to book:</p>
            <ul style="text-align:left">
              <li><strong>Aircraft:</strong> ${requiredAircraft}</li>
              ${requiredInstructor > 0 ? `<li><strong>Instructor:</strong> ${requiredInstructor}</li>` : ''}
            </ul>
            <div style="margin-top: 20px;">
              <button id="buyTimeBtn" class="swal2-confirm swal2-styled" style="margin-right:10px;">Buy Time and Fly</button>
              <button id="declineBtn" class="swal2-cancel swal2-styled">I won’t fly today</button>
            </div>
          `,
          showConfirmButton: false,
          didOpen: () => {
            document.getElementById('buyTimeBtn').addEventListener('click', () => {
              window.open(payUrl, '_blank');
            });
            document.getElementById('declineBtn').addEventListener('click', () => {
              Swal.fire({
                icon: 'info',
                title: 'No problem!',
                text: 'You can always book later.',
              }).then(() => location.reload());
            });
          }
        });
        return;
      }

      if (response.status === 'ok') {
        const student = response.student;

        $.get('fetch_aircraft_by_home.php', { home_airport: student.home_airport }, function(aircraftList) {
          let aircraftOptions = '<option value="">Select Aircraft</option>' + aircraftList.map(tail => `<option value="${tail}">${tail}</option>`).join('');

          Swal.fire({
            title: `Hi ${student.first_name}, Let's Get you Checked Out and Flying`,
            html: `
              <select id="aircraftSelect" class="swal2-select">${aircraftOptions}</select>
              <div style="font-size: 12px; color: #888; margin-bottom: 10px;">
                On mobile: tap inside box to make selection
              </div>

              <label>What type of flight today?</label>
              <select id="flightType" class="swal2-select">
                <option value="">Select Flight Type</option>
                <option value="Dual Training">Dual Training</option>
                <option value="Dual Cross Country">Dual Cross Country</option>
                <option value="Solo Training Flight">Solo Training Flight</option>
                <option value="Solo Cross Country">Solo Cross Country</option>
                <option value="Rental Solo - Local">Rental Solo - Local</option>
                <option value="Rental Cross Country">Rental Cross Country</option>
                <option value="Discovery Flight">Discovery Flight</option>
                <option value="Checkride">Checkride</option>
              </select>
              <div style="font-size: 12px; color: #888; margin-bottom: 10px;">
                On mobile: tap inside box to make selection
              </div>

              <div id="cfiWrapOuter" style="margin-top:15px; display:none;">
                <label>Who is your CFI today?</label>
                <select id="cfiSelect" class="swal2-select">
                  <option value="">Select CFI</option>
                </select>
                <div style="font-size: 12px; color: #888; margin-bottom: 10px;">
                  On mobile: tap inside box to make selection
                </div>
              </div>

              <style>
                .swal2-select {
                  border: 2px solid #555 !important;
                  border-radius: 6px;
                  padding: 8px;
                  background-color: #fff;
                }
              </style>
            `,
            confirmButtonText: 'Next',
            didOpen: () => {
              $('#flightType').on('change', function () {
                const val = $(this).val();
                if (val.includes('Dual') || val === 'Checkride') {
                  $.get('fetch_cfis_by_home.php', { home_airport: student.home_airport }, function(cfis) {
                    if (!Array.isArray(cfis)) {
                      console.error('Invalid response from fetch_cfis_by_home.php:', cfis);
                      $('#cfiWrapOuter').hide();
                      return;
                    }

                    if (cfis.length === 0) {
                      $('#cfiWrapOuter').hide();
                      return;
                    }

                    const options = '<option value="">Select CFI</option>' + cfis.map(c => `<option value="${c.cfi_id}">${c.name}</option>`).join('');
                    $('#cfiSelect').html(options);

                    $('#cfiWrapOuter').show();
                    setTimeout(() => Swal.update(), 50);
                  }, 'json');

                } else {
                  $('#cfiWrapOuter').hide();
                  setTimeout(() => Swal.update(), 50);
                }
              });

              setTimeout(() => $('#flightType').trigger('change'), 100);
            }
          }).then(res => {
            if (!res.isConfirmed) return;

            const tail = $('#aircraftSelect').val();
            const type = $('#flightType').val();
            const cfiRequired = $('#cfiWrapOuter').is(':visible');
            const cfiId = cfiRequired ? $('#cfiSelect').val() : null;

            if (!tail || !type || (cfiRequired && !cfiId)) {
              Swal.fire('Missing Info', 'Please make all selections before continuing.', 'warning');
              return;
            }

            Swal.fire({
              title: 'Start Hobbs & Tach',
              html: `
                <input id="hobbs" class="swal2-input" placeholder="Start Hobbs">
                <input id="tach" class="swal2-input" placeholder="Start Tach">
              `,
              confirmButtonText: 'Checkout Aircraft',
              preConfirm: () => ({
                hobbs: parseFloat($('#hobbs').val()) || 0,
                tach: parseFloat($('#tach').val()) || 0
              })
            }).then(res => {
              if (!res.value) return;
              const { hobbs, tach } = res.value;

              $.ajax({
                url: 'logging_checkout.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                  student_id: student.student_id,
                  cfi_id: cfiId,
                  tail_number: tail,
                  appointment_type: type,
                  start_hobbs: hobbs,
                  start_tach: tach
                }),
                success: () => {
                  Swal.fire('✅ Checked Out', `You're checked out, ${student.first_name}. Fly safe!`, 'success').then(startCheckoutFlow);
                },
                error: () => {
                  Swal.fire('Error', 'There was an error logging your checkout.', 'error');
                }
              });
            });
          });
        }, 'json');
      }
    }).fail(function(xhr) {
      Swal.fire('Server Error', `verify_phone.php failed:<br><code>${xhr.responseText}</code>`, 'error');
    });
  });
}

startCheckoutFlow();

function showRecommendedFlights(slots, totalHoursToday = 0) {
  console.log("📦 Recommended slots:", slots);

  if (!slots.length) {
    Swal.fire('No Suggestions', 'We couldn’t find any good openings right now. Try the full scheduler.', 'info');
    return;
  }

  if (slots[0]?.aircraft) {
    localStorage.setItem('aircraft_tail', slots[0].aircraft);
  }

  let html = slots.map((slot) => {
    const start = new Date(slot.start_time);
    const end = new Date(slot.end_time);
    const dateStr = start.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
    const timeStr = start.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) + ' – ' +
                    end.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });

    const cfiInfo = slot.cfi_name && slot.cfi_name !== 'N/A' ? `👨‍🏫 ${slot.cfi_name}` : '';
    const planeInfo = `✈️ ${slot.aircraft}`;

    return `
      <button class="swal2-confirm swal2-styled" style="margin:6px; width:100%"
        onclick="bookRecommended('${slot.start_time}', '${slot.end_time}', '${slot.aircraft}', ${slot.cfi_id || 'null'})">
        📅 ${dateStr} — ${timeStr}<br>${planeInfo} ${cfiInfo ? '— ' + cfiInfo : ''}
      </button>`;
  }).join('');

  Swal.fire({
    title: `🚀 ${Number(totalHoursToday).toFixed(2)} hrs Today<br><span style="font-size:22px; display:inline-block; margin-top:6px;">✅ Check-In Complete — Pick Your Next Flight</span>`,
    html: html + `
      <hr>
      <button class="swal2-cancel swal2-styled" onclick="showSoloOption()">🛩 Solo Flights Only</button>
      <button class="swal2-cancel swal2-styled" onclick="showAircraftChooser()">✈️ Pick Another Aircraft</button>
      <button class="swal2-cancel swal2-styled" onclick="launchFullScheduler()">📅 Full Scheduler</button>
    `,
    showConfirmButton: false
  });
}

function bookRecommended(start, end, tailNumber, cfiId) {
  $.post('schedule_flight.php', {
    student_id: localStorage.getItem('student_id'),
    cfi_id: cfiId,
    tail_number: tailNumber,
    start_time: start,
    end_time: end
  }).done(function(res) {
    if ((res.status || '').toLowerCase() === 'success') {
      Swal.fire('✅ Booked!', 'Your next flight is on the schedule.', 'success')
        .then(() => location.reload());
    } else {
      Swal.fire('Error', res.message || 'Could not schedule flight.', 'error');
    }
  }).fail(function(xhr) {
    Swal.fire('Server Error', xhr.responseText || 'Something went wrong.', 'error');
  });
}

function showSoloOption() {
  const tail = localStorage.getItem('aircraft_tail') || 'N7970F';
  const studentId = localStorage.getItem('student_id');

  $.post('get_solo_slots.php', {
    tail_number: tail,
    student_id: studentId
  }, function(response) {
    if ((response.status || '').toLowerCase() === 'success') {
      showRecommendedFlights(response.slots || []);
    } else {
      Swal.fire('No Solo Flights', response.message || 'Could not fetch solo flights.', 'info');
    }
  }, 'json');
}

function showAircraftChooser() {
  $.get('fetch_all_aircraft.php', function(tails) {
    const html = `
      <label>Select Aircraft:</label>
      <select id="aircraftPicker" class="swal2-select">
        ${tails.map(tail => `<option value="${tail}">${tail}</option>`).join('')}
      </select>
    `;

    Swal.fire({
      title: 'Pick Another Aircraft',
      html: html,
      confirmButtonText: 'Show Flights',
      preConfirm: () => $('#aircraftPicker').val()
    }).then(result => {
      if (!result.isConfirmed || !result.value) return;
      const tail = result.value;
      localStorage.setItem('aircraft_tail', tail);

      $.post('get_solo_slots.php', {
        tail_number: tail,
        student_id: localStorage.getItem('student_id')
      }, function(response) {
        if ((response.status || '').toLowerCase() === 'success') {
          showRecommendedFlights(response.slots || []);
        } else {
          Swal.fire('No Flights', response.message || 'Could not fetch solo flights.', 'info');
        }
      }, 'json');
    });
  }, 'json');
}

function launchFullScheduler() {
  const phone = localStorage.getItem('student_phone') || '';
  const clean = phone.replace(/\D/g, '');
  window.location.href = `https://amelia-i.com/wp-content/plugins/calendar.v2/appointment-booking/index.html?phone=${encodeURIComponent(clean)}`;
}

</script>
</body>
</html>
