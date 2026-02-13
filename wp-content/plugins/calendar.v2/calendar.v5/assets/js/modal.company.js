function launchCompanyEventModal() {
  Swal.fire({
    title: "New Company Event",
    html: `
      <div style="display:flex; flex-direction:column; gap:10px;">
        <input id="companyTitle" class="swal2-input" placeholder="Event Title">
        <input type="date" id="companyDate" class="swal2-input">
        <select id="companyTime" class="swal2-select">
          ${[...Array(33)].map((_, i) => {
            const mins = 360 + i * 30;
            const h = String(Math.floor(mins / 60)).padStart(2, '0');
            const m = mins % 60 === 0 ? '00' : '30';
            return `<option value="${h}:${m}">${h}:${m}</option>`;
          }).join('')}
        </select>
        <input id="companyDuration" class="swal2-input" type="number" placeholder="Duration in minutes" value="60">
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: "Next",
    preConfirm: () => {
      const title = document.getElementById("companyTitle").value.trim();
      const date = document.getElementById("companyDate").value;
      const time = document.getElementById("companyTime").value;
      const duration = parseInt(document.getElementById("companyDuration").value);

      if (!title || !date || !time || !duration) {
        Swal.showValidationMessage("All fields are required.");
        return false;
      }

      return { title, date, time, duration };
    }
  }).then(initial => {
    if (!initial.isConfirmed) return;

    // Fetch resources next
    fetch("fetch_resources.php")
      .then(res => res.json())
      .then(resources => {
        const cfis = resources.filter(r => r.extendedProps?.group === "CFI");
        const aircraft = resources.filter(r => r.extendedProps?.group === "Aircraft");

        const cfiOptions = cfis.map(cfi => `
          <label style="display:block;">
            <input type="checkbox" value="${cfi.id}" class="company-cfi"> ${cfi.title}
          </label>`).join('');

        const aircraftOptions = aircraft.map(ac => `
          <label style="display:block;">
            <input type="checkbox" value="${ac.id}" class="company-aircraft"> ${ac.title}
          </label>`).join('');

        Swal.fire({
          title: "Who's Attending, and What Aircraft?",
          html: `
            <div style="text-align:left;">
              <strong>CFIs:</strong><br>
              <label><input type="checkbox" id="selectAllCFIs"> Select all CFIs</label><br>
              <div id="cfiCheckboxes" style="margin-bottom:10px;">${cfiOptions}</div>

              <strong>Aircraft:</strong><br>
              <label><input type="checkbox" id="selectAllAircraft"> Select all Aircraft</label><br>
              <div id="aircraftCheckboxes">${aircraftOptions}</div>
            </div>
          `,
          showCancelButton: true,
          confirmButtonText: "Create Event",
          didOpen: () => {
            document.getElementById("selectAllCFIs").addEventListener("change", function () {
              document.querySelectorAll(".company-cfi").forEach(cb => cb.checked = this.checked);
            });

            document.getElementById("selectAllAircraft").addEventListener("change", function () {
              document.querySelectorAll(".company-aircraft").forEach(cb => cb.checked = this.checked);
            });
          },
          preConfirm: () => {
            const selectedCFIs = Array.from(document.querySelectorAll(".company-cfi:checked")).map(c => c.value);
            const selectedAircraft = Array.from(document.querySelectorAll(".company-aircraft:checked")).map(c => c.value);

            if (selectedCFIs.length === 0 && selectedAircraft.length === 0) {
              Swal.showValidationMessage("Please select at least one CFI or Aircraft.");
              return false;
            }

            return {
              ...initial.value,
              cfis: selectedCFIs,
              aircraft: selectedAircraft
            };
          }
        }).then(final => {
          if (!final.isConfirmed) return;

          Swal.fire({ title: "Creating Event...", didOpen: () => Swal.showLoading() });

          fetch("create_company_event.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(final.value)
          })
            .then(res => res.json())
            .then(resp => {
              if (resp.success && Array.isArray(resp.events)) {
                Swal.fire("✅ Event Created", "Company event has been added.", "success");

                calendarInstance.refetchEvents();

              } else {
                Swal.fire("Error", resp.message || "Could not create company event", "error");
              }
            });
        });
      });
  });
}
