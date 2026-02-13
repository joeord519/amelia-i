<?php
require_once(__DIR__ . '/db_connect.php');
$db = getDB();
$id = intval($_GET['id'] ?? 0);
$token = $_GET['token'] ?? '';
if ($id <= 0 || empty($token)) die('Invalid flight log link.');
if ($id <= 0) die("Invalid flight ID");

$stmt = $db->prepare("
  SELECT f.*, s.first_name, s.last_name
  FROM wp_flight_logs f
  JOIN wp_students s ON f.student_id = s.student_id
  WHERE f.id = :id AND f.cfi_token = :token
");
$stmt->execute([':id' => $id, ':token' => $token]);
$log = $stmt->fetch();
if (!$log) die("Flight log not found.");

// ✅ Dual-entry protection
if ($log['cfi_log_submitted']) {
  echo "<!DOCTYPE html>
  <html><head>
    <title>Already Submitted</title>
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
  </head><body>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        Swal.fire({
          icon: 'info',
          title: '✅ Already Submitted',
          text: 'This flight log has already been completed.',
          confirmButtonText: 'OK'
        }).then(() => {
          window.location.href = 'https://pistonaviation.slack.com';
        });
      });
    </script>
  </body></html>";
  exit;
}

$student = $log['first_name'] . ' ' . $log['last_name'];
$tail = $log['tail_number'];
$originalTime = floatval($log['total_flight_time']);
?>

<!DOCTYPE html>
<html>
<head>
  <title>CFI Flight Log</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.5/dist/signature_pad.umd.min.js"></script>
  <style>
    body { margin: 0; }
    canvas { border: 1px solid #ccc; width: 100%; height: 150px; }
    .swal2-popup {
      width: 95vw !important;
      max-width: 600px;
    }
    @media (max-width: 600px) {
      .swal2-popup { font-size: 1rem; }
    }
  </style>
</head>
<body>
<noscript>
  <p style="text-align:center;">Please enable JavaScript or open this link in a browser.</p>
</noscript>
<div id="fallback" style="display:none; text-align:center;">
  If nothing appears, <a href="#" onclick="window.location.reload();">tap to reload</a> or open in Safari/Chrome.
</div>

<script>
$(document).ready(function () {
  let signatureData = '';
  let isEditingTime = false;

  Swal.fire({
    title: '✍️ CFI Flight Log',
    html: `
      <div style="text-align:left;">
        <p><strong>Student:</strong> <?= htmlspecialchars($student) ?><br>
           <strong>Tail:</strong> <?= htmlspecialchars($tail) ?><br>
           <strong>Logged Time:</strong> <span id="loggedTime"><?= number_format($originalTime, 1) ?> hrs</span></p>

        <button id="editTimeBtn" type="button">✏️ Modify Flight Time</button>
        <div id="editTimeSection" style="display:none; margin-top:10px;">
          <label>New Flight Time:</label>
          <input type="number" step="0.1" id="flight_time" class="swal2-input" placeholder="Flight Time">
          <input type="text" id="discrepancy_reason" class="swal2-input" placeholder="Discrepancy Reason (required)">
        </div>

        <input type="number" step="0.1" id="ground_time" class="swal2-input" placeholder="Ground Time (hrs)">
        <textarea id="notes" class="swal2-textarea" placeholder="Student Progress Notes (required)"></textarea>

        <label>Draw Your Signature:</label>
        <canvas id="sigCanvas"></canvas>
        <button id="clearSig" type="button">Clear Signature</button>

        <div style="margin-top:10px;">
          <input type="checkbox" id="reviewed">
          <label for="reviewed">Mark this flight as reviewed</label>
        </div>
      </div>
    `,
    willOpen: () => {
      const canvas = document.getElementById('sigCanvas');
      const signaturePad = new SignaturePad(canvas);
      window.signaturePad = signaturePad;

      $('#editTimeBtn').on('click', function () {
        $('#editTimeSection').slideDown();
        isEditingTime = true;
      });

      $('#clearSig').on('click', function () {
        signaturePad.clear();
      });
    },
    preConfirm: () => {
      const flight = isEditingTime ? parseFloat($('#flight_time').val()) || 0 : <?= $originalTime ?>;
      const reason = $('#discrepancy_reason').val().trim();
      const notes = $('#notes').val().trim();
      const ground = parseFloat($('#ground_time').val()) || 0;
      const reviewed = $('#reviewed').is(':checked') ? 1 : 0;

      if (!notes) return Swal.showValidationMessage('Student progress notes are required.');
      if (isEditingTime && reason === '') return Swal.showValidationMessage('Discrepancy reason is required.');
      if (window.signaturePad.isEmpty()) return Swal.showValidationMessage('Signature is required.');

      signatureData = window.signaturePad.toDataURL();

      return {
        id: <?= $id ?>,
        original_time: <?= $originalTime ?>,
        flight_time: flight,
        discrepancy_reason: reason,
        ground_time: ground,
        notes: notes,
        signature: signatureData,
        reviewed: reviewed
      };
    },
    confirmButtonText: 'Submit Log'
  }).then(result => {
    if (!result.value) return;
    $.ajax({
      url: 'submit_cfi_log.php',
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify(result.value),
      success: function (res) {
        if (res.status === 'success') {
          Swal.fire('✅ Success', 'Flight log submitted.', 'success').then(() => {
            window.location.href = 'https://pistonaviation.slack.com';
          });
        } else {
          Swal.fire('Error', res.message || 'Unknown error.', 'error');
        }
      },
      error: function (xhr) {
        Swal.fire('Server Error', xhr.responseText, 'error');
      }
    });
  });

  // Slack browser fallback check
  setTimeout(() => {
    if (!document.querySelector('.swal2-container')) {
      document.getElementById('fallback').style.display = 'block';
    }
  }, 2500);
});
</script>
</body>
</html>
