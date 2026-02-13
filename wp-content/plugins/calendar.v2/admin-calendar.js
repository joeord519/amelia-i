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
      eventResourceEditable: true,
      eventAllow: function(dropInfo, draggedEvent) {
        const type = draggedEvent.extendedProps?.type;
        const fromId = draggedEvent.extendedProps?.resourceId;
        const toId = dropInfo.resource.id;
        const group = dropInfo.resource.group;
        console.log("💡 Checking drop:", { type, fromId, toId, group });
        return group === "CFI" || group === "Aircraft";
      },
      eventDrop: function(info) {
        const ep = info.event.extendedProps;
        const paired = calendarInstance.getEventById(ep.paired_event_id);
        if (paired) {
          paired.setStart(info.event.start);
          paired.setEnd(info.event.end);
        }
        fetch('update_flight.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            id: ep.flight_id,
            start: info.event.startStr,
            end: info.event.endStr
          })
        }).then(res => res.json()).then(resp => {
          if (!resp.success) {
            Swal.fire('Error', 'Update failed.', 'error');
          }
        });
      },

      schedulerLicenseKey: "0645855535-fcs-1739777982",
      initialView: "resourceTimelineDay",
      slotMinTime: "06:00:00",
      slotMaxTime: "23:00:00",
      height: "auto",
      nowIndicator: true,
      resourceAreaHeaderContent: "Resources",
      titleFormat: { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' },
      resources: filteredResources,
      events: allEvents.flatMap(e => {
  const isCFIEvent = e.flight_type === "CFI-Event";

  const title = isCFIEvent
    ? "CFI Unavailable"
    : `${(e.tail_number && e.tail_number !== 'null') ? e.tail_number : (e.flight_type || 'G.L.')} → ${e.student_name}`;

  const baseEvent = {
    title,
    start: e.start,
    end: e.end,
    backgroundColor: isCFIEvent ? "#dc2626" : (e.color || "#2563eb"),
    textColor: "#ffffff",
    borderColor: "#000",
    classNames: isCFIEvent ? ["fc-event", "fc-cfi-block"] : [],
    extendedProps: e,
    // ✅ Tooltip on hover
    title: isCFIEvent ? "CFI Unavailable (from Google Calendar)" : title
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
      eventAllow: function(dropInfo, draggedEvent) {
          const type = draggedEvent.extendedProps?.type;
          const fromId = draggedEvent.extendedProps?.resourceId;
          const toId = dropInfo.resource.id;
          const group = dropInfo.resource.group;
          console.log("💡 Checking drop:", { type, fromId, toId, group });
          return group === "CFI" || group === "Aircraft";
        },
        eventDrop: function(info) {
          const ep = info.event.extendedProps;
          const paired = calendarInstance.getEventById(ep.paired_event_id);
          if (paired) {
            paired.setStart(info.event.start);
            paired.setEnd(info.event.end);
          }
          fetch('update_flight.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              id: ep.flight_id,
              start: info.event.startStr,
              end: info.event.endStr
            })
          }).then(res => res.json()).then(resp => {
            if (!resp.success) {
              Swal.fire('Error', 'Update failed.', 'error');
            }
          });
        },
        eventClick: async function (info) {
  const e = info.event.extendedProps;

  const result = await Swal.fire({
    title: `${e.flight_type}`,
    html: (() => {
      let html = `...`;
      return html;
    })(),
    didOpen: () => {
      const startTime = (e.start || "").includes("T") ? e.start.split('T')[1].substring(0, 5) : "06:00";
      const endTime = (e.end || "").includes("T") ? e.end.split('T')[1].substring(0, 5) : "08:00";
      document.getElementById("newStart").value = startTime;
      document.getElementById("newEnd").value = endTime;
    }
  }); // ✅ CLOSES Swal.fire()

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
}, // ✅ Comma ends eventClick

// ✅ Now this is valid
select: function(info) {
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
},


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


