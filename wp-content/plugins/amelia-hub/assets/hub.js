(function () {
  const roleButtons = document.querySelectorAll('[data-role-target]');
  const rolePanels = document.querySelectorAll('[data-role-panel]');

  roleButtons.forEach((button) => {
    button.addEventListener('click', () => {
      roleButtons.forEach((item) => item.classList.remove('is-active'));
      rolePanels.forEach((item) => item.classList.remove('is-active'));

      button.classList.add('is-active');
      const panel = document.querySelector(`[data-role-panel="${button.dataset.roleTarget}"]`);
      if (panel) panel.classList.add('is-active');
    });
  });

  const fleetContainer = document.getElementById('amelia-fleet-results');
  if (fleetContainer) {
    loadFleet(fleetContainer);
  }

  const historyForm = document.getElementById('amelia-student-history-form');
  if (historyForm) {
    historyForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const lookup = document.getElementById('amelia-student-lookup').value.trim();
      const target = document.getElementById('amelia-student-history-results');

      if (!lookup) {
        target.innerHTML = '<p class="amelia-error">Please enter an email or phone.</p>';
        return;
      }

      target.innerHTML = '<p>Loading history…</p>';
      const params = new URLSearchParams({
        action: 'amelia_get_student_history',
        nonce: ameliaHub.nonce,
        lookup,
      });

      const response = await fetch(ameliaHub.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString(),
      });

      const payload = await response.json();
      if (!payload.success) {
        target.innerHTML = `<p class="amelia-error">${payload.data.message}</p>`;
        return;
      }

      target.innerHTML = renderStudentHistory(payload.data.student, payload.data.flights);
    });
  }

  async function loadFleet(container) {
    const params = new URLSearchParams({
      action: 'amelia_get_fleet',
      nonce: ameliaHub.nonce,
    });

    try {
      const response = await fetch(ameliaHub.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString(),
      });

      const payload = await response.json();
      if (!payload.success) {
        container.innerHTML = '<p class="amelia-error">Unable to load fleet details.</p>';
        return;
      }

      container.innerHTML = renderFleet(payload.data.fleet || []);
    } catch (error) {
      container.innerHTML = '<p class="amelia-error">Unable to load fleet details.</p>';
    }
  }

  function renderFleet(fleet) {
    if (!fleet.length) return '<p>No aircraft records found.</p>';

    const rows = fleet
      .map((aircraft) => `
      <tr>
        <td>${aircraft.tail_number || ''}</td>
        <td>${aircraft.model || ''}</td>
        <td>${aircraft.aircraft_type || ''}</td>
        <td>${aircraft.home_airport || ''}</td>
        <td>${aircraft.status || 'Unknown'}</td>
      </tr>
    `)
      .join('');

    return `
      <table class="amelia-table">
        <thead>
          <tr>
            <th>Tail #</th>
            <th>Model</th>
            <th>Type</th>
            <th>Home Airport</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    `;
  }

  function renderStudentHistory(student, flights) {
    const studentName = `${student.first_name || ''} ${student.last_name || ''}`.trim();
    if (!flights.length) {
      return `<p><strong>${studentName}</strong> has no flights yet.</p>`;
    }

    const rows = flights
      .map(
        (flight) => `
      <tr>
        <td>${new Date(flight.start_time).toLocaleString()}</td>
        <td>${flight.flight_type || ''}</td>
        <td>${flight.tail_number || ''}</td>
        <td>${flight.cfi_email || 'TBD'}</td>
        <td>${flight.status || ''}</td>
      </tr>
    `,
      )
      .join('');

    return `
      <h4>${studentName}</h4>
      <table class="amelia-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Flight Type</th>
            <th>Aircraft</th>
            <th>Instructor</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    `;
  }
})();
