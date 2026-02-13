function launchCreateFlightModal(prefill = {}) {
  fetch("fetch_flight_types.php")
    .then(res => res.json())
    .then(json => {
      if (!json.success || !Array.isArray(json.types)) {
        throw new Error("Flight types failed to load.");
      }

      const flightOptions = `<option disabled selected value="">-- Select Flight Type --</option>` +
        json.types
          .filter(t => !t.name.toLowerCase().includes("ground"))
          .map(t => `<option value="${t.id}">${t.name}</option>`)
          .join('');

      Swal.fire({
        title: "New Flight",
        html: `
          <div style="display:flex; flex-direction:column; gap:10px;">
            <select id="flightType" class="swal2-select">${flightOptions}</select>
            <input id="studentSearch" class="swal2-input visible" placeholder="Search Student by Last Name" title="Search by last name to find an existing student.">
            <select id="studentSelect" class="swal2-select visible" style="display:none;" title="Select a matching student"></select>
            <input id="futureStudentName" class="swal2-input hidden" placeholder="Future Student Name" title="For Discovery Flights only.">
            <input id="futureStudentPhone" class="swal2-input hidden" placeholder="Future Student Phone #" title="For Discovery Flights only.">
            <input id="tailSearch" class="swal2-input visible" placeholder="Search Aircraft (e.g. 23B)">
            <select id="tailSelect" class="swal2-select visible" style="display:none;" title="Select the aircraft to be used."></select>
            <input id="cfiSearch" class="swal2-input visible" placeholder="Search CFI" title="Required for dual flights.">
            <select id="cfiSelect" class="swal2-select visible" style="display:none;" title="Select the assigned instructor."></select>
            <input type="date" id="flightDate" class="swal2-input visible" title="Choose the flight date.">
            <select id="flightTime" class="swal2-select visible" title="Choose the flight start time.">
              ${[...Array(33)].map((_, i) => {
                const mins = 360 + i * 30;
                const h = String(Math.floor(mins / 60)).padStart(2, '0');
                const m = mins % 60 === 0 ? '00' : '30';
                return `<option value="${h}:${m}">${h}:${m}</option>`;
              }).join('')}
            </select>
          </div>`,
        showCancelButton: true,
        confirmButtonText: "Create Flight",
        didOpen: () => {
          const fType = document.getElementById("flightType");
          const sBox = document.getElementById("studentSearch");
          const sDrop = document.getElementById("studentSelect");
          const fName = document.getElementById("futureStudentName");
          const fPhone = document.getElementById("futureStudentPhone");
          const cfiBox = document.getElementById("cfiSearch");
          const cfiDrop = document.getElementById("cfiSelect");
          const tailSearch = document.getElementById("tailSearch");
          const tailSelect = document.getElementById("tailSelect");

          const toggleField = (el, show) => {
            el.classList.toggle("hidden", !show);
            el.classList.toggle("visible", show);
          };

          // Flight type toggle logic
          fType.addEventListener("change", () => {
            const selectedText = fType.options[fType.selectedIndex].text.toLowerCase();
            const isDiscovery = selectedText.includes("discovery");
            const isSolo = selectedText.includes("solo");

            // Discovery flight logic
            toggleField(fName, isDiscovery);
            toggleField(fPhone, isDiscovery);
            toggleField(sBox, !isDiscovery);
            sDrop.style.display = "none";

            if (!isDiscovery) {
              fName.value = "";
              fPhone.value = "";
            }

            // Solo logic: hide CFI fields
            toggleField(cfiBox, !isSolo);
            toggleField(cfiDrop, !isSolo);
          });

          // Tail search
          tailSearch.addEventListener("input", function () {
            fetch("fetch_aircraft.php?q=" + encodeURIComponent(this.value))
              .then(res => res.json())
              .then(matches => {
                tailSelect.style.display = matches.length ? "block" : "none";
                tailSelect.innerHTML = matches.map(a =>
                  `<option value="${a.tail_number}">${a.tail_number}</option>`
                ).join('');
                if (matches.length === 1) {
                  tailSelect.value = matches[0].tail_number;
                }
              });
          });

          // Student search
          sBox.addEventListener("input", function () {
            fetch("fetch_students.php?query=" + encodeURIComponent(this.value))
              .then(res => res.json())
              .then(matches => {
                sDrop.style.display = "block";
                sDrop.innerHTML = matches.map(s =>
                  `<option data-id="${s.student_id}" value="${s.phone}">${s.name} - ${s.phone}</option>`
                ).join('');
              });
          });

          // CFI search
          cfiBox.addEventListener("input", function () {
            fetch("fetch_cfis.php?name=" + encodeURIComponent(this.value))
              .then(res => res.json())
              .then(matches => {
                cfiDrop.style.display = "block";
                cfiDrop.innerHTML = matches.map(c =>
                  `<option value="${c.id}">${c.name}</option>`
                ).join('');
              });
          });

          // Prefill
          if (prefill.date) document.getElementById("flightDate").value = prefill.date;
          if (prefill.time) document.getElementById("flightTime").value = prefill.time;
          if (prefill.resourceId) cfiDrop.value = prefill.resourceId;
        },
        preConfirm: () => {
          const selected = document.getElementById("studentSelect").selectedOptions[0];
          return {
            flightTypeId: document.getElementById("flightType").value,
            flightTypeName: document.getElementById("flightType").selectedOptions[0]?.text || 'Flight',
            studentId: selected?.dataset?.id || null,
            studentPhone: selected?.value || '',
            futureStudentName: document.getElementById("futureStudentName").value.trim(),
            futureStudentPhone: document.getElementById("futureStudentPhone").value.trim(),
            tailNumber: document.getElementById("tailSelect").value || '',
            cfiId: document.getElementById("cfiSelect").value,
            date: document.getElementById("flightDate").value,
            time: document.getElementById("flightTime").value
          };
        }
      }).then(result => {
        if (result.isConfirmed) {
          createFlight(result.value);
        }
      });
    })
    .catch(err => {
      console.error("Failed to load flight types:", err);
      Swal.fire("Error", "Could not load flight types. Try again later.", "error");
    });
}

// ✅ Final createFlight function
function createFlight(data) {
  Swal.fire({ title: "Creating Flight...", didOpen: () => Swal.showLoading() });

  fetch("create_flight.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  })
    .then(res => {
      return res.json().catch(err => {
        console.error("❌ JSON parse failed:", err);
        throw err;
      });
    })
    .then(resp => {
      console.log("📦 Response from create_flight.php:", resp);
      if (resp.success && Array.isArray(resp.events)) {
        resp.events.forEach(e => calendarInstance.addEvent(e));
        Swal.fire("✅ Flight Created", "The flight has been scheduled successfully.", "success");
      } else {
        Swal.fire("Error", resp.message || "Flight creation failed.", "error");
      }
    })
    .catch(err => {
      console.error("❌ Flight booking failed:", err);
      Swal.fire("Error", "Could not book the flight due to a network or server error.", "error");
    });
}
