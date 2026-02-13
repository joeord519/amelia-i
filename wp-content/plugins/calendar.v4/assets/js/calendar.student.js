// /wp-content/plugins/calendar.v4/assets/js/calendar.student.js

let studentCalendarInstance = null;
let studentEvents = [];
let studentResources = [];

document.addEventListener("DOMContentLoaded", () => {
  loadStudentCalendar();

  // Date range buttons
  document.querySelectorAll("button[data-range]").forEach((btn) => {
    btn.addEventListener("click", () => {
      if (!studentCalendarInstance) return;
      const now = new Date();

      switch (btn.dataset.range) {
        case "today":
          studentCalendarInstance.changeView("resourceTimelineDay");
          studentCalendarInstance.gotoDate(now);
          break;

        case "tomorrow": {
          const tomorrow = new Date(now);
          tomorrow.setDate(now.getDate() + 1);
          studentCalendarInstance.changeView("resourceTimelineDay");
          studentCalendarInstance.gotoDate(tomorrow);
          break;
        }

        case "7days": {
          const start7 = new Date();
          const end7 = new Date();
          end7.setDate(start7.getDate() + 6);
          studentCalendarInstance.changeView("resourceTimeline");
          studentCalendarInstance.setOption("visibleRange", {
            start: start7.toISOString().split("T")[0],
            end: end7.toISOString().split("T")[0],
          });
          break;
        }

        case "month":
          studentCalendarInstance.changeView("dayGridMonth");
          studentCalendarInstance.gotoDate(now);
          break;

        case "30days": {
          const start = new Date();
          const end = new Date();
          end.setDate(start.getDate() + 29);
          studentCalendarInstance.changeView("resourceTimeline");
          studentCalendarInstance.setOption("visibleRange", {
            start: start.toISOString().split("T")[0],
            end: end.toISOString().split("T")[0],
          });
          break;
        }
      }
    });
  });
});

async function loadStudentCalendar() {
  try {
    // EXACT same endpoints the admin calendar uses
    const resEvents = await fetch("fetch_flight.php?ts=" + Date.now());
    const dataEvents = await resEvents.json();

    console.log("👩‍🎓 Student v4 fetch_flight.php:", dataEvents);

    studentEvents = dataEvents.events || [];

    const resResources = await fetch("fetch_resources.php?ts=" + Date.now());
    const dataResources = await resResources.json();

    console.log("👩‍🎓 Student v4 fetch_resources.php:", dataResources);

    studentResources = dataResources || [];

    renderStudentCalendar();
  } catch (err) {
    console.error("❌ Student calendar load failed:", err);
    Swal.fire("Error", "Could not load the schedule.", "error");
    renderStudentCalendar(); // show empty grid at least
  }
}

function renderStudentCalendar() {
  const calendarEl = document.getElementById("calendar");
  if (!calendarEl) {
    console.error("❌ #calendar not found");
    return;
  }
  calendarEl.innerHTML = "";

  studentCalendarInstance = new FullCalendar.Calendar(calendarEl, {
    schedulerLicenseKey: "GPL-My-Project-Is-Open-Source",
    initialView: "resourceTimelineDay",
    nowIndicator: true,
    editable: false,   // 🔒 read-only for students
    selectable: false,
    slotMinTime: "06:00:00",
    slotMaxTime: "22:00:00",
    resourceAreaHeaderContent: "CFIs & Aircraft",

    // Use resources & events EXACTLY like admin calendar
    resources: studentResources,
    events: studentEvents,

    eventClick: function (info) {
      const ep = info.event.extendedProps || {};
      const title = info.event.title || "Scheduled Flight";

      const student =
        ep.student_name || ep.future_student_name || "N/A";
      const phone =
        ep.student_phone || ep.future_student_phone || "N/A";
      const tail = ep.tail_number || "N/A";
      const cfi  = ep.cfi_name || "N/A";

      Swal.fire({
        title: title,
        html: `
          <div style="text-align:left;">
            <strong>Student:</strong> ${student}<br>
            <strong>Phone:</strong> ${phone}<br>
            <strong>Tail:</strong> ${tail}<br>
            <strong>CFI:</strong> ${cfi}<br>
            <strong>Start:</strong> ${
              info.event.start?.toLocaleString() || ""
            }<br>
            <strong>End:</strong> ${
              info.event.end?.toLocaleString() || ""
            }<br>
          </div>
        `,
        confirmButtonText: "Close",
      });
    },
  });

  studentCalendarInstance.render();
  studentCalendarInstance.gotoDate(new Date());
}


