function launchDiscoveryFlightModal(prefill = {}) {
  Swal.fire({
    title: "✨ Discovery Flight",
    html: `
      <div style="display:flex; flex-direction:column; gap:10px;">
        <input id="discStudentName" class="swal2-input" placeholder="Future Student Name (First and Last)">
        <input id="discStudentPhone" class="swal2-input" placeholder="Future Student's Phone (e.g. 555-555-5555)">
        <input id="discStudentEmail" class="swal2-input" placeholder="Future Student's Email">
        <select id="discAircraft" class="swal2-select">
          <option value="">Type tail Number above to Find</option>
        </select>
        <select id="discCFI" class="swal2-select">
          <option value="">type name above to find CFI</option>
        </select>
        <input type="date" id="discDate" class="swal2-input">
        <select id="discTime" class="swal2-select">
          ${[...Array(33)].map((_, i) => {
            const mins = 360 + i * 30;
            const h = String(Math.floor(mins / 60)).padStart(2, '0');
            const m = mins % 60 === 0 ? '00' : '30';
            return `<option value="${h}:${m}">${h}:${m}</option>`;
          }).join('')}
        </select>
        <textarea id="discNotes" class="swal2-textarea" placeholder="Notes (optional)"></textarea>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: "Create Discovery",
    didOpen: () => {
      if (prefill.date) document.getElementById("discDate").value = prefill.date;
      if (prefill.time) document.getElementById("discTime").value = prefill.time;

      const phoneInput = document.getElementById("discStudentPhone");
      phoneInput.addEventListener("input", () => {
        let val = phoneInput.value.replace(/\D/g, '').slice(0, 10);
        if (val.length >= 6) {
          phoneInput.value = `(${val.slice(0, 3)}) ${val.slice(3, 6)}-${val.slice(6)}`;
        } else if (val.length >= 3) {
          phoneInput.value = `(${val.slice(0, 3)}) ${val.slice(3)}`;
        } else {
          phoneInput.value = val;
        }
      });

      const cfiSelect = document.getElementById("discCFI");
      const cfiSearch = document.createElement("input");
      cfiSearch.className = "swal2-input";
      cfiSearch.placeholder = "Search CFI";
      cfiSelect.parentNode.insertBefore(cfiSearch, cfiSelect);

      cfiSearch.addEventListener("input", () => {
        fetch("fetch_cfis.php?name=" + encodeURIComponent(cfiSearch.value))
          .then(res => res.json())
          .then(matches => {
            cfiSelect.innerHTML = matches.map(c =>
              `<option value="${c.id}">${c.name}</option>`
            ).join('');
            if (matches.length === 1) {
              cfiSelect.value = matches[0].id;
            }
          });
      });

      const acSelect = document.getElementById("discAircraft");
      const acSearch = document.createElement("input");
      acSearch.className = "swal2-input";
      acSearch.placeholder = "Search Aircraft";
      acSelect.parentNode.insertBefore(acSearch, acSelect);

      acSearch.addEventListener("input", () => {
        fetch("fetch_aircraft.php?q=" + encodeURIComponent(acSearch.value))
          .then(res => res.json())
          .then(matches => {
            acSelect.innerHTML = matches.map(a =>
              `<option value="${a.tail_number}">${a.tail_number} (${a.make_model})</option>`
            ).join('');
            if (matches.length === 1) {
              acSelect.value = matches[0].tail_number;
            }
          });
      });
    },
    preConfirm: () => {
      const name = document.getElementById("discStudentName").value.trim();
      let phone = document.getElementById("discStudentPhone").value.replace(/\D/g, '');
      phone = phone.replace(/^(\d{3})(\d{3})(\d{4})$/, '($1) $2-$3');
      const email = document.getElementById("discStudentEmail").value.trim();
      const tail = document.getElementById("discAircraft").value;
      const cfi = document.getElementById("discCFI").value;
      const date = document.getElementById("discDate").value;
      const time = document.getElementById("discTime").value;
      const notes = document.getElementById("discNotes").value.trim();

      if (!name || !phone || !email || !tail || !cfi || !date || !time) {
        Swal.showValidationMessage("All fields except notes are required.");
        return false;
      }

      return {
        student_name: name,
        student_phone: phone,
        student_email: email,
        aircraft_tail: tail,
        cfi_id: cfi,
        date,
        time,
        notes
      };
    }
  }).then(result => {
    if (result.isConfirmed) {
      createDiscoveryFlight(result.value);
    }
  });
}

function createDiscoveryFlight(data) {
  Swal.fire({ title: "Creating Discovery...", didOpen: () => Swal.showLoading() });

  fetch("create_discovery.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  })
    .then(res => res.json())
    .then(resp => {
      console.log("🧪 Discovery Response:", resp);
      if (resp.success && resp.event) {
        // 🔥 Manually inject extendedProps into the calendar event
        resp.event.extendedProps = {
          ...resp.event.extendedProps,
          student_name: data.student_name,
          student_phone: data.student_phone,
          future_student_name: data.student_name,
          future_student_phone: data.student_phone
        };

        console.log("🧪 Final Event Sent to Calendar:", resp.event);

        calendarInstance.addEvent(resp.event);
        Swal.fire("✅ Discovery Created", "Flight has been scheduled.", "success");
      } else {
        Swal.fire("Error", resp.message || "Could not create discovery flight.", "error");
      }
    })
    .catch(err => {
      console.error("Discovery booking failed:", err);
      Swal.fire("Error", "Server error while booking flight.", "error");
    });
}