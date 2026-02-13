<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Student Calendar</title>
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.8/index.global.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.8/index.global.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body { font-family: sans-serif; margin: 20px; }
    #calendar { max-width: 1200px; margin: auto; }
  </style>
</head>
<body>
  <h2 style="text-align: center;">📅 Your Flight Schedule</h2>
  <div id="calendar"></div>

  <script>
    let studentPhone = null;

    document.addEventListener("DOMContentLoaded", function () {
      Swal.fire({
        title: "Student Login",
        input: "text",
        inputLabel: "Enter your phone number",
        inputPlaceholder: "(636) 555-1234",
        confirmButtonText: "View Calendar",
        inputValidator: value => {
          if (!value || value.trim().length < 10) {
            return "Please enter a valid phone number";
          }
        }
      }).then(result => {
        if (!result.isConfirmed) return;
        studentPhone = result.value.trim();
        loadCalendar();
      });
    });

    function loadCalendar() {
      const calendarEl = document.getElementById("calendar");
      const calendar = new FullCalendar.Calendar(calendarEl, {
        schedulerLicenseKey: "GPL-My-Project-Is-Open-Source",
        timeZone: 'local',
        initialView: 'resourceTimelineDay',
        nowIndicator: true,
        allDaySlot: false,
        slotMinTime: "06:00:00",
        slotMaxTime: "22:00:00",
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: ''
        },
        resources: async function (fetchInfo, successCallback, failureCallback) {
          try {
            const res = await fetch("fetch_resources.php");
            const data = await res.json();
            successCallback(data);
          } catch (err) {
            failureCallback("Could not load resources.");
          }
        },
        events: async function (info, successCallback, failureCallback) {
          try {
            const res = await fetch("fetch_student_events.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ phone: studentPhone })
            });
            const data = await res.json();
            if (Array.isArray(data.events)) {
              successCallback(data.events);
            } else {
              failureCallback("No events found.");
            }
          } catch (err) {
            failureCallback("Error fetching events.");
          }
        },
        eventClick: function (info) {
          const ep = info.event.extendedProps;
          if (ep.isStudentEvent) {
            Swal.fire({
              title: info.event.title,
              html: `<b>Start:</b> ${info.event.start.toLocaleString()}<br><b>End:</b> ${info.event.end.toLocaleString()}`,
              confirmButtonText: "OK"
            });
          }
        }
      });
      calendar.render();
    }
  </script>
</body>
</html>
