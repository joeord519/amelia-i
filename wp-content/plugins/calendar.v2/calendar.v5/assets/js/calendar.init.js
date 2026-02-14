let calendarInstance = null;
let allEvents = [];
let selectedResourceIds = new Set();
let availableOnly = false;

document.addEventListener("DOMContentLoaded", () => {
  showUserInfo();

  const role = getCookieValue("user_role");
  if (role !== "admin") {
    document.getElementById("companyEventBtn")?.remove();
  }

  document.getElementById("availableOnlyToggle")?.addEventListener("change", (e) => {
    availableOnly = !!e.target.checked;
    loadAdminCalendar();
  });

  document.getElementById("markDownBtn")?.addEventListener("click", launchMarkDownModal);
  document.getElementById("markUpBtn")?.addEventListener("click", launchMarkUpModal);

  loadAdminCalendar();
  startSessionTimeout();

  document.getElementById("logoutLink")?.addEventListener("click", (e) => {
    e.preventDefault();
    logout();
  });

  document.getElementById("companyEventBtn")?.addEventListener("click", () => {
    launchCompanyEventModal();
  });

  window.addEventListener("focus", () => {
    loadAdminCalendar();
  });

  const refreshBtn = document.getElementById("refreshCalendar");
  if (refreshBtn) {
    refreshBtn.addEventListener("click", () => {
      loadAdminCalendar();
    });
  }
});

async function launchMarkDownModal() {
  try {
    const res = await fetch('fetch_aircraft_list.php');
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to fetch aircraft list');

    const aircraftOptions = data.aircraft
      .map(a => `<option value="${a.tail_number}">${a.tail_number} (${a.status})</option>`)
      .join('');

    const now = new Date();
    const startDefault = now.toISOString().slice(0, 16);

    const result = await Swal.fire({
      title: 'Mark Aircraft Down',
      html: `
        <select id="mdTail" class="swal2-select">${aircraftOptions}</select>
        <select id="mdStatus" class="swal2-select">
          <option value="Maintenance">Maintenance</option>
          <option value="Out of Service">Out of Service</option>
        </select>
        <input id="mdStart" type="datetime-local" class="swal2-input" value="${startDefault}">
        <input id="mdEnd" type="datetime-local" class="swal2-input" placeholder="Optional end">
        <input id="mdReason" class="swal2-input" placeholder="Reason (required)">
        <textarea id="mdNotes" class="swal2-textarea" placeholder="Notes"></textarea>
      `,
      showCancelButton: true,
      confirmButtonText: 'Mark Down',
      preConfirm: () => {
        const tail_number = document.getElementById('mdTail').value;
        const status = document.getElementById('mdStatus').value;
        const start_at = document.getElementById('mdStart').value;
        const end_at = document.getElementById('mdEnd').value;
        const reason = document.getElementById('mdReason').value.trim();
        const notes = document.getElementById('mdNotes').value.trim();

        if (!tail_number || !start_at || !reason) {
          Swal.showValidationMessage('Tail, start time, and reason are required.');
          return false;
        }

        return { tail_number, status, start_at: start_at.replace('T', ' '), end_at: end_at ? end_at.replace('T', ' ') : null, reason, notes };
      }
    });

    if (!result.isConfirmed) return;

    const resp = await fetch('mark_aircraft_down.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(result.value)
    });
    const payload = await resp.json();

    if (!payload.success) throw new Error(payload.message || 'Failed to mark aircraft down');

    await Swal.fire('Aircraft marked down', `Affected flights: ${payload.affected_count}\nEmails sent: ${payload.emails_sent}`, 'success');
    loadAdminCalendar();
  } catch (error) {
    Swal.fire('Error', error.message || 'Unable to mark aircraft down.', 'error');
  }
}

async function launchMarkUpModal() {
  try {
    const res = await fetch('fetch_aircraft_list.php');
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to fetch aircraft list');

    const aircraftOptions = data.aircraft
      .filter(a => a.status !== 'Available')
      .map(a => `<option value="${a.tail_number}">${a.tail_number} (${a.status})</option>`)
      .join('');

    if (!aircraftOptions) {
      Swal.fire('Nothing to update', 'No aircraft are currently marked down.', 'info');
      return;
    }

    const result = await Swal.fire({
      title: 'Mark Aircraft Back Up',
      html: `<select id="muTail" class="swal2-select">${aircraftOptions}</select>`,
      showCancelButton: true,
      confirmButtonText: 'Mark Up',
      preConfirm: () => ({ tail_number: document.getElementById('muTail').value })
    });

    if (!result.isConfirmed) return;

    const resp = await fetch('mark_aircraft_up.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(result.value)
    });
    const payload = await resp.json();
    if (!payload.success) throw new Error(payload.message || 'Failed to mark aircraft up');

    Swal.fire('Aircraft available', `${payload.tail_number} is now Available.`, 'success');
    loadAdminCalendar();
  } catch (error) {
    Swal.fire('Error', error.message || 'Unable to mark aircraft up.', 'error');
  }
}

function startSessionTimeout(minutes = 5) {
  let timeoutId, warningId;

  const logoutUser = () => {
    Swal.fire("Session Expired", "You’ve been logged out due to inactivity.", "warning").then(() => logout());
  };

  const showWarning = () => {
    Swal.fire({
      title: "Are you still there?",
      text: "You’ll be logged out in a few minutes if there's no activity.",
      icon: "warning",
      timer: 10000
    });
  };

  const resetTimer = () => {
    clearTimeout(timeoutId);
    clearTimeout(warningId);
    warningId = setTimeout(showWarning, 90 * 1000);
    timeoutId = setTimeout(logoutUser, minutes * 60 * 1000);
  };

  ["mousemove", "mousedown", "keydown", "touchstart"].forEach(evt => {
    document.addEventListener(evt, resetTimer);
  });

  resetTimer();
}

function loadAdminCalendar() {
  const storedDate = localStorage.getItem("calendarFocusDate");
  const currentDate = storedDate ? new Date(storedDate) : new Date();

  if (!document.cookie.includes("user_phone")) {
    launchLoginModal();
    return;
  }

  const loadData = async () => {
    const res = await fetch(`fetch_flight.php?ts=${Date.now()}`);
    const json = await res.json();
    allEvents = json.events || [];
    renderCalendar(currentDate);
    localStorage.removeItem("calendarFocusDate");
  };

  loadData();
}

function renderCalendar(focusedDate = new Date()) {
  const calendarEl = document.getElementById("calendar");
  calendarEl.innerHTML = "";

  calendarInstance = new FullCalendar.Calendar(calendarEl, {
    schedulerLicenseKey: "GPL-My-Project-Is-Open-Source",
    initialView: "resourceTimelineDay",
    nowIndicator: true,
    editable: true,
    selectable: true,
    slotMinTime: "06:00:00",
    slotMaxTime: "22:00:00",
    resourceAreaHeaderContent: "CFIs & Aircraft",

    resources: function (fetchInfo, successCallback, failureCallback) {
      fetch(`fetch_resources.php?available_only=${availableOnly ? '1' : '0'}&ts=${Date.now()}`)
        .then(res => res.json())
        .then(data => {
          if (selectedResourceIds.size === 0) data.forEach(r => selectedResourceIds.add(r.id));
          successCallback(data);
        })
        .catch(err => failureCallback(err));
    },

    resourceLabelContent: function(arg) {
      const group = arg.resource.extendedProps?.group;
      if (group !== 'Aircraft') return { html: `<span>${arg.resource.title}</span>` };

      const status = arg.resource.extendedProps?.status || 'Unknown';
      const nickname = arg.resource.extendedProps?.nickname || '';
      const badgeColor = status === 'Available' ? '#16a34a' : (status === 'Maintenance' ? '#d97706' : '#dc2626');

      return {
        html: `<div><strong>${arg.resource.title}</strong> ${nickname ? `<small>(${nickname})</small>` : ''}<br><span style="display:inline-block;padding:2px 6px;border-radius:999px;background:${badgeColor};color:#fff;font-size:11px;">${status}</span></div>`
      };
    },

    eventSources: [
      { events: allEvents },
      {
        events: function(info, successCallback, failureCallback) {
          const qs = new URLSearchParams({ start: info.startStr, end: info.endStr, ts: Date.now().toString() });
          fetch(`fetch_downtime_blocks.php?${qs.toString()}`)
            .then(r => r.json())
            .then(payload => successCallback(payload.events || []))
            .catch(err => failureCallback(err));
        }
      }
    ],

    eventDrop: function (info) {
      const ep = info.event.extendedProps;
      if (ep.isDowntime) {
        info.revert();
        return;
      }

      const role = getCookieValue("user_role");
      const userCfiId = getCookieValue("user_cfi_id");

      if (role === "cfi" && ep.cfi_id != userCfiId){
        Swal.fire("Access Denied", "You can only reschedule your own events.", "warning");
        info.revert();
        return;
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
          info.revert();
          return;
        }

        const reason = result.value || "No reason provided";

        fetch("update_flight.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ id: ep.flight_id, start: info.event.startStr, end: info.event.endStr, reason })
        })
          .then(res => res.json())
          .then(resp => {
            if (!resp.success) {
              Swal.fire("Error", resp.message || "Update failed.", "error");
              info.revert();
            } else {
              Swal.fire("✅ Updated", "The event has been updated.", "success");
              if (ep.paired_event_id) {
                const paired = calendarInstance.getEventById(ep.paired_event_id);
                if (paired) {
                  paired.setStart(info.event.start);
                  paired.setEnd(info.event.end);
                }
              }
            }
          });
      });
    },

    eventClick: function (info) {
      const ep = info.event.extendedProps || {};

      if (ep.isDowntime) {
        Swal.fire({
          title: `Downtime: ${ep.tail_number}`,
          html: `<strong>Reason:</strong> ${ep.reason || 'N/A'}<br><strong>Start:</strong> ${ep.start_at || info.event.startStr}<br><strong>End:</strong> ${ep.end_at || 'Open'}<br><strong>Notes:</strong> ${ep.notes || 'None'}`,
          icon: 'info'
        });
        return;
      }

      const role = getCookieValue("user_role");
      const userCfiId = getCookieValue("user_cfi_id");
      const title = info.event.title || "Booking";
      const isGround = title.toLowerCase().includes("ground");

      let html = `<div style="text-align: left;">`;
      if (isGround && Array.isArray(ep.students)) {
        html += `<strong>Students:</strong><br>`;
        ep.students.forEach(s => { html += `${s.name} - ${s.phone}<br>`; });
      } else {
        html += `<strong>Student:</strong> ${ep.student_name || ep.future_student_name || 'N/A'}<br>
                 <strong>Phone:</strong> ${ep.student_phone || ep.future_student_phone || 'N/A'}<br>
                 <strong>Tail:</strong> ${ep.tail_number || 'N/A'}<br>`;
      }
      html += `<strong>CFI:</strong> ${ep.cfi_name || 'N/A'}</div>`;

      Swal.fire({
        title,
        html,
        showDenyButton: true,
        denyButtonText: 'Close',
        confirmButtonText: 'Delete',
        confirmButtonColor: '#dc2626'
      }).then(result => {
        if (!result.isConfirmed) return;
        if (role === "cfi" && ep.cfi_id != userCfiId) {
          Swal.fire("Access Denied", "You can only delete events you're assigned to.", "warning");
          return;
        }

        Swal.fire({
          title: 'Cancel Flight',
          html: `<textarea id="cancelReason" class="swal2-textarea" placeholder="Required: reason for cancellation"></textarea>
                 <label style="display:flex; align-items:center; margin-top:10px; font-size: 14px;">
                   <input type="checkbox" id="studentRequestedBox" style="margin-right:8px;"> Student Requested
                 </label>`,
          focusConfirm: false,
          showCancelButton: true,
          confirmButtonText: 'Confirm Cancel',
          preConfirm: () => {
            const reason = document.getElementById('cancelReason').value.trim();
            const studentRequested = document.getElementById('studentRequestedBox').checked;
            if (!reason) {
              Swal.showValidationMessage("Please enter a reason.");
              return false;
            }
            return { reason, student_requested: studentRequested };
          }
        }).then(result => {
          if (!result.isConfirmed) return;
          const deletedBy = { name: getCookieValue("user_name") || "Unknown", phone: getCookieValue("user_phone") || "" };
          fetch("delete_flight.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: ep.flight_id, reason: result.value.reason, student_requested: result.value.student_requested ? 1 : 0, deleted_by: deletedBy })
          })
          .then(res => res.json())
          .then(resp => {
            if (resp.success) {
              const pairedId = info.event.id.includes('-ac') ? info.event.id.replace('-ac', '') : info.event.id + '-ac';
              const paired = calendarInstance.getEventById(pairedId);
              if (paired) paired.remove();
              info.event.remove();
              Swal.fire("Deleted", "Flight removed successfully", "success");
            } else {
              Swal.fire("Error", resp.message || "Deletion failed.", "error");
            }
          });
        });
      });
    },

    select: function (info) {
      if (!info.resource || !info.resource.id) return;
      const start = new Date(info.start);
      const h = String(start.getHours()).padStart(2, '0');
      const m = String(start.getMinutes()).padStart(2, '0');
      const clickedTime = `${h}:${m}`;
      const clickedDate = start.getFullYear() + '-' + String(start.getMonth() + 1).padStart(2, '0') + '-' + String(start.getDate()).padStart(2, '0');

      Swal.fire({
        title: "What type of booking?",
        showDenyButton: true,
        showCancelButton: true,
        showConfirmButton: false,
        html: `
          <button id="bookFlight" class="swal2-confirm swal2-styled" style="background-color:#7c3aed; margin-right: 10px;">✈️ Flight</button>
          <button id="bookGround" class="swal2-deny swal2-styled" style="background-color:#dc2626; margin-right: 10px;">📘 Ground</button>
          <button id="bookDiscovery" class="swal2-cancel swal2-styled" style="background-color:#0ea5e9;">🎯 Discovery</button>
        `,
        didOpen: () => {
          document.getElementById("bookFlight").addEventListener("click", () => {
            Swal.close();
            launchCreateFlightModal({ resourceId: info.resource.id, date: clickedDate, time: clickedTime });
          });

          document.getElementById("bookGround").addEventListener("click", () => {
            Swal.close();
            launchGroundLessonModal({ resourceId: info.resource.id, date: clickedDate, time: clickedTime });
          });

          document.getElementById("bookDiscovery").addEventListener("click", () => {
            Swal.close();
            launchDiscoveryFlightModal({ date: clickedDate, time: clickedTime });
          });
        }
      });
    }
  });

  calendarInstance.render();
  calendarInstance.gotoDate(focusedDate);
}
