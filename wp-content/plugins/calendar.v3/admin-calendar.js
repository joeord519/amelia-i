// ✅ Final admin-calendar.js with fixed student search injection for Ground Lessons
// ✅ Full Working admin-calendar.js — updated to fix event labels, click behavior, slot selection, and date persistence
let calendarInstance = null;
let allEvents = [];
let selectedResourceIds = new Set();

async function launchLoginModal() {
  const { value: rawPhone } = await Swal.fire({
    title: "CFI/Admin Login",
    input: "tel",
    inputPlaceholder: "(###) ###-####",
    inputAttributes: {
      autofocus: true,
      maxlength: 14
    },
    confirmButtonText: "Login",
    allowOutsideClick: false,
    allowEscapeKey: false,
    preConfirm: async (raw) => {
      const cleaned = raw.replace(/\D/g, '').substring(0, 10);
      if (cleaned.length < 10) {
        Swal.showValidationMessage("Please enter a valid 10-digit phone number.");
        return false;
      }

      const formatted = `(${cleaned.substring(0, 3)}) ${cleaned.substring(3, 6)}-${cleaned.substring(6, 10)}`;
      const res = await fetch("/wp-content/plugins/calendar.v3/login.php?phone=" + encodeURIComponent(formatted));
      const data = await res.json();

      if (data.success) {
        document.cookie = "user_phone=" + encodeURIComponent(data.phone);
        document.cookie = "user_name=" + encodeURIComponent(data.name);
        document.cookie = "user_role=" + encodeURIComponent(data.role);
        startSessionTimeout();
        return data;
      } else {
        Swal.showValidationMessage("Login failed. Check your phone number.");
        return false;
      }
    }
  });

  if (rawPhone) {
  showUserInfo();
  const currentDate = calendarInstance?.getDate?.();
  if (currentDate) {
    localStorage.setItem("calendarFocusDate", currentDate.toISOString());
  }
  location.reload();
}

}


function getCookieValue(name) {
  const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
  return match ? decodeURIComponent(match[2]) : null;
}

function showUserInfo() {
  const name = getCookieValue("user_name");
  if (name) {
    const target = document.getElementById("userNameDisplay");
    if (target) target.textContent = name;
  }

  const role = getCookieValue("user_role");
  if (role && document.getElementById("userRoleDisplay")) {
    document.getElementById("userRoleDisplay").textContent = role.toUpperCase();
  }
}

function startSessionTimeout(minutes = 15) {
  setTimeout(() => {
    Swal.fire({
      icon: "warning",
      title: "Session Expired",
      text: "Please log in again.",
      confirmButtonText: "OK"
    }).then(() => logout());
  }, minutes * 60 * 1000);
}

function logout() {
  const expire = "expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
  document.cookie = "user_phone=;" + expire;
  document.cookie = "user_name=;" + expire;
  document.cookie = "user_role=;" + expire;
  const currentDate = calendarInstance?.getDate?.();
if (currentDate) {
  localStorage.setItem("calendarFocusDate", currentDate.toISOString());
}
location.reload();

}

// Attach logout click handler after DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  const logoutLink = document.getElementById("logoutLink");
  if (logoutLink) {
    logoutLink.addEventListener("click", function (e) {
      e.preventDefault();
      logout();
    });
  }
});

function formatLocalDateTime(date) {
  const yyyy = date.getFullYear();
  const MM = String(date.getMonth() + 1).padStart(2, '0');
  const dd = String(date.getDate()).padStart(2, '0');
  const hh = String(date.getHours()).padStart(2, '0');
  const mm = String(date.getMinutes()).padStart(2, '0');
  const ss = String(date.getSeconds()).padStart(2, '0');
  return `${yyyy}-${MM}-${dd} ${hh}:${mm}:${ss}`;
}


  const renderCalendar = (focusedDate = new Date(), viewType = "resourceTimelineDay") => {
    const calendarEl = document.getElementById("calendar");
    calendarEl.innerHTML = "";

    // ✅ Add a note for session-created flights
const calendarContainer = document.getElementById("calendarContainer");
if (calendarContainer && !document.getElementById("sessionNote")) {
  const note = document.createElement("div");
  note.id = "sessionNote";
  note.textContent = "🔹 Flights created this session appear in blue until the calendar is refreshed.";
  calendarContainer.insertBefore(note, calendarContainer.firstChild);

  // ✅ Trigger fade-in with CSS class
  setTimeout(() => {
    note.classList.add("fade-in");
  }, 10); // slight delay to allow DOM render

  // ✅ Fade out after 6 seconds
  setTimeout(() => {
    note.classList.remove("fade-in");
    note.classList.add("fade-out");
  }, 6000);

  // ✅ Remove from DOM after fade-out completes
  setTimeout(() => {
    note.remove();
  }, 7500);
}
    calendarInstance = new FullCalendar.Calendar (calendarEl, {
      schedulerLicenseKey: "GPL-My-Project-Is-Open-Source",
      initialView: viewType,
      slotMinTime: "06:00:00",
      slotMaxTime: "22:00:00",
      nowIndicator: true,
      editable: true,
      selectable: true,
      resourceAreaHeaderContent: "CFIs & Aircraft",

      resources: function (fetchInfo, successCallback, failureCallback) {
        fetch("get-resources.php")
          .then(res => res.json())
          .then(data => {
            if (selectedResourceIds.size === 0) {
              data.forEach(r => selectedResourceIds.add(r.id));
            }
            const filtered = data.filter(r => selectedResourceIds.has(r.id));
            successCallback(filtered);
          })
          .catch(err => failureCallback(err));
      },

      events: allEvents,

      eventAllow: function (dropInfo, draggedEvent) {
        const group = dropInfo.resource.extendedProps?.group;
        return group === "CFI" || group === "Aircraft";
      },

      eventDrop: function (info) {
  const ep = info.event.extendedProps;
  const paired = calendarInstance.getEventById(ep.paired_event_id);

  if (paired) {
    paired.setStart(info.event.start);
    paired.setEnd(info.event.end);
  }

  Swal.fire({
    title: "Reason for Rescheduling",
    input: "text",
    inputPlaceholder: "Enter reason (optional)",
    showCancelButton: true,
    confirmButtonText: "Submit",
    cancelButtonText: "Cancel"
  }).then((result) => {
    if (!result.isConfirmed) {
      info.revert(); // ✅ cancel the drag visually
      return;
    }

    const reason = result.value || "No reason provided";

    fetch("update_flight.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        id: ep.flight_id,
        start: formatLocalDateTime(info.event.start),
        end: formatLocalDateTime(info.event.end),
        reason: reason
      })
    })
    .then(res => res.json())
    .then(resp => {
      if (!resp.success) {
        Swal.fire("Error", "Update failed.", "error");
        info.revert(); // ✅ drag back if save fails
      } else {
        Swal.fire({
          icon: "success",
          title: "Flight Updated",
          text: "The flight time has been updated.",
          timer: 1800,
          showConfirmButton: false
        });
        // ✅ Leave the event in place — no reload
      }
    });
  });
},

      eventClick: async function (info) {
  const ep = info.event.extendedProps;
  const title = info.event.title || "Booking";

  const isGround = title.toLowerCase().includes("ground");

  let html = `<div style="text-align: left;">`;

  if (isGround && Array.isArray(ep.students)) {
    html += `<strong>Students:</strong><br>`;
    ep.students.forEach(s => {
      html += `${s.name} - ${s.phone}<br>`;
    });
  } else {
    html += `
      <strong>Student:</strong> ${ep.student_name || 'N/A'}<br>
      <strong>Phone:</strong> ${ep.student_phone || 'N/A'}<br>
      <strong>Tail:</strong> ${ep.tail_number || 'N/A'}<br>
    `;
  }

  html += `<strong>CFI:</strong> ${ep.cfi_name || 'N/A'}</div>`;

  const result = await Swal.fire({
    title: title,
    html: html,
    showCancelButton: false,
    showDenyButton: true,
    confirmButtonText: 'Delete',
    denyButtonText: 'Close',
    confirmButtonColor: '#8b5cf6',
    denyButtonColor: '#ef4444'
  });

  if (result.isConfirmed) {
    const { value: reason } = await Swal.fire({
      title: 'Reason for Deletion',
      input: 'text',
      inputLabel: 'Optional Reason:',
      inputPlaceholder: 'Enter reason...',
      showCancelButton: true,
      confirmButtonText: 'Confirm Delete'
    });

    fetch("delete_flight.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        id: ep.flight_id,
        reason: reason || "No reason provided"
      })
    })
      .then(res => res.json())
      .then(resp => {
        if (resp.success) {
          Swal.fire("Deleted", "Booking removed successfully", "success");
          info.event.remove(); // ✅ live remove
        } else {
          Swal.fire("Error", resp.message || "Deletion failed.", "error");
        }
      });
  }
},

  select: function (info) {
  if (!info.resource || !info.resource.id) return;

  const start = new Date(info.start);
  const h = String(start.getHours()).padStart(2, '0');
  const m = String(start.getMinutes()).padStart(2, '0');
  const clickedTime = `${h}:${m}`;
  const clickedDate = start.getFullYear() + '-' +
    String(start.getMonth() + 1).padStart(2, '0') + '-' +
    String(start.getDate()).padStart(2, '0');

  Swal.fire({
    title: "What type of booking?",
    showCancelButton: true,
    showDenyButton: true,
    confirmButtonText: "✈️ Flight Lesson",
    denyButtonText: "📘 Ground Lesson",
    cancelButtonText: "Cancel"
  }).then(result => {
    if (result.isConfirmed) {
      window.launchCreateBookingModal({
        resourceId: info.resource.id,
        date: clickedDate,
        time: clickedTime
      });
    } else if (result.isDenied) {
      window.launchGroundLessonModal({
        resourceId: info.resource.id,
        date: clickedDate,
        time: clickedTime
      });
    }
  });
},

    });

    calendarInstance.render();
    calendarInstance.gotoDate(focusedDate);
  };

function loadAdminCalendar() {
  const { Calendar } = FullCalendar;

  const storedDate = localStorage.getItem("calendarFocusDate");
  const currentDate = storedDate ? new Date(storedDate) : new Date();

  const loadData = async () => {
    const eventRes = await fetch("get-events.php?cb=" + Date.now());
    const { events } = await eventRes.json();
    allEvents = events || [];
    renderCalendar(currentDate);
    localStorage.removeItem("calendarFocusDate"); // ✅ reset after render
  };

  if (!document.cookie.includes("user_phone")) {
    launchLoginModal();
    return;
  }

  loadData();
}

function launchCreateBookingModal(prefill = {}) {
  fetch("fetch_flight_types_admin.php")
    .then(res => res.json())
    .then(types => {
      const flightOptions = `<option disabled selected value="">-- Select Flight Type --</option>` +
        types
          .filter(t => !t.name.toLowerCase().includes("ground"))
          .map(t => `<option value="${t.id}">${t.name}</option>`).join('');
      Swal.fire({
        title: "Add New Flight Booking",
        html: `
          <div style="display: flex; flex-direction: column; gap: 8px;">
            <select id="adminFlightType" class="swal2-select">${flightOptions}</select>
            <input id="groundSearchInput" class="swal2-input" placeholder="Search for students">
            <div id="groundSearchResults" style="max-height: 100px; overflow-y: auto; font-size: 0.9rem;"></div>
            <div id="selectedGroundStudents" style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 6px;"></div>
            <small id="groundLimitNote" style="color: #4b5563; font-size: 0.8rem;">You may select up to 4 students for this ground lesson.</small>
            <input id="adminStudentSearch" class="swal2-input" placeholder="Search Student by Last Name">
            <select id="adminStudentDropdown" class="swal2-select" style="display:none;"></select>
            <input id="adminStudentPhone" type="hidden">
            <input id="futureStudentName" class="swal2-input" placeholder="Future Student Name" style="display:none;">
            <input id="futureStudentPhone" class="swal2-input" placeholder="Future Student Phone #" style="display:none;">
            <input id="adminTailNumber" class="swal2-input" placeholder="Tail Number (if required)">
            <input id="cfiSearch" class="swal2-input" placeholder="Search CFI by Name">
            <select id="adminCFI" class="swal2-select" style="display:none;"></select>
            <select id="adminLocation" class="swal2-select"><option disabled selected>-- Select Location --</option></select>
            <input type="date" id="adminDate" class="swal2-input">
            <select id="adminTime" class="swal2-select">
              ${[...Array(33)].map((_, i) => {
                const totalMinutes = 360 + i * 30;
                const h = String(Math.floor(totalMinutes / 60)).padStart(2, '0');
                const m = totalMinutes % 60 === 0 ? '00' : '30';
                return `<option value="${h}:${m}">${h}:${m}</option>`;
              }).join('')}
            </select>
          </div>`,
        didOpen: () => {
          const sBox = document.getElementById("adminStudentSearch");
          const sDrop = document.getElementById("adminStudentDropdown");
          const sPhone = document.getElementById("adminStudentPhone");
          const cfiBox = document.getElementById("cfiSearch");
          const cfiDrop = document.getElementById("adminCFI");
          const locDrop = document.getElementById("adminLocation");
          const fType = document.getElementById("adminFlightType");
          const fName = document.getElementById("futureStudentName");
          const fPhone = document.getElementById("futureStudentPhone");

          fPhone.addEventListener('input', function (e) {
            let x = e.target.value.replace(/\D/g, '').substring(0, 10);
            const area = x.substring(0, 3);
            const mid = x.substring(3, 6);
            const last = x.substring(6, 10);
            if (x.length > 6) {
              e.target.value = `(${area}) ${mid}-${last}`;
            } else if (x.length > 3) {
              e.target.value = `(${area}) ${mid}`;
            } else if (x.length > 0) {
              e.target.value = `(${area}`;
            }
          });

          sBox.addEventListener("input", function () {
            fetch("search_students.php?last=" + encodeURIComponent(this.value))
              .then(res => res.json())
              .then(matches => {
                if (!Array.isArray(matches)) return;
                sDrop.style.display = "block";
                sDrop.innerHTML = matches.map(s =>
                  `<option data-id="${s.student_id}" value="${s.phone}">${s.name} - ${s.phone}</option>`
                ).join('');
                sDrop.onchange = () => {
                  sPhone.value = sDrop.value;
                };
              });
          });

          cfiBox.addEventListener("input", function () {
            fetch("search_cfis.php?name=" + encodeURIComponent(this.value))
              .then(res => res.json())
              .then(results => {
                if (!Array.isArray(results)) return;
                cfiDrop.style.display = "block";
                cfiDrop.innerHTML = results.map(cfi =>
                  `<option value="${cfi.id}">${cfi.name}</option>`
                ).join('');
              });
          });

          fetch("fetch_locations.php")
            .then(res => res.json())
            .then(locations => {
              locDrop.innerHTML = `<option disabled selected value="">-- Select Location --</option>` +
                locations.map(loc => `<option value="${loc.airport_code}">${loc.name}</option>`).join('');
            });

          setTimeout(() => {
            const checkDiscovery = () => {
              const selectedText = fType.options[fType.selectedIndex]?.text.toLowerCase() || '';
              const isDiscovery = selectedText.includes("discovery");
              fName.style.display = isDiscovery ? "block" : "none";
              fPhone.style.display = isDiscovery ? "block" : "none";
              sBox.style.display = isDiscovery ? "none" : "block";
              sDrop.style.display = "none";
              if (!isDiscovery) {
                fName.value = "";
                fPhone.value = "";
              }
            };
            fType.addEventListener("change", checkDiscovery);
            checkDiscovery();
          }, 100);

          setTimeout(() => {
            if (prefill.date) {
              const dateField = document.getElementById("adminDate");
              dateField.value = prefill.date;
              dateField.setAttribute("readonly", "readonly");
            }
            if (prefill.time) {
              document.getElementById("adminTime").value = prefill.time;
            }
            if (prefill.resourceId) {
              const cfiSelect = document.getElementById("adminCFI");
              if (cfiSelect) cfiSelect.value = prefill.resourceId;
            }
          }, 150);

          // Fetch full student list for ground lessons

// Show/hide based on flight type
setTimeout(() => {
  const checkGround = () => {
  const selectedText = fType.options[fType.selectedIndex]?.text.toLowerCase() || '';
  const isGround = selectedText.includes("ground");

  const groundBox = document.getElementById("groundSearchInput");
  const resultsBox = document.getElementById("groundSearchResults");
  const selectedBox = document.getElementById("selectedGroundStudents");
  const limitNote = document.getElementById("groundLimitNote");

  const classicSearch = document.getElementById("adminStudentSearch");
  const classicDropdown = document.getElementById("adminStudentDropdown");
  const tailInput = document.getElementById("adminTailNumber");

  // Always hide by default
  groundBox.style.display = "none";
  resultsBox.style.display = "none";
  selectedBox.style.display = "none";
  limitNote.style.display = "none";

  classicSearch.style.display = "block";
  classicDropdown.style.display = "none";
  tailInput.style.display = "block";

  if (isGround) {
    groundBox.style.display = "block";
    resultsBox.style.display = "block";
    selectedBox.style.display = "flex";
    limitNote.style.display = "block";

    classicSearch.style.display = "none";
    tailInput.style.display = "none";
  }
};

  fType.addEventListener("change", checkGround);
  checkGround();
}, 100);


        },
        confirmButtonText: "Create Flight",
        showCancelButton: true,
        preConfirm: async () => {
          const tail = document.getElementById("adminTailNumber").value.trim().toUpperCase();
          if (tail) {
            const valid = await fetch("validate_tail.php?tail=" + encodeURIComponent(tail))
              .then(res => res.json()).then(res => res.valid);
            if (!valid) {
              Swal.showValidationMessage("Invalid or unrecognized tail number.");
              return false;
            }
          }

          const studentSelect = document.getElementById("adminStudentDropdown");
          const selectedOption = studentSelect.options[studentSelect.selectedIndex];
          const studentId = selectedOption?.dataset?.id || null;

          return {
            phone: document.getElementById("adminStudentPhone").value.trim(),
            studentId: studentId,
            flightTypeId: document.getElementById("adminFlightType").value,
            tailNumber: tail || null,
            cfiId: document.getElementById("adminCFI").value || null,
            home_airport: document.getElementById("adminLocation").value,
            date: document.getElementById("adminDate").value,
            time: document.getElementById("adminTime").value,
            futureName: document.getElementById("futureStudentName").value.trim(),
            futurePhone: document.getElementById("futureStudentPhone").value.trim()
          };
        }
      }).then(result => {
        if (result.isConfirmed) {
          Swal.fire({
            title: "Booking...",
            didOpen: () => Swal.showLoading()
          });

          fetch("/wp-content/plugins/calendar.v3/create_flight_admin.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(result.value)
          })
            .then(res => res.json())
            .then(resp => {
              if (resp.success && Array.isArray(resp.events)) {
                resp.events.forEach(ev => {
                  calendarInstance.addEvent({
                    id: ev.id,
                    title: ev.title,
                    start: ev.start,
                    end: ev.end,
                    resourceId: ev.resourceId,
                    backgroundColor: ev.backgroundColor || '#007BFF',
                    borderColor: ev.borderColor || '#007BFF',
                    textColor: ev.textColor || '#ffffff',
                    extendedProps: ev.extendedProps || {}
                  });
                });

                Swal.fire("✅ Booked!", "Flight has been created.", "success");
              } else {
                Swal.fire("Error", resp.message || "Booking failed.", "error");
              }
            })
            .catch(err => {
              console.error("Booking failed:", err);
              Swal.fire("Error", "Could not book flight.", "error");
            });
        }
      });
    });
}

function launchGroundLessonModal(prefill = {}) {
  Swal.fire({
    title: "New Ground Lesson",
    html: `
  <div style="display:flex; flex-direction:column; align-items:center; gap:10px;">
    <input type="date" id="glDate" class="swal2-input" style="text-align:center;">
    <select id="glTime" class="swal2-select" style="text-align:center;">
      ${[...Array(33)].map((_, i) => {
        const mins = 360 + i * 30;
        const h = String(Math.floor(mins / 60)).padStart(2, '0');
        const m = mins % 60 === 0 ? '00' : '30';
        return `<option value="${h}:${m}">${h}:${m}</option>`;
      }).join('')}
    </select>
    <select id="glDuration" class="swal2-select" style="text-align:center;">
      <option value="60">60 minutes</option>
      <option value="90">90 minutes</option>
      <option value="120">120 minutes</option>
    </select>
    <input id="glCfiSearch" class="swal2-input" placeholder="Search CFI" style="text-align:center;">
    <select id="glCfiSelect" class="swal2-select" style="display:none; text-align:center;"></select>
    <input id="glStudentSearch" class="swal2-input" placeholder="Search Student" style="text-align:center;">
    <div id="glStudentResults" style="max-height:100px; overflow-y:auto; font-size:0.9rem; width:100%; text-align:left;"></div>
    <div id="glSelectedStudents" style="margin-top:10px; display:flex; flex-wrap:wrap; gap:6px; justify-content:center;"></div>
    <small style="text-align:center;">You may select up to 4 students.</small>
  </div>
`,

    didOpen: () => {
  const dateInput = document.getElementById("glDate");
  const timeInput = document.getElementById("glTime");
  const cfiInput = document.getElementById("glCfiSearch");
  const cfiDropdown = document.getElementById("glCfiSelect");
  const studentSearch = document.getElementById("glStudentSearch");
  const studentResults = document.getElementById("glStudentResults");
  const selectedBox = document.getElementById("glSelectedStudents");

  const selectedStudents = [];
  Swal.getPopup().selectedStudents = selectedStudents; // ✅ make it accessible to preConfirm

  // Autofill
  if (prefill.date) {
    dateInput.value = prefill.date;
    dateInput.setAttribute("readonly", true);
  }
  if (prefill.time) timeInput.value = prefill.time;

  // Load CFI if passed
  if (prefill.resourceId) {
    cfiDropdown.innerHTML = `<option value="${prefill.resourceId}">${prefill.resourceId}</option>`;
    cfiDropdown.value = prefill.resourceId;
    cfiDropdown.style.display = "block";
  }

  // Student Search
  studentSearch.addEventListener("input", () => {
    fetch("search_students.php?last=" + encodeURIComponent(studentSearch.value))
      .then(res => res.json())
      .then(students => {
        studentResults.innerHTML = students.map(s =>
          `<div class="student-option" data-id="${s.student_id}" data-name="${s.name}" data-phone="${s.phone}" style="cursor:pointer; padding:4px;">${s.name} - ${s.phone}</div>`
        ).join('');

        document.querySelectorAll(".student-option").forEach(el => {
          el.onclick = () => {
            const id = el.dataset.id;
            const name = el.dataset.name;
            const phone = el.dataset.phone;

            if (selectedStudents.length >= 4 || selectedStudents.some(s => s.id === id)) return;

            selectedStudents.push({ id, name, phone });

            const pill = document.createElement("div");
            pill.textContent = name;
            pill.style.cssText = "background:#2563eb;color:white;padding:4px 8px;border-radius:4px;cursor:pointer;";
            pill.onclick = () => {
              selectedBox.removeChild(pill);
              const idx = selectedStudents.findIndex(s => s.id === id);
              if (idx !== -1) selectedStudents.splice(idx, 1);
            };
            selectedBox.appendChild(pill);
          };
        });
      });
  });

  // CFI Search
  cfiInput.addEventListener("input", () => {
    fetch("search_cfis.php?name=" + encodeURIComponent(cfiInput.value))
      .then(res => res.json())
      .then(cfis => {
        cfiDropdown.style.display = "block";
        cfiDropdown.innerHTML = cfis.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
      });
  });
},
showCancelButton: true,
cancelButtonText: "Cancel",
confirmButtonText: "Create Lesson",
preConfirm: () => {
  const date = document.getElementById("glDate").value;
  const time = document.getElementById("glTime").value;
  const duration = parseInt(document.getElementById("glDuration").value);
  const cfiId = document.getElementById("glCfiSelect").value;
  const selectedStudents = Swal.getPopup().selectedStudents || [];

  if (!date || !time || !duration || !cfiId || selectedStudents.length === 0) {
    Swal.showValidationMessage("All fields required and at least 1 student.");
    return false;
  }

  return { date, time, duration, cfiId, students: selectedStudents };
}

  }).then(result => {
    if (!result.isConfirmed) return;

    Swal.fire({ title: "Booking...", didOpen: () => Swal.showLoading() });

    fetch("create_ground_lesson.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(result.value)
    })
      .then(res => res.json())
      .then(resp => {
        if (resp.success && Array.isArray(resp.events)) {
          Swal.fire("✅ Ground Lesson Booked", "", "success");
          resp.events.forEach(ev => {
            calendarInstance.addEvent({
              id: ev.id,
              title: "Ground Lesson",
              start: ev.start,
              end: ev.end,
              resourceId: ev.resourceId,
              backgroundColor: "#2563eb",
              borderColor: "#2563eb",
              textColor: "#ffffff",
              extendedProps: {
                students: ev.students
              }
            });
          });
        } else {
          Swal.fire("Error", resp.message || "Could not book ground lesson", "error");
        }
      });
  });
}


document.addEventListener("DOMContentLoaded", function () {
  // ✅ Show user & start calendar
  showUserInfo();
  loadAdminCalendar();
  startSessionTimeout();

    // ✅ Company Event Button
  const companyBtn = document.getElementById("companyEventBtn");
  if (companyBtn) {
    companyBtn.addEventListener("click", () => {
  Swal.fire({
    title: "Company Event",
    html: `
      <input id="eventTitle" class="swal2-input" placeholder="Event title">
      <input type="date" id="eventDate" class="swal2-input">
      <select id="eventTime" class="swal2-select">
  ${[...Array(32)].map((_, i) => {
    const mins = 360 + i * 30;
    const h = String(Math.floor(mins / 60)).padStart(2, '0');
    const m = mins % 60 === 0 ? '00' : '30';
    return `<option value="${h}:${m}">${h}:${m}</option>`;
  }).join('')}
</select>

      <input type="number" id="eventDuration" class="swal2-input" placeholder="Duration in minutes" value="60">
    `,
    confirmButtonText: "Next",
    preConfirm: () => {
      const title = document.getElementById("eventTitle").value.trim();
      const date = document.getElementById("eventDate").value;
      const time = document.getElementById("eventTime").value;
      const duration = parseInt(document.getElementById("eventDuration").value);

      if (!title || !date || !time || !duration) {
        Swal.showValidationMessage("All fields are required.");
        return false;
      }

      return { title, date, time, duration };
    }
  }).then(result => {
    if (!result.isConfirmed) return;

    // ✅ Load resources (CFIs and Aircraft)
    fetch("get-resources.php")
      .then(res => res.json())
      .then(resources => {
        const cfis = resources.filter(r => r.extendedProps?.group === "CFI");
        const aircraft = resources.filter(r => r.extendedProps?.group === "Aircraft");

        const cfiCheckboxes = cfis.map(cfi => `
          <label style="display:block;">
            <input type="checkbox" value="${cfi.id}" class="company-cfi"> ${cfi.title}
          </label>`).join('');

        const aircraftCheckboxes = aircraft.map(ac => `
          <label style="display:block;">
            <input type="checkbox" value="${ac.id}" class="company-aircraft"> ${ac.title}
          </label>`).join('');

        Swal.fire({
  title: "Who's attending, and what Aircraft?",
  html: `
    <div style="text-align:left;">
      <strong>CFIs:</strong><br>
      <label><input type="checkbox" id="selectAllCFIs"> Select all CFIs</label><br>
      <div id="cfiCheckboxes" style="margin-bottom:10px;">
        ${cfiCheckboxes}
      </div>

      <strong>Aircraft:</strong><br>
      <label><input type="checkbox" id="selectAllAircraft"> Select all Aircraft</label><br>
      <div id="aircraftCheckboxes">
        ${aircraftCheckboxes}
      </div>
    </div>
  `,
  confirmButtonText: "Create Event",
  didOpen: () => {
    // ✅ Select all CFIs toggle
    document.getElementById("selectAllCFIs").addEventListener("change", function () {
      const allCFIs = document.querySelectorAll(".company-cfi");
      allCFIs.forEach(cb => cb.checked = this.checked);
    });

    // ✅ Select all Aircraft toggle
    document.getElementById("selectAllAircraft").addEventListener("change", function () {
      const allAC = document.querySelectorAll(".company-aircraft");
      allAC.forEach(cb => cb.checked = this.checked);
    });
  },
  preConfirm: () => {
    const selectedCFIs = Array.from(document.querySelectorAll(".company-cfi:checked")).map(c => c.value);
    const selectedAircraft = Array.from(document.querySelectorAll(".company-aircraft:checked")).map(c => c.value);
    const groundStudents = Array.from(document.getElementById("groundStudents").selectedOptions).map(o => o.value);

  
    if (selectedCFIs.length === 0 && selectedAircraft.length === 0) {
      Swal.showValidationMessage("Please select at least one CFI or Aircraft.");
      return false;
    }

    return {
      ...result.value,
      cfis: selectedCFIs,
      aircraft: selectedAircraft
    };
  }
}).then(final => {
  if (!final.isConfirmed) return;

  fetch("create_company_event.php", {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify(final.value)
})
.then(res => res.json())
.then(resp => {
  if (resp.success && Array.isArray(resp.events)) {
    Swal.fire("✅ Created", "Company event added!", "success");

    resp.events.forEach(ev => {
      calendarInstance.addEvent({
        id: ev.id,
        title: ev.title,
        start: ev.start,
        end: ev.end,
        resourceId: ev.resourceId,
        backgroundColor: ev.backgroundColor || '#007BFF',
        borderColor: ev.borderColor || '#007BFF',
        textColor: ev.textColor || '#ffffff',
        extendedProps: ev.extendedProps || {}
      });
    });

  } else {
    Swal.fire("Error", resp.message || "Could not create event", "error");
  }
})
.catch(err => {
  console.error("Error parsing response:", err);
  Swal.fire("Error", "Unexpected error occurred.", "error");
});


});

      });
  });
});
  }
});

// ✅ Expose to global scope if needed by SweetAlert create logic
window.launchCreateBookingModal = launchCreateBookingModal;
window.loadAdminCalendar = loadAdminCalendar;