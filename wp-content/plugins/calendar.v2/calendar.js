document.addEventListener("DOMContentLoaded", async () => {
  const { Calendar } = FullCalendar;

  let allEvents = [];
  let allResources = [];
  let calendarInstance = null;

  let currentLocation = "";
  let selectedResourceIds = new Set();

  const loadData = async () => {
    const res = await fetch("get-events.php");
    const data = await res.json();

    allEvents = data.events || [];

    const aircraft = getUniqueAircraft(data.resources || []);
    const cfis = getUniqueCFIs(data.resources || []);

    allResources = [...aircraft, ...cfis];

    if (selectedResourceIds.size === 0) {
      allResources.forEach(r => selectedResourceIds.add(r.id));
    }

    renderCalendar();
  };

  function getUniqueAircraft(resources) {
    return resources.filter(r => r.group === "Aircraft");
  }

  function getUniqueCFIs(resources) {
    return resources.filter(r => r.group === "CFI");
  }

  function getFilteredEvents() {
    return allEvents.filter(e =>
      currentLocation ? e.airport_code === currentLocation : true
    );
  }

  function getColorForResource(id) {
    const colors = ['#fee2e2', '#dbeafe', '#d1fae5', '#fef3c7', '#ede9fe', '#f3e8ff', '#fcd34d'];
    let hash = 0;
    for (let i = 0; i < id.length; i++) {
      hash = id.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colors[Math.abs(hash) % colors.length];
  }

  function renderCalendar() {
    const calendarEl = document.getElementById("calendar");
    calendarEl.innerHTML = "";

    const filteredResources = allResources
      .filter(r => selectedResourceIds.has(r.id))
      .map(r => ({
        ...r,
        eventBackgroundColor: getColorForResource(r.id),
        eventBorderColor: "#000",
        className: `res-${r.id}`
      }));

    const calendar = new Calendar(calendarEl, {
      schedulerLicenseKey: "0645855535-fcs-1739777982",
      initialView: "resourceTimelineDay",
      slotMinTime: "06:00:00",
      slotMaxTime: "22:00:00",
      height: "auto",
      nowIndicator: true,
      resourceAreaHeaderContent: "Resources",
      resources: filteredResources,

      events: getFilteredEvents().flatMap(e => {
  const baseEvent = {
  title: (e.flight_type === "CFI-Event") ? "CFI Unavailable" : `${(e.tail_number && e.tail_number !== 'null') ? e.tail_number : (e.flight_type || 'G.L.')} → ${e.student_name}`,
  start: e.start,
  end: e.end,
  backgroundColor: (e.flight_type === "CFI-Event") ? "#9ca3af" : (e.color || "#2563eb"),
  textColor: (e.flight_type === "CFI-Event") ? "#000000" : "#ffffff",
  borderColor: "#000",
  classNames: (e.flight_type === "CFI-Event") ? ["fc-event", "fc-cfi-block"] : [],
  extendedProps: e
};

  const events = [];

  // Show only on CFI if ground lesson
  const isGroundLesson = (e.flight_type || "").toLowerCase().includes("ground");

  if (isGroundLesson) {
    if (selectedResourceIds.has(e.cfi_id)) {
      events.push({ ...baseEvent, resourceId: e.cfi_id });
    }
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

      eventClick: function (info) {
        const e = info.event.extendedProps;
        Swal.fire({
          title: e.flight_type ? "CFI Unavailable" : `${(e.tail_number && e.tail_number !== 'null') ? e.tail_number : (e.flight_type || 'G.L.')} → ${e.student_name}`,
          html: `
            <b>🧑‍✈️ CFI:</b> ${e.cfi_name} ${e.cfi_phone}<br>
            <b>👨‍🎓 Student:</b> ${e.student_name} ${e.student_phone}<br>
            <b>📍 Location:</b> ${e.airport_code}<br>
            <b>🕒</b> ${new Date(e.start).toLocaleString()} – ${new Date(e.end).toLocaleString()}
          `,
          confirmButtonText: "Close"
        });
      }
    });

    calendar.render();
    calendar.gotoDate(new Date());
    calendarInstance = calendar;
  }

  // Date range buttons
  document.querySelectorAll("button[data-range]").forEach(btn => {
    btn.addEventListener("click", () => {
      if (!calendarInstance) return;
      const now = new Date();

      switch (btn.dataset.range) {
        case "today":
          calendarInstance.changeView("resourceTimelineDay");
          calendarInstance.gotoDate(now);
          break;
        case "tomorrow":
          const tomorrow = new Date(now);
          tomorrow.setDate(now.getDate() + 1);
          calendarInstance.changeView("resourceTimelineDay");
          calendarInstance.gotoDate(tomorrow);
          break;
        case "7days":
          const start7 = new Date();
          const end7 = new Date();
          end7.setDate(start7.getDate() + 6);
          calendarInstance.changeView("resourceTimeline");
          calendarInstance.setOption("visibleRange", {
            start: start7.toISOString().split("T")[0],
            end: end7.toISOString().split("T")[0]
          });
          break;
        case "month":
          calendarInstance.changeView("dayGridMonth");
          calendarInstance.gotoDate(now);
          break;
        case "30days":
          const start = new Date();
          const end = new Date();
          end.setDate(start.getDate() + 29);
          calendarInstance.changeView("resourceTimeline");
          calendarInstance.setOption("visibleRange", {
            start: start.toISOString().split("T")[0],
            end: end.toISOString().split("T")[0]
          });
          break;
      }
    });
  });

  // Resource + location filter modal
  if (!document.getElementById("filter-btn")) {
    const filterBtn = document.createElement("button");
    filterBtn.id = "filter-btn";
    filterBtn.textContent = "✈️ Select Planes & CFIs";
    filterBtn.style.marginLeft = "auto";
    filterBtn.onclick = () => {
      const resourceHTML = allResources.map(r => {
        const checked = selectedResourceIds.has(r.id) ? "checked" : "";
        return `<div><input type="checkbox" id="res-${r.id}" value="${r.id}" ${checked}> <label for="res-${r.id}">${r.title}</label></div>`;
      }).join('');

      Swal.fire({
        title: e.flight_type ? "CFI Unavailable" : `${(e.tail_number && e.tail_number !== 'null') ? e.tail_number : (e.flight_type || 'G.L.')} → ${e.student_name}`,
        html: `
          <div style="text-align:left; max-height: 300px; overflow-y: auto;">${resourceHTML}</div>
          <hr>
          <button id="select-all" class="swal2-confirm swal2-styled">Select All</button>
          <button id="deselect-all" class="swal2-cancel swal2-styled">Deselect All</button>
        `,
        didOpen: () => {
          setTimeout(() => {
            fetch('/wp-content/plugins/calendar/appointment-booking/fetch_locations.php')
              .then(res => res.json())
              .then(locations => {
                const dropdown = document.getElementById("locationSelector");
                if (!dropdown) return;

                locations.forEach(loc => {
                  const opt = document.createElement("option");
                  opt.value = loc.airport_code;
                  opt.textContent = loc.name;
                  dropdown.appendChild(opt);
                });

                dropdown.value = currentLocation || "";
                dropdown.addEventListener("change", () => {
                  currentLocation = dropdown.value;
                  renderCalendar();
                });
              })
              .catch(err => {
                console.error("❌ Failed to load locations:", err);
              });
          }, 50);

          document.getElementById("select-all").onclick = () => {
            allResources.forEach(r => selectedResourceIds.add(r.id));
            Swal.close();
            renderCalendar();
          };

          document.getElementById("deselect-all").onclick = () => {
            selectedResourceIds.clear();
            Swal.close();
            renderCalendar();
          };
        },
        preConfirm: () => {
          selectedResourceIds.clear();
          allResources.forEach(r => {
            const box = document.getElementById(`res-${r.id}`);
            if (box && box.checked) selectedResourceIds.add(r.id);
          });
        },
        confirmButtonText: "Apply"
      }).then(() => {
        renderCalendar();
      });
    };

    document.getElementById("controls").appendChild(filterBtn);
  }

  await loadData();
});





