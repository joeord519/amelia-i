document.addEventListener("DOMContentLoaded", () => {
  // Allow parent pages to tell this page to refresh
  window.addEventListener("message", (event) => {
    if (event.data?.action === "refreshCalendar") {
      console.log("🔄 Received refresh message, reloading calendar.");
      loadData();
    }
  });

  let allEvents = [];
  let allResources = [];
  let calendarInstance = null;

  let currentLocation = "";
  let selectedResourceIds = new Set();

  async function loadData() {
    try {
      const res = await fetch("get-events.php?" + Date.now());
      const data = await res.json();

      console.log("Student calendar data:", data);

      if (data.error) {
        console.error("Server error from get-events.php:", data.error);
        Swal.fire("Error", data.error, "error");
      }

      allEvents = data.events || [];
      allResources = data.resources || [];

      if (selectedResourceIds.size === 0) {
        allResources.forEach((r) => selectedResourceIds.add(r.id));
      }

      renderCalendar();
    } catch (err) {
      console.error("❌ Failed to load events:", err);
      Swal.fire("Error", "Could not load calendar data.", "error");
      // Still render an empty calendar so students see *something*
      renderCalendar();
    }
  }

  function getUniqueAircraft(resources) {
    return resources.filter((r) => r.group === "Aircraft");
  }

  function getUniqueCFIs(resources) {
    return resources.filter((r) => r.group === "CFI");
  }

  function getFilteredEvents() {
    return allEvents.filter((e) =>
      currentLocation ? e.airport_code === currentLocation : true
    );
  }

  function getColorForResource(id) {
    const colors = [
      "#fee2e2",
      "#dbeafe",
      "#d1fae5",
      "#fef3c7",
      "#ede9fe",
      "#f3e8ff",
      "#fcd34d",
    ];
    let hash = 0;
    for (let i = 0; i < String(id).length; i++) {
      hash = String(id).charCodeAt(i) + ((hash << 5) - hash);
    }
    return colors[Math.abs(hash) % colors.length];
  }

  function renderCalendar() {
    const calendarEl = document.getElementById("calendar");
    if (!calendarEl) {
      console.error("❌ #calendar element not found");
      return;
    }
    calendarEl.innerHTML = "";

    const aircraft = getUniqueAircraft(allResources);
    const cfis = getUniqueCFIs(allResources);
    const resourcesCombined = [...aircraft, ...cfis];

    const filteredResources = resourcesCombined
      .filter((r) => selectedResourceIds.has(r.id))
      .map((r) => ({
        ...r,
        eventBackgroundColor: getColorForResource(r.id),
        eventBorderColor: "#000",
        className: `res-${r.id}`,
      }));

    // Use the same pattern as admin calendar: FullCalendar.Calendar
    calendarInstance = new FullCalendar.Calendar(calendarEl, {
      schedulerLicenseKey: "0645855535-fcs-1739777982",
      initialView: "resourceTimelineDay",
      slotMinTime: "06:00:00",
      slotMaxTime: "22:00:00",
      height: "auto",
      nowIndicator: true,
      resourceAreaHeaderContent: "Resources",
      resources: filteredResources,

      events: getFilteredEvents().flatMap((e) => {
        const isCFIBlock =
          e.flight_type === "CFI Unavailable" ||
          e.flight_type === "CFI-Event";

        const baseTitle = isCFIBlock
          ? "CFI Unavailable"
          : `${
              e.tail_number && e.tail_number !== "null"
                ? e.tail_number
                : e.flight_type || "G.L."
            } → ${e.student_name || e.future_student_name || ""}`;

        const baseEvent = {
          title: baseTitle,
          start: e.start,
          end: e.end,
          backgroundColor: isCFIBlock ? "#dc2626" : e.color || "#2563eb",
          borderColor: "#000",
          textColor: "#ffffff",
          classNames: isCFIBlock ? ["fc-event", "fc-cfi-block"] : [],
          extendedProps: e,
        };

        const events = [];

        if (e.tail_number && selectedResourceIds.has(e.tail_number)) {
          events.push({ ...baseEvent, resourceId: e.tail_number });
        }

        if (e.cfi_id && selectedResourceIds.has(String(e.cfi_id))) {
          events.push({ ...baseEvent, resourceId: String(e.cfi_id) });
        }

        return events;
      }),

      eventClick: function (info) {
        const e = info.event.extendedProps;
        const isCFIBlock =
          e.flight_type === "CFI Unavailable" ||
          e.flight_type === "CFI-Event";

        const title = isCFIBlock
          ? "CFI Unavailable"
          : `${
              e.tail_number && e.tail_number !== "null"
                ? e.tail_number
                : e.flight_type || "G.L."
            } → ${e.student_name || e.future_student_name || ""}`;

        Swal.fire({
          title: title,
          html: `
            <b>🧑‍✈️ CFI:</b> ${e.cfi_name || "N/A"} ${e.cfi_phone || ""}<br>
            <b>🧍 Student:</b> ${e.student_name || e.future_student_name || "N/A"}<br>
            <b>📍 Location:</b> ${e.airport_code || "N/A"}<br>
            <b>🕒</b> ${new Date(e.start).toLocaleString()} – ${new Date(
            e.end
          ).toLocaleString()}
          `,
          confirmButtonText: "Close",
        });
      },
    });

    calendarInstance.render();
    calendarInstance.gotoDate(new Date());
  }

  // Date range buttons
  document.querySelectorAll("button[data-range]").forEach((btn) => {
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
            end: end7.toISOString().split("T")[0],
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
            end: end.toISOString().split("T")[0],
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
    filterBtn.style.marginLeft = "10px";
    filterBtn.onclick = () => {
      const resourceHTML = allResources
        .map((r) => {
          const checked = selectedResourceIds.has(r.id) ? "checked" : "";
          return `<div><input type="checkbox" id="res-${r.id}" value="${r.id}" ${checked}> <label for="res-${r.id}">${r.title}</label></div>`;
        })
        .join("");

      Swal.fire({
        title: "Filter Resources",
        html: `
          <div style="text-align:left; max-height: 260px; overflow-y: auto; margin-bottom:10px;">
            ${resourceHTML}
          </div>
          <hr>
          <div style="text-align:left; margin-bottom:8px;">
            <label for="locationSelector"><b>Location:</b></label>
            <select id="locationSelector" class="swal2-select" style="width:100%; margin-top:4px;">
              <option value="">All Locations</option>
            </select>
          </div>
          <button id="select-all" class="swal2-confirm swal2-styled" style="margin-right:10px;">Select All</button>
          <button id="deselect-all" class="swal2-cancel swal2-styled">Deselect All</button>
        `,
        didOpen: () => {
          // Load locations into dropdown (optional)
          fetch("/wp-content/plugins/calendar/appointment-booking/fetch_locations.php")
            .then((res) => res.json())
            .then((locations) => {
              const dropdown = document.getElementById("locationSelector");
              if (!dropdown) return;

              locations.forEach((loc) => {
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
            .catch((err) => {
              console.error("❌ Failed to load locations:", err);
            });

          document.getElementById("select-all").onclick = () => {
            allResources.forEach((r) => selectedResourceIds.add(r.id));
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
          allResources.forEach((r) => {
            const box = document.getElementById(`res-${r.id}`);
            if (box && box.checked) selectedResourceIds.add(r.id);
          });
        },
        confirmButtonText: "Apply",
      }).then(() => {
        renderCalendar();
      });
    };

    document.getElementById("controls").appendChild(filterBtn);
  }

  // Kick everything off
  loadData();
});

