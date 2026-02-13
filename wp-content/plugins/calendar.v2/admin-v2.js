let calendarInstance = null;
let allEvents = [];
let allResources = [];
let selectedResourceIds = new Set();

function loadAdminCalendar(adminData) {
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

    const filteredResources = allResources.filter(r => selectedResourceIds.has(r.id));

    calendarInstance = new Calendar(calendarEl, {
      schedulerLicenseKey: "GPL-My-Project-Is-Open-Source",
      initialView: "resourceTimelineDay",
      slotMinTime: "06:00:00",
      slotMaxTime: "22:00:00",
      nowIndicator: true,
      editable: true,
      selectable: true,
      resourceAreaHeaderContent: "CFIs & Aircraft",
      resources: filteredResources,
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

        fetch("update_flight.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            id: ep.flight_id,
            start: info.event.startStr,
            end: info.event.endStr,
            paired_event_id: ep.paired_event_id
          })
        })
          .then(res => res.json())
          .then(resp => {
            if (!resp.success) {
              Swal.fire("Error", "Update failed.", "error");
            }
          });
      },
      select: function (info) {
        if (!info.resource || !info.resource.id) return;
        const start = new Date(info.start);
        const h = String(start.getHours()).padStart(2, '0');
        const m = String(start.getMinutes()).padStart(2, '0');
        const clickedTime = `${h}:${m}`;
        const clickedDate = start.toISOString().split('T')[0];

        window.launchCreateBookingModal({
          resourceId: info.resource.id,
          date: clickedDate,
          time: clickedTime
        });
      }
    });

    calendarInstance.render();
    calendarInstance.gotoDate(new Date());
  };

  const addBtn = document.createElement("button");
  addBtn.innerText = "Add Flight Booking";
  addBtn.className = "admin-add-booking-btn";
  addBtn.onclick = launchCreateBookingModal;
  document.body.appendChild(addBtn);

  loadData();
}

function launchCreateBookingModal(prefill = {}) {
  fetch("fetch_flight_types_admin.php")
    .then(res => res.json())
    .then(types => {
      const flightOptions = `<option disabled selected value="">-- Select Flight Type --</option>` +
        types.map(t => `<option value="${t.id}">${t.name}</option>`).join('');

      Swal.fire({
        title: "Add New Flight Booking",
        html: `
          <div style="display: flex; flex-direction: column; gap: 8px;">
            <select id="adminFlightType" class="swal2-select">${flightOptions}</select>
            <input id="adminStudentSearch" class="swal2-input" placeholder="Search Student by Last Name">
            <select id="adminStudentDropdown" class="swal2-select" style="display:none;"></select>
            <input id="adminStudentPhone" type="hidden">
            <input id="adminTailNumber" class="swal2-input" placeholder="Tail Number">
            <input id="cfiSearch" class="swal2-input" placeholder="Search CFI by Name">
            <select id="adminCFI" class="swal2-select" style="display:none;"></select>
            <select id="adminLocation" class="swal2-select"><option disabled selected>-- Location --</option></select>
            <input type="date" id="adminDate" class="swal2-input">
            <select id="adminTime" class="swal2-select">
              ${[...Array(33)].map((_, i) => {
                const m = 360 + i * 30, h = String(Math.floor(m / 60)).padStart(2, '0'),
                  mm = m % 60 === 0 ? '00' : '30'; return `<option value="${h}:${mm}">${h}:${mm}</option>`;
              }).join('')}
            </select>
          </div>`,
        didOpen: () => {
          const sBox = document.getElementById("adminStudentSearch");
          const sDrop = document.getElementById("adminStudentDropdown");
          const phone = document.getElementById("adminStudentPhone");
          const cfiBox = document.getElementById("cfiSearch");
          const cfiDrop = document.getElementById("adminCFI");
          const locDrop = document.getElementById("adminLocation");

          sBox.addEventListener("input", function () {
            fetch("search_students.php?last=" + encodeURIComponent(this.value))
              .then(res => res.json())
              .then(matches => {
                if (!Array.isArray(matches)) return;
                sDrop.style.display = "block";
                sDrop.innerHTML = matches.map(s =>
                  `<option value="${s.phone}">${s.name} - ${s.phone}</option>`).join('');
                sDrop.onchange = () => { phone.value = sDrop.value; };
              });
          });

          cfiBox.addEventListener("input", function () {
            fetch("search_cfis.php?name=" + encodeURIComponent(this.value))
              .then(res => res.json())
              .then(results => {
                if (!Array.isArray(results)) return;
                cfiDrop.style.display = "block";
                cfiDrop.innerHTML = results.map(cfi =>
                  `<option value="${cfi.id}">${cfi.name}</option>`).join('');
              });
          });

          fetch("fetch_locations.php")
            .then(res => res.json())
            .then(locations => {
              locDrop.innerHTML = `<option disabled selected value="">-- Select Location --</option>` +
                locations.map(loc => `<option value="${loc.airport_code}">${loc.name}</option>`).join('');
            });

          if (prefill.date) document.getElementById("adminDate").value = prefill.date;
          if (prefill.time) document.getElementById("adminTime").value = prefill.time;
          if (prefill.resourceId) {
            const cfiSelect = document.getElementById("adminCFI");
            if (cfiSelect) cfiSelect.value = prefill.resourceId;
          }
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
          return {
            phone: document.getElementById("adminStudentPhone").value.trim(),
            flightTypeId: document.getElementById("adminFlightType").value,
            tailNumber: tail || null,
            cfiId: document.getElementById("adminCFI").value || null,
            location: document.getElementById("adminLocation").value,
            date: document.getElementById("adminDate").value,
            time: document.getElementById("adminTime").value
          };
        }
      }).then(result => {
        if (result.isConfirmed) {
          fetch("create_flight_admin.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(result.value)
          })
            .then(res => res.json())
            .then(resp => {
              if (resp.success) {
                Swal.fire("Booked", "Flight has been created.", "success");
                loadAdminCalendar();
              } else {
                Swal.fire("Error", resp.message || "Booking failed.", "error");
              }
            });
        }
      });
    });
}

window.loadAdminCalendar = loadAdminCalendar;
window.launchCreateBookingModal = launchCreateBookingModal;

