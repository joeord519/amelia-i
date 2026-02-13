// ✅ Full Working admin-calendar.js with all booking logic + fixes

let calendarInstance = null;
let allEvents = [];
let allResources = [];
let selectedResourceIds = new Set();

function loadAdminCalendar(adminData) {
  console.log("✅ loadAdminCalendar was called", adminData);

  const { Calendar } = FullCalendar;

  const loadData = async () => {
    const res = await fetch("get-events.php");
    const data = await res.json();
    allEvents = data.events || [];
    allResources = data.resources || [];

    if (selectedResourceIds.size === 0) {
      allResources.forEach(r => selectedResourceIds.add(r.id));
    }

    renderCalendar();
  };

  const renderCalendar = () => {
    const calendarEl = document.getElementById("calendar");
    calendarEl.innerHTML = "";

    const filteredResources = allResources
      .filter(r => selectedResourceIds.has(r.id))
      .map(r => ({
        ...r,
        eventBackgroundColor: "#e0e7ff",
        eventBorderColor: "#000",
        className: `res-${r.id}`
      }));

    calendarInstance = new Calendar(calendarEl, {
      schedulerLicenseKey: "0645855535-fcs-1739777982",
      initialView: "resourceTimelineDay",
      slotMinTime: "06:00:00",
      slotMaxTime: "23:00:00",
      height: "auto",
      nowIndicator: true,
      resourceAreaHeaderContent: "Resources",
      resources: filteredResources,
      events: allEvents.flatMap(e => {
        const title = e.gcal_title || `${(e.tail_number && e.tail_number !== 'null') ? e.tail_number : (e.flight_type || 'G.L.')} → ${e.student_name}`;
        const baseEvent = {
          title,
          start: e.start,
          end: e.end,
          backgroundColor: e.gcal_title ? "#9ca3af" : e.color,
          borderColor: "#000",
          extendedProps: e
        };

        const isGround = (e.flight_type || "").toLowerCase().includes("ground");
        const events = [];

        if (isGround && selectedResourceIds.has(e.cfi_id)) {
          events.push({ ...baseEvent, resourceId: e.cfi_id });
        } else {
          if (selectedResourceIds.has(e.tail_number)) {
            events.push({ ...baseEvent, resourceId: e.tail_number });
          }
          if (selectedResourceIds.has(e.cfi_id)) {
            events.push({ ...baseEvent, resourceId: e.cfi_id });
          }
        }

        return events;
      }),
      eventClick: async function (info) {
        const e = info.event.extendedProps;

        const result = await Swal.fire({
          title: `${e.flight_type}`,
          html: (() => {
            let html = `
              <b>🥎 CFI:</b> ${e.cfi_name}<br>
              <b>👨‍🎓 Student:</b> ${e.student_name}<br>
              <b>📍 Location:</b> ${e.airport_code}<br>`;

            if ((e.flight_type || '').toLowerCase() === 'discovery flight' && (e.future_student_name || e.future_student_phone)) {
              html += `
                <hr>
                <b>🧒 Future Student:</b><br>
                ${e.future_student_name || ''}<br>
                ${e.future_student_phone || ''}<br>`;
            }

            html += `
              <hr>
              <label style="display: block; margin-bottom: 4px;">⏰ Change Time:</label>
              <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                <label for="newStart" style="width: 120px; font-weight: bold; text-align: right;">Start Time:</label>
                <select id="newStart" style="width: 120px;">
                  ${[...Array(33)].map((_, i) => {
                    const mins = 360 + i * 30;
                    const h = String(Math.floor(mins / 60)).padStart(2, '0');
                    const m = mins % 60 === 0 ? '00' : '30';
                    return `<option value="${h}:${m}">${h}:${m}</option>`;
                  }).join('')}
                </select>
              </div>
              <div style="display: flex; align-items: center; gap: 8px;">
                <label for="newEnd" style="width: 120px; font-weight: bold; text-align: right;">End Time:</label>
                <select id="newEnd" style="width: 120px;">
                  ${[...Array(33)].map((_, i) => {
                    const mins = 360 + i * 30;
                    const h = String(Math.floor(mins / 60)).padStart(2, '0');
                    const m = mins % 60 === 0 ? '00' : '30';
                    return `<option value="${h}:${m}">${h}:${m}</option>`;
                  }).join('')}
                </select>
              </div>`;

            return html;
          })(),
          didOpen: () => {
            const startTime = e.start.split('T')[1].substring(0, 5);
            const endTime = e.end.split('T')[1].substring(0, 5);
            document.getElementById("newStart").value = startTime;
            document.getElementById("newEnd").value = endTime;
          },
          showCancelButton: true,
          showDenyButton: true,
          confirmButtonText: "Save",
          denyButtonText: "🕵 Delete",
          cancelButtonText: "Close"
        });

        if (result.isConfirmed) {
          const selectedStart = document.getElementById("newStart").value;
          const selectedEnd = document.getElementById("newEnd").value;
          const flightDate = e.start.split('T')[0];

          const payload = {
            id: e.flight_id,
            start: `${flightDate} ${selectedStart}:00`,
            end: `${flightDate} ${selectedEnd}:00`
          };

          fetch("update_flight.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
          })
            .then(res => res.json())
            .then(resp => {
              if (resp.success) {
                Swal.fire("Updated!", "Flight updated successfully.", "success");
                loadData();
              } else {
                Swal.fire("Error", resp.message || "Update failed.", "error");
              }
            });
        }
      }
    });

    calendarInstance.render();
    calendarInstance.gotoDate(new Date());
  };

  const addBtn = document.createElement("button");
  addBtn.innerText = "➕ Add Flight Booking";
  addBtn.style.cssText = `
    position: fixed;
    bottom: 24px;
    right: 24px;
    background-color: #2563eb;
    color: white;
    border: none;
    padding: 14px 18px;
    font-size: 1rem;
    font-weight: bold;
    border-radius: 50px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    cursor: pointer;
    z-index: 9999;
  `;
  addBtn.onclick = launchCreateBookingModal;
  document.body.appendChild(addBtn);

  loadData();
}

window.loadAdminCalendar = loadAdminCalendar;

// launchCreateBookingModal remains unchanged from your last working version


function launchCreateBookingModal() {
  fetch("fetch_flight_types_admin.php")
    .then(res => res.json())
    .then(types => {
      const flightOptions = `<option disabled selected value="">-- Select Flight Type --</option>` +
        types.map(t => `<option value="${t.id}">${t.name}</option>`).join('');

      Swal.fire({
        title: "Add New Flight Booking",
        html: `
          <div style="display: flex; flex-direction: column; gap: 8px; align-items: stretch;">
  <select id="adminFlightType" class="swal2-select">${flightOptions}</select>

  <input id="adminStudentSearch" class="swal2-input" placeholder="Search Student by Last Name">
  <select id="adminStudentDropdown" class="swal2-select" style="display:none;"></select>
  <input id="adminStudentPhone" type="hidden">

  <input id="futureStudentName" class="swal2-input" placeholder="Future Student Name" style="display:none;">
  <input id="futureStudentPhone" class="swal2-input" placeholder="Future Student Phone #" style="display:none;">

  <input id="adminTailNumber" class="swal2-input" placeholder="Tail Number (if required)">

  <input id="cfiSearch" class="swal2-input" placeholder="Search CFI by Name">
  <select id="adminCFI" class="swal2-select" style="display:none;"></select>

  <select id="adminLocation" class="swal2-select">
    <option disabled selected value="">-- Select Location --</option>
  </select>

  <input type="date" id="adminDate" class="swal2-input">
  <select id="adminTime" class="swal2-select">
  ${[...Array(33)].map((_, i) => {
    const totalMinutes = 360 + i * 30; // 360 = 6:00 AM in minutes
    const h = String(Math.floor(totalMinutes / 60)).padStart(2, '0');
    const m = totalMinutes % 60 === 0 ? '00' : '30';
    return `<option value=\"${h}:${m}\">${h}:${m}</option>`;
  }).join('')}
</select>
</div>

        `,
        didOpen: () => {
          const searchBox = document.getElementById("adminStudentSearch");
          const dropdown = document.getElementById("adminStudentDropdown");
          const hiddenPhone = document.getElementById("adminStudentPhone");

          const flightTypeField = document.getElementById("adminFlightType");
          const futureName = document.getElementById("futureStudentName");
          const futurePhone = document.getElementById("futureStudentPhone");

          const cfiSearch = document.getElementById("cfiSearch");
          const cfiDropdown = document.getElementById("adminCFI");

          const locDropdown = document.getElementById("adminLocation");

          // 🔍 Student name search
          searchBox.addEventListener("input", function () {
            const query = this.value.trim();
            if (query.length < 2) return;

            fetch("search_students.php?last=" + encodeURIComponent(query))
              .then(res => res.json())
              .then(matches => {
                if (!Array.isArray(matches)) return;

                dropdown.style.display = "block";
                dropdown.innerHTML = matches.map(s =>
                  `<option value="${s.phone}">${s.name} - ${s.phone}</option>`
                ).join('');

                dropdown.onchange = () => {
                  hiddenPhone.value = dropdown.value;
                };
              });
          });

          // 🔍 CFI name search
          cfiSearch.addEventListener("input", function () {
            const query = this.value.trim();
            if (query.length < 2) return;

            fetch("search_cfis.php?name=" + encodeURIComponent(query))
              .then(res => res.json())
              .then(results => {
                if (!Array.isArray(results)) return;

                cfiDropdown.style.display = "block";
                cfiDropdown.innerHTML = results.map(cfi =>
                  `<option value="${cfi.id}">${cfi.name}</option>`
                ).join('');
              });
          });

          // 📍 Load active locations
          fetch("fetch_locations.php")
            .then(res => res.json())
            .then(locations => {
              locDropdown.innerHTML = `<option disabled selected value="">-- Select Location --</option>` +
                locations.map(loc => `<option value="${loc.airport_code}">${loc.name}</option>`).join('');
            });

          // ✈️ Discovery Flight logic
          setTimeout(() => {
            const checkDiscovery = () => {
              const selectedText = flightTypeField.options[flightTypeField.selectedIndex]?.text.toLowerCase() || '';
              const isDiscovery = selectedText.includes("discovery");

              futureName.style.display = isDiscovery ? "block" : "none";
              futurePhone.style.display = isDiscovery ? "block" : "none";

              searchBox.style.display = isDiscovery ? "none" : "block";
              dropdown.style.display = "none";

              if (!isDiscovery) {
                futureName.value = "";
                futurePhone.value = "";
              }
            };

            flightTypeField.addEventListener("change", checkDiscovery);
            checkDiscovery();
          }, 100);
        },
        confirmButtonText: "Create Flight",
        showCancelButton: true,
        preConfirm: async () => {
          const tail = document.getElementById("adminTailNumber").value.trim().toUpperCase();

          if (tail) {
            const tailValid = await fetch(`validate_tail.php?tail=${encodeURIComponent(tail)}`)
              .then(res => res.json())
              .then(res => res.valid);

            if (!tailValid) {
              Swal.showValidationMessage("Invalid or unrecognized tail number.");
              return false;
            }
          }

          return {
            phone: document.getElementById("adminStudentPhone").value.trim(),
            flightTypeId: document.getElementById("adminFlightType").value,
            tailNumber: tail || null,
            cfiId: document.getElementById("adminCFI").value || null,
            location: document.getElementById("adminLocation").value,
            date: document.getElementById("adminDate").value,
            time: document.getElementById("adminTime").value,
            futureName: document.getElementById("futureStudentName").value.trim(),
            futurePhone: document.getElementById("futureStudentPhone").value.trim()
          };
        }
      }).then(result => {
        if (result.isConfirmed) {
          fetch("admin-booking-create.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(result.value)
          })
            .then(res => res.json())
            .then(resp => {
              if (resp.success) {
                Swal.fire("✅ Booked!", "Flight has been created.", "success");
                loadData();
              } else {
                Swal.fire("Error", resp.message || "Booking failed.", "error");
              }
            });
        }
      });
    });
}