function launchGroundLessonModal(prefill = {}) {
  Swal.fire({
    title: "New Ground Lesson",
    html: `
      <div style="display:flex; flex-direction:column; gap:10px;">
        <input id="glCfiSearch" class="swal2-input" placeholder="Search CFI by Name">
        <select id="glCfiSelect" class="swal2-select" style="display:none;"></select>

        <input id="glStudentSearch" class="swal2-input" placeholder="Search Student by Last Name">
        <div id="glStudentResults" style="max-height:100px; overflow-y:auto; font-size:0.9rem;"></div>
        <div id="glSelectedStudents" style="display:flex; flex-wrap:wrap; gap:6px;"></div>
        <div style="font-size: 0.75rem; color: #555; margin-top: 4px;">
        Limit 4 students. Click name to remove.
        </div>
        <select id="glDuration" class="swal2-select">
          <option value="60">60 minutes</option>
          <option value="90">90 minutes</option>
          <option value="120">120 minutes</option>
        </select>

        <input type="date" id="glDate" class="swal2-input">
        <select id="glTime" class="swal2-select">
          ${[...Array(33)].map((_, i) => {
            const mins = 360 + i * 30;
            const h = String(Math.floor(mins / 60)).padStart(2, '0');
            const m = mins % 60 === 0 ? '00' : '30';
            return `<option value="${h}:${m}">${h}:${m}</option>`;
          }).join('')}
        </select>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: "Create Lesson",
    didOpen: () => {
      const selectedStudents = [];
      Swal.getPopup().selectedStudents = selectedStudents;

      const studentSearch = document.getElementById("glStudentSearch");
      const studentResults = document.getElementById("glStudentResults");
      const selectedBox = document.getElementById("glSelectedStudents");

      const cfiSearch = document.getElementById("glCfiSearch");
      const cfiSelect = document.getElementById("glCfiSelect");

      // CFI search
      cfiSearch.addEventListener("input", () => {
        fetch("fetch_cfis.php?name=" + encodeURIComponent(cfiSearch.value))
          .then(res => res.json())
          .then(matches => {
            cfiSelect.style.display = "block";
            cfiSelect.innerHTML = matches.map(c =>
              `<option value="${c.id}" data-name="${c.name}" data-phone="${c.phone}">${c.name}</option>`
              ).join('');
          });
      });

      // Student search
      studentSearch.addEventListener("input", () => {
  fetch("fetch_students.php?query=" + encodeURIComponent(studentSearch.value))
    .then(res => res.json())
    .then(matches => {
      studentResults.innerHTML = matches.map(s =>
  `<div class="student-result" 
     data-id="${s.student_id}" 
     data-name="${s.name}" 
     data-phone="${s.phone}" 
     data-email="${s.email}" 
     style="cursor:pointer; padding:4px;">
     ${s.name} - ${s.phone}
   </div>`
).join('');

      bindStudentClickHandlers(); // ✅ bind clicks after rendering
    });
});

function bindStudentClickHandlers() {
  document.querySelectorAll(".student-result").forEach(el => {
    el.onclick = () => {
      const id = el.dataset.id;
      const name = el.dataset.name;
      const phone = el.dataset.phone;
      const email = el.dataset.email;

      if (selectedStudents.length >= 4 || selectedStudents.some(s => s.id === id)) return;

      selectedStudents.push({ id, name, phone, email });

      const pill = document.createElement("div");
      pill.textContent = name;
      pill.className = "student-pill";
      pill.style = "background:#2563eb;color:white;padding:4px 8px;border-radius:4px;cursor:pointer;";
      pill.onclick = () => {
        selectedBox.removeChild(pill);
        const idx = selectedStudents.findIndex(s => s.id === id);
        if (idx !== -1) selectedStudents.splice(idx, 1);
      };
      selectedBox.appendChild(pill);
    };
  });
}

      // Prefill logic
      if (prefill.date) document.getElementById("glDate").value = prefill.date;
      if (prefill.time) document.getElementById("glTime").value = prefill.time;
      if (prefill.resourceId) cfiSelect.value = prefill.resourceId;
    },
    preConfirm: () => {
      const selectedStudents = Swal.getPopup().selectedStudents || [];
      const date = document.getElementById("glDate").value;
      const time = document.getElementById("glTime").value;
      const cfiSelectEl = document.getElementById("glCfiSelect");
      const cfiId = cfiSelectEl.value;
      const cfiName = cfiSelectEl.options[cfiSelectEl.selectedIndex].dataset.name;
      const cfiPhone = cfiSelectEl.options[cfiSelectEl.selectedIndex].dataset.phone;

      const duration = parseInt(document.getElementById("glDuration").value);

      if (!date || !time || !cfiId || !duration || selectedStudents.length === 0) {
        Swal.showValidationMessage("All fields required + at least 1 student.");
        return false;
      }

      return {
        students: selectedStudents,
        cfiId,
        cfiName,
        cfiPhone,
        duration,
        date,
        time
      };
    }
  }).then(result => {
    if (result.isConfirmed) {
      createGroundLesson(result.value);
    }
  });
}

