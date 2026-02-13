let calendarInstance = null;
let allEvents = [];
let selectedResourceIds = new Set();

document.addEventListener("DOMContentLoaded", () => {
  showUserInfo();

  const role = getCookieValue("user_role");
  if (role !== "admin") {
    document.getElementById("companyEventBtn")?.remove();
  }

  // Make sure the Add Event button is on top of any calendar overlay
  const addBtnEl = document.getElementById("addEventBtn");
  if (addBtnEl) {
    addBtnEl.style.position = "relative";
    addBtnEl.style.zIndex = "9999";
  }

  loadAdminCalendar();
  startSessionTimeout();

  document.getElementById("logoutLink")?.addEventListener("click", (e) => {
    e.preventDefault();
    logout();
  });

  document.getElementById("companyEventBtn")?.addEventListener("click", () => {
    launchCompanyEventModal();
  });

  // Direct handler (desktop)
  if (addBtnEl) {
    addBtnEl.addEventListener("click", (e) => {
      e.preventDefault();
      handleAddEventClick();
    });
  }

  // Refresh on tab focus
  window.addEventListener("focus", () => {
    loadAdminCalendar();
  });

  // Manual refresh (if you ever add a refresh button with this ID)
  const refreshBtn = document.getElementById("refreshCalendar");
  if (refreshBtn) {
    refreshBtn.addEventListener("click", () => {
      loadAdminCalendar();
    });
  }
});

/**
 * Delegated click as a backup, in case theme wraps the button.
 */
document.addEventListener("click", (e) => {
  const target = e.target;
  if (!target) return;

  if (target.id === "addEventBtn" || target.closest?.("#addEventBtn")) {
    e.preventDefault();
    handleAddEventClick();
  }
});

function startSessionTimeout(minutes = 5) {
  let timeoutId, warningId;

  const logoutUser = () => {
    Swal.fire("Session Expired", "You’ve been logged out due to inactivity.", "warning")
      .then(() => logout());
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

    warningId = setTimeout(showWarning, 90 * 1000); // show after 90s
    timeoutId = setTimeout(logoutUser, minutes * 60 * 1000); // logout after 5 min
  };

  ["mousemove", "mousedown", "keydown", "touchstart"].forEach(evt => {
    document.addEventListener(evt, resetTimer);
  });

  resetTimer();
}

function loadAdminCalendar() {
  const { Calendar } = FullCalendar;

  const storedDate = localStorage.getItem("calendarFocusDate");
  const currentDate = storedDate ? new Date(storedDate) : new Date();

  if (!document.cookie.includes("user_phone")) {
    // not logged in – only show login
    launchLoginModal();
    return;
  }

  const loadData = async () => {
    const res = await fetch(`fetch_flight.php?ts=${Date.now()}`);
    const json = await res.json();
    console.log("Fetched from PHP:", json);
    allEvents = json.events || [];
    renderCalendar(currentDate);
    localStorage.removeItem("calendarFocusDate");
  };

  loadData();
}

/**
 * Helper: are we on a mobile-ish screen?
 */
function isMobileScreen() {
  return window.matchMedia("(max-width: 768px)").matches;
}

/**
 * When Add Event is clicked.
 * If not logged in -> show login.
 * If logged in -> open the booking chooser.
 */
function handleAddEventClick() {
  if (!document.cookie.includes("user_phone")) {
    // Force login first
    launchLoginModal();
    return;
  }

  if (!calendarInstance) {
    // Calendar not initialized yet – load it and then open dialog
    loadAdminCalendar();
    // Slight delay to let calendarInstance get set
    setTimeout(() => openAddEventDialog(), 400);
  } else {
    openAddEventDialog();
  }
}

/**
 * NEW: Open the same booking type chooser without clicking on the grid.
 * Used by the "Add Event" button (great for mobile).
 */
function openAddEventDialog() {
  // Use the calendar's current date if available, otherwise today
  const baseDate = calendarInstance ? calendarInstance.getDate() : new Date();

  const year = baseDate.getFullYear();
  const month = String(baseDate.getMonth() + 1).padStart(2, "0");
  const day = String(baseDate.getDate()).padStart(2, "0");
  const clickedDate = `${year}-${month}-${day}`;

  // Round current time to nearest half hour for a reasonable default
  const now = new Date();
  let hour = now.getHours();
  let minute = now.getMinutes() < 30 ? "00" : "30";
  const clickedTime = `${String(hour).padStart(2, "0")}:${minute}`;

  const mobile = isMobileScreen();

  Swal.fire({
    title: "What type of booking?",
    position: mobile ? "top" : "center",
    showDenyButton: true,
    showCancelButton: true,
    showConfirmButton: false,
    html: `
      <button id="bookFlight" class="swal2-confirm swal2-styled" style="background-color:#7c3aed; margin-right: 10px;">✈️ Flight</button>
      <button id="bookGround" class="swal2-deny swal2-styled" style="background-color:#dc2626; margin-right: 10px;">📘 Ground</button>
      <button id="bookDiscovery" class="swal2-cancel swal2-styled" style="background-color:#0ea5e9;">🎯 Discovery</button>
    `,
    didOpen: () => {
      if (mobile) {
        window.scrollTo({ top: 0, behavior: "smooth" });
      }

      document.getElementById("bookFlight").addEventListener("click", () => {
        Swal.close();
        // No resourceId here — flight modal will handle aircraft/CFI selection
        launchCreateFlightModal({
          date: clickedDate,
          time: clickedTime
        });
      });

      document.getElementById("bookGround").addEventListener("click", () => {
        Swal.close();
        launchGroundLessonModal({
          date: clickedDate,
          time: clickedTime
        });
      });

      document.getElementById("bookDiscovery").addEventListener("click", () => {
        Swal.close();
        launchDiscoveryFlightModal({
          date: clickedDate,
          time: clickedTime
        });
      });
    }
  });
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
      fetch(`fetch_resources.php?ts=${Date.now()}`)
        .then(res => res.json())
        .then(data => {
          if (selectedResourceIds.size === 0) {
            data.forEach(r => selectedResourceIds.add(r.id));
          }
          successCallback(data);
        })
        .catch(err => failureCallback(err));
    },

    events: allEvents,

    eventDrop: function (info) {
      const ep = info.event.extendedProps;
      const role = getCookieValue("user_role");
      const userPhone = getCookieValue("user_phone");
      const userCfiId = getCookieValue("user_cfi_id");

      if (role === "cfi" && ep.cfi_id != userCfiId) {
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
          body: JSON.stringify({
            id: ep.flight_id,
            start: info.event.startStr,
            end: info.event.endStr,
            reason: reason
          })
        })
          .then(res => res.json())
          .then(resp => {
            if (!resp.success) {
              Swal.fire("Error", "Update failed.", "error");
              info.revert();
            } else {
              Swal.fire("✅ Updated", "The event has been updated.", "success");

              console.log("✅ Drag Success", info.event.id, ep.paired_event_id);
              if (ep.paired_event_id) {
                const paired = calendarInstance.getEventById(ep.paired_event_id);
                if (paired) {
                  console.log("⏩ Syncing Paired Event", paired.id);
                  paired.setStart(info.event.start);
                  paired.setEnd(info.event.end);
                } else {
                  console.warn("⚠️ Paired event not found:", ep.paired_event_id);
                }
              }
            }
          });
      });
    },

    eventClick: function (info) {
      const ep = info.event.extendedProps;
      const role = getCookieValue("user_role");
      const userPhone = getCookieValue("user_phone");
      const userCfiId = getCookieValue("user_cfi_id");

      const title = info.event.title || "Booking";
      const isGround = title.toLowerCase().includes("ground");

      let html = `<div style="text-align: left;">`;

      if (isGround && Array.isArray(ep.students)) {
        html += `<strong>Students:</strong><br>`;
        ep.students.forEach(s => {
          html += `${s.name} - ${s.phone}<br>`;
        });
      } else {
        let student = ep.student_name || ep.future_student_name || "N/A";
        let phone = ep.student_phone || ep.future_student_phone || "N/A";

        html += `
          <strong>Student:</strong> ${student}<br>
          <strong>Phone:</strong> ${phone}<br>
          <strong>Tail:</strong> ${ep.tail_number || "N/A"}<br>
        `;
      }

      html += `<strong>CFI:</strong> ${ep.cfi_name || "N/A"}</div>`;

      Swal.fire({
        title: title,
        html: html,
        showDenyButton: true,
        denyButtonText: "Close",
        confirmButtonText: "Delete",
        confirmButtonColor: "#dc2626"
      }).then(result => {
        if (result.isConfirmed) {
          if (role === "cfi" && ep.cfi_id != userCfiId) {
            Swal.fire("Access Denied", "You can only delete events you're assigned to.", "warning");
            return;
          }

          Swal.fire({
            title: "Cancel Flight",
            html: `
              <textarea id="cancelReason" class="swal2-textarea" placeholder="Required: reason for cancellation"></textarea>
              <label style="display:flex; align-items:center; margin-top:10px; font-size: 14px;">
                <input type="checkbox" id="studentRequestedBox" style="margin-right:8px;"> Student Requested
              </label>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: "Confirm Cancel",
            preConfirm: () => {
              const reason = document.getElementById("cancelReason").value.trim();
              const studentRequested = document.getElementById("studentRequestedBox").checked;
              if (!reason) {
                Swal.showValidationMessage("Please enter a reason.");
                return false;
              }
              return {
                reason,
                student_requested: studentRequested
              };
            }
          }).then(result => {
            if (!result.isConfirmed) return;

            const deletedBy = {
              name: getCookieValue("user_name") || "Unknown",
              phone: getCookieValue("user_phone") || ""
            };

            fetch("delete_flight.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                id: ep.flight_id,
                reason: result.value.reason,
                student_requested: result.value.student_requested ? 1 : 0,
                deleted_by: deletedBy
              })
            })
              .then(res => res.json())
              .then(resp => {
                if (resp.success) {
                  const pairedId = info.event.id.includes("-ac")
                    ? info.event.id.replace("-ac", "")
                    : info.event.id + "-ac";
                  const paired = calendarInstance.getEventById(pairedId);
                  if (paired) paired.remove();
                  info.event.remove();

                  Swal.fire("Deleted", "Flight removed successfully", "success");
                } else {
                  Swal.fire("Error", resp.message || "Deletion failed.", "error");
                }
              });
          });
        }
      });
    },

    select: function (info) {
      if (!info.resource || !info.resource.id) return;

      const start = new Date(info.start);
      const h = String(start.getHours()).padStart(2, "0");
      const m = String(start.getMinutes()).padStart(2, "0");
      const clickedTime = `${h}:${m}`;
      const clickedDate = start.getFullYear() + "-" +
        String(start.getMonth() + 1).padStart(2, "0") + "-" +
        String(start.getDate()).padStart(2, "0");

      const mobile = isMobileScreen();

      Swal.fire({
        title: "What type of booking?",
        position: mobile ? "top" : "center",
        showDenyButton: true,
        showCancelButton: true,
        showConfirmButton: false,
        html: `
          <button id="bookFlight" class="swal2-confirm swal2-styled" style="background-color:#7c3aed; margin-right: 10px;">✈️ Flight</button>
          <button id="bookGround" class="swal2-deny swal2-styled" style="background-color:#dc2626; margin-right: 10px;">📘 Ground</button>
          <button id="bookDiscovery" class="swal2-cancel swal2-styled" style="background-color:#0ea5e9;">🎯 Discovery</button>
        `,
        didOpen: () => {
          if (mobile) {
            window.scrollTo({ top: 0, behavior: "smooth" });
          }

          document.getElementById("bookFlight").addEventListener("click", () => {
            Swal.close();
            launchCreateFlightModal({
              resourceId: info.resource.id,
              date: clickedDate,
              time: clickedTime
            });
          });

          document.getElementById("bookGround").addEventListener("click", () => {
            Swal.close();
            launchGroundLessonModal({
              resourceId: info.resource.id,
              date: clickedDate,
              time: clickedTime
            });
          });

          document.getElementById("bookDiscovery").addEventListener("click", () => {
            Swal.close();
            launchDiscoveryFlightModal({
              date: clickedDate,
              time: clickedTime
            });
          });
        }
      });
    }
  });

  calendarInstance.render();
  calendarInstance.gotoDate(focusedDate);
}



