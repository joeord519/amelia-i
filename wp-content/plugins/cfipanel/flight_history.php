<?php
// /wp-content/plugins/cfipanel/flight_history.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once(__DIR__ . '/db_connect.php');
$db = getDB();

$cfi_id = $_SESSION['cfi_id'] ?? 0;

try {
  $stmt = $db->prepare("
    SELECT fl.*, 
           CONCAT(s.first_name, ' ', s.last_name) AS student_name, 
           a.tail_number 
    FROM wp_flight_logs fl
    LEFT JOIN wp_students s ON fl.student_id = s.student_id
    LEFT JOIN wp_aircraft a ON fl.tail_number = a.tail_number
    WHERE fl.cfi_id = :cfi_id
    ORDER BY fl.flight_date DESC
  ");
  $stmt->execute([':cfi_id' => $cfi_id]);
  $logs = $stmt->fetchAll();
} catch (PDOException $e) {
  echo "<pre>SQL Error: " . $e->getMessage() . "</pre>";
  exit;
}
?>


<!-- ✅ Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<style>
.exportBtn {
  background-color: #3c8dbc !important;
  color: white !important;
  font-weight: bold;
  border: none !important;
  padding: 6px 14px !important;
  margin: 5px 10px 15px 0 !important;
  border-radius: 6px;
}
</style>


<style>
.exportBtn {
  background-color: #3c8dbc !important;
  color: white !important;
  font-weight: bold;
  border: none !important;
  padding: 6px 14px !important;
  margin: 5px 10px 15px 0 !important;
  border-radius: 6px;
}
</style>


<div style="margin-bottom: 20px;">
  <button id="totalHoursBtn" class="fancyHoursBtn">
  🧮 Total My Hours
</button>
</div>

<style>
.fancyHoursBtn {
  background: linear-gradient(135deg, #28a745, #218838);
  color: white;
  font-size: 18px;
  font-weight: bold;
  padding: 12px 24px;
  border: none;
  border-radius: 10px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  transition: all 0.2s ease-in-out;
  margin-bottom: 20px;
}

.fancyHoursBtn:hover {
  background: linear-gradient(135deg, #218838, #1e7e34);
  transform: scale(1.03);
  box-shadow: 0 6px 16px rgba(0,0,0,0.2);
  cursor: pointer;
}
</style>

<!-- ✅ Filter UI -->
<div style="margin-bottom: 20px;">
  <strong>Filter by Date:</strong><br><br>
  <button class="date-filter" data-range="7">Last 7 Days</button>
  <button class="date-filter" data-range="30">Last 30 Days</button>
  <button class="date-filter" data-range="180">Last 6 Months</button>
  <button class="date-filter" data-range="all">All Time</button>
  <br><br>
  <label>From:</label>
  <input type="text" id="minDate" style="margin-right:20px;" placeholder="Start Date">
  <label>To:</label>
  <input type="text" id="maxDate" placeholder="End Date">
</div>

<!-- ✅ Flight History Table -->
<table id="historyTable" class="display" style="width:100%; border-collapse: collapse;">
  <thead>
    <tr style="background:#eee;">
      <th>Date</th>
      <th>Student</th>
      <th>Aircraft</th>
      <th>Type</th>
      <th>Flight Time</th>
      <th>Ground Time</th>
      <th>Notes</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($logs as $log): ?>
      <?php
        $formattedDate = (!empty($log['flight_date']) && $log['flight_date'] !== '0000-00-00') 
          ? htmlspecialchars($log['flight_date']) : '';

        $studentName = htmlspecialchars($log['student_name'] ?? '');
        $tail = htmlspecialchars($log['tail_number'] ?? '');
        $type = htmlspecialchars($log['flight_category'] ?? '');
        $flight = htmlspecialchars($log['total_flight_time'] ?? '');
        $ground = htmlspecialchars($log['ground_time'] ?? '');
        $notes = htmlspecialchars($log['notes'] ?? '');
      ?>
      <tr>
        <td><?= $formattedDate ?></td>
        <td><?= $studentName ?></td>
        <td><?= $tail ?></td>
        <td><?= $type ?></td>
        <td><?= ($flight !== '' && $flight !== null) ? $flight : '0.00' ?></td>
        <td><?= $ground ?></td>
        <td><?= $notes ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- ✅ DataTables + Date Filter Logic -->
<script>
let minDate, maxDate;

$.fn.dataTable.ext.search.push(function(settings, data) {
  const date = new Date(data[0]); // Date is in first column
  if ((minDate && date < minDate) || (maxDate && date > maxDate)) {
    return false;
  }
  return true;
});

$(document).ready(function () {
  
const table = $('#historyTable').DataTable({
  pageLength: 25,
  order: [[0, 'desc']],
  dom: 'Bfrtip',
  buttons: [
    {
      extend: 'csvHtml5',
      text: '📥 Download CSV',
      className: 'exportBtn'
    },
    {
      extend: 'pdfHtml5',
      text: '📄 Download PDF',
      orientation: 'landscape',
      pageSize: 'A4',
      className: 'exportBtn'
    },
    {
      extend: 'print',
      text: '🖨 Print',
      className: 'exportBtn'
    }
  ]
});


  // Initialize Flatpickr after DOM is fully ready
  setTimeout(() => {
    flatpickr("#minDate", {
      dateFormat: "Y-m-d",
      onChange: function (selectedDates) {
        minDate = selectedDates[0] ? new Date(selectedDates[0]) : null;
        table.draw();
      }
    });

    flatpickr("#maxDate", {
      dateFormat: "Y-m-d",
      onChange: function (selectedDates) {
        maxDate = selectedDates[0] ? new Date(selectedDates[0]) : null;
        table.draw();
      }
    });
  }, 100); // slight delay to ensure fields are present

  $(".date-filter").on("click", function () {
    const range = $(this).data("range");

    if (range === 'all') {
      minDate = null;
      maxDate = null;
    } else {
      const today = new Date();
      maxDate = today;
      minDate = new Date(today);
      minDate.setDate(today.getDate() - parseInt(range));
    }

    $("#minDate").val('');
    $("#maxDate").val('');
    table.draw();
  });
});
</script>

<script>
$('#totalHoursBtn').on('click', function () {
  Swal.fire({
    title: 'Total My Hours',
    html: `
      <div style="margin-bottom:10px;">
        <button type="button" class="quickRange" data-range="7">Last 7 Days</button>
        <button type="button" class="quickRange" data-range="30">Last 30 Days</button>
        <button type="button" class="quickRange" data-range="all">All Time</button>
      </div>

      <label>Date Range:</label><br>
      <input id="hoursFrom" class="swal2-input" placeholder="Start Date">
      <input id="hoursTo" class="swal2-input" placeholder="End Date"><br>

      <label>Aircraft (optional):</label><br>
      <input id="hoursAircraft" class="swal2-input" placeholder="Ex: N2723B"><br>

      <label>Student (optional):</label><br>
      <input id="hoursStudent" class="swal2-input" placeholder="Search by name">
    `,
    showCancelButton: true,
    confirmButtonText: 'Calculate',
    didOpen: () => {
      const $btn = Swal.getConfirmButton();
      $btn.disabled = true;

      const validate = () => {
        const from = $('#hoursFrom').val();
        const to = $('#hoursTo').val();
        $btn.disabled = !(from || to);
      };

      flatpickr("#hoursFrom", {
        dateFormat: "Y-m-d",
        onChange: validate
      });

      flatpickr("#hoursTo", {
        dateFormat: "Y-m-d",
        onChange: validate
      });

      $('.quickRange').on('click', function () {
        const range = $(this).data('range');
        const today = new Date();
        const to = today.toISOString().split('T')[0];
        $btn.disabled = false;

        if (range === 'all') {
          $('#hoursFrom').val('');
          $('#hoursTo').val('');
        } else {
          const from = new Date();
          from.setDate(today.getDate() - parseInt(range));
          const fromFormatted = from.toISOString().split('T')[0];
          $('#hoursFrom').val(fromFormatted);
          $('#hoursTo').val(to);
        }
      });

      // Only run autocomplete if jQuery UI is loaded
      if (typeof $.ui !== 'undefined' && $.ui.autocomplete) {
        $('#hoursStudent').autocomplete({
          source: 'search_students.php',
          minLength: 2,
          select: function (event, ui) {
            $('#hoursStudent').val(ui.item.label).data('student-id', ui.item.id);
            return false;
          }
        });
      }
    },
    preConfirm: () => {
      const data = {
        from: $('#hoursFrom').val(),
        to: $('#hoursTo').val(),
        tail_number: $('#hoursAircraft').val(),
        student_name: $('#hoursStudent').val()
      };

      return fetch('get_total_hours.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      })
      .then(res => res.json())
      .then(json => {
        if (!json || json.status !== 'success') {
          throw new Error(json.message || 'Calculation failed.');
        }
        return { ...json, filter: data };
      })
      .catch(err => {
        Swal.showValidationMessage(err.message);
      });
    }
  }).then(result => {
    if (result.isConfirmed) {
      const r = result.value;
      let filterText = '';

      if (r.filter.tail_number) {
        filterText += `<br><b>Aircraft:</b> ${r.filter.tail_number}`;
      }
      if (r.filter.student_name) {
        filterText += `<br><b>Student:</b> ${r.filter.student_name}`;
      }
      if (r.filter.from || r.filter.to) {
        filterText += `<br><b>Date Range:</b> ${r.filter.from || '—'} to ${r.filter.to || '—'}`;
      }

      Swal.fire({
        icon: 'success',
        title: 'Total Hours',
        html: `
          <b>Flight Time:</b> ${r.total_flight_time} hrs<br>
          <b>Ground Time:</b> ${r.ground_time} hrs<br>
          <b>Entries:</b> ${r.count} flights
          ${filterText}
        `
      });
    }
  });
});
</script>




