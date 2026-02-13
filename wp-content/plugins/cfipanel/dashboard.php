<?php
// /wp-content/plugins/cfipanel/dashboard.php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$cfi_id = $_SESSION['cfi_id'] ?? null;
$cfi_name = $_SESSION['cfi_name'] ?? 'CFI';
require_once(__DIR__ . '/db_connect.php');
$conn = getDB();

// ✅ Handle flight cancellation submission
if (isset($_POST['submit_cancel'])) {
  $flight_id = $_POST['cancel_flight_id'];
  $reason = $_POST['cancel_reason'];
  $comments = $_POST['cancel_comments'];
  $student_name = $_POST['cancel_student_name'];
  $student_phone = $_POST['cancel_student_phone'];
  $canceled_by = 'cfi';

  $stmt = $conn->prepare("SELECT * FROM wp_flight_schedule WHERE id = ?");
  $stmt->execute([$flight_id]);
  $flight = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($flight) {
    $insert = $conn->prepare("INSERT INTO wp_canceled_flights 
      (student_name, student_phone, flight_type, cfi_id, tail_number, home_airport, appointment_date, date_booked, date_canceled, deleted_reason, deleted_by, deleted_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, CURDATE())");
    $insert->execute([
      $flight['student_name'], $flight['student_phone'], $flight['flight_type'],
      $flight['cfi_id'], $flight['tail_number'], $flight['home_airport'], $flight['appointment_date'], 
      $flight['date_booked'], $reason . ($comments ? " — " . $comments : ""), $canceled_by
    ]);

    $conn->prepare("DELETE FROM wp_flight_schedule WHERE id = ?")->execute([$flight_id]);

    // You can also add email sending here via Mailgun
  }
}

$wing_name = '';
$wing_logo_url = '';

$stmt = $conn->prepare("
  SELECT w.wing_name, w.logo_url
  FROM wp_cfis c
  LEFT JOIN wp_cfi_wings w ON c.wing_id = w.id
  WHERE c.cfi_id = ?
");
$stmt->execute([$cfi_id]);
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $wing_name = $row['wing_name'] ?? '';
  $wing_logo_url = $row['logo_url'] ?? '';
}

?>
<!DOCTYPE html>
<html>
<head>
  <title>CFI Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&display=swap" rel="stylesheet">

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <!-- ✅ Bootstrap (for card and btn classes) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

  <!-- ✅ DataTables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

  <!-- ✅ DataTables Buttons -->
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

  <!-- ✅ jQuery, SweetAlert2, Flatpickr, Select2 -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

  <script src="js/cfipanel.js"></script>

  <style>
    body {
      font-family: 'Montserrat', sans-serif;
      background-color: #f0f2f5;
      padding: 30px;
    }
    .dashboard-header {
      margin-bottom: 30px;
    }
    .dashboard-header h2 {
      font-size: 28px;
      margin-bottom: 15px;
    }
    .button-row button {
      font-size: 16px;
      padding: 10px 20px;
      margin-right: 15px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      background-color: #3c8dbc;
      color: white;
      transition: background-color 0.2s ease-in-out;
    }
    .button-row button:hover {
      background-color: #367fa9;
    }
    #dashboard-content {
      margin-top: 30px;
      background: white;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .swal2-container {
  z-index: 10000 !important;
}

.select2-container {
  z-index: 10001 !important;
}

.select2-dropdown {
  z-index: 10002 !important;
}

.select2-container--open {
  position: relative;
}

  </style>
</head>
<body>
  <div class="dashboard-header">
    <h2>
  Welcome, <?= htmlspecialchars($cfi_name) ?>
  <?php if ($wing_name): ?>
    <span style="margin-left: 15px; display: inline-flex; align-items: center;">
      <?php if ($wing_logo_url): ?>
        <img src="<?= $wing_logo_url ?>" alt="<?= $wing_name ?> Logo" style="height: 40px; margin-right: 10px;">
      <?php endif; ?>
      <span style="font-size: 20px; font-weight: normal; color: #555;"><?= $wing_name ?></span>
    </span>
  <?php endif; ?>
</h2>

    <div class="button-row">
      <button onclick="launchOutreachMode()" class="btn btn-success mt-2">
        🚀 Launch Student Outreach Mode
      </button>

      <button onclick="loadFlightHistory()">🛫 Flight History</button>
      <button onclick="loadEntryForm()">📝 Submit Entry</button>
      <button onclick="loadPaySummary()">💰 View My Pay</button>
      <button onclick="logout()">🚪 Logout</button>
      <button onclick="window.open('https://amelia-i.com/wp-content/plugins/calendar.v4/', '_blank')">📅 View Calendar</button>
    </div>
  </div>

  <!-- 🛫 Today’s Flights Section -->
<?php
date_default_timezone_set('America/Chicago');
$stmt = $conn->prepare("
  SELECT f.*, a.status AS aircraft_status
  FROM wp_flight_schedule f
  LEFT JOIN wp_aircraft a ON f.tail_number = a.tail_number
  WHERE f.cfi_id = :cfi_id AND DATE(f.start_time) = CURDATE()
  ORDER BY f.start_time ASC
");

$stmt->execute([':cfi_id' => $cfi_id]);
$flights = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card mt-4">
  <div class="card-header bg-info text-white">🛫 Today’s Flights</div>
  <div class="card-body">
    <?php if (count($flights) === 0): ?>
      <p>No flights scheduled today.</p>
    <?php else: ?>
      <table class="table table-bordered">
        <thead>
          <tr>
            <th>Time</th>
            <th>Student</th>
            <th>Tail #</th>
            <th>Flight Type</th>
            <th>Home Airport</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($flights as $flight): ?>
          <tr>
            <td><?= date("g:i A", strtotime($flight['start_time'])) ?></td>
            <td><?= htmlspecialchars($flight['first_name'] . ' ' . $flight['last_name']) ?></td>
            <td><?= $flight['tail_number'] ?></td>
            <td><?= $flight['flight_type'] ?></td>
            <td><?= $flight['home_airport'] ?></td>
            <td><?= $flight['aircraft_status'] ?? 'Unknown' ?></td>
            <td>
              <button class="btn btn-danger btn-sm" 
                      onclick="launchCancelModal(<?= $flight['id'] ?>, '', '')">Cancel</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>


  <div id="dashboard-content">
    <!-- 🔥 Students to Contact Module -->
    <div class="card mt-4">
      <div class="card-header bg-warning text-dark">
        🔥 Students to Contact
      </div>
      <div class="card-body" id="students-to-contact">
        <p>Loading student contact suggestions...</p>
      </div>
    </div>
  </div>

  <!-- Student Contact Module Script -->
  <script>
    $(document).ready(function() {
      $.getJSON('fetch_students_to_contact.php', function(data) {
        if (!Array.isArray(data)) {
          $('#students-to-contact').html('<p>Error loading students.</p>');
          return;
        }

        if (data.length === 0) {
          $('#students-to-contact').html('<p>No students need contact right now. 🧘‍♂️</p>');
          return;
        }

        let html = '<div class="list-group">';
        data.forEach(s => {
          let urgency = s.outreach_score >= 7 ? '🔥 High' :
                        s.outreach_score >= 4 ? '⚠️ Medium' : '💤 Low';

          html += `
            <div class="list-group-item">
              <strong>${s.first_name} ${s.last_name}</strong> (${urgency})<br>
              📞 <a href="tel:${s.phone}">${s.phone}</a><br>
              ✈️ Last Flight: ${s.last_flight_date || 'N/A'}<br>
              📘 Lesson: ${s.current_lesson || 'N/A'}<br>
              ⛽ Aircraft: ${s.aircraft_hours_remaining} | 🧑‍🏫 Instructor: ${s.instructor_hours_remaining}<br>
              <button class="btn btn-sm btn-outline-primary mt-2" onclick="copyTextTemplate('${s.first_name}', '${s.phone}')">📋 Copy Text</button>
              <button 
  class="btn btn-warning sendPaymentLinkBtn" 
  data-id="${s.student_id}" 
  data-name="${s.first_name}" 
  data-email="${s.email}">
  💳 Send Payment Link
</button>

            </div>
          `;
        });
        html += '</div>';
        $('#students-to-contact').html(html);
      });
    });

    function copyTextTemplate(name, phone) {
      const message = `Hey ${name}! It’s your instructor at Piston. Ready to fly? Let’s get you back in the air! 🛫`;
      navigator.clipboard.writeText(message).then(() => {
        Swal.fire('Copied!', `Message for ${name} copied to clipboard.`, 'success');
      });
    }
  </script>

<script>
function launchOutreachMode() {
  $.getJSON('fetch_students_to_contact.php', function(students) {
    if (!Array.isArray(students) || students.length === 0) {
      Swal.fire('😎 No outreach needed right now!');
      return;
    }

    let index = 0;

    function showStudent(i) {
      const s = students[i];
      if (!s) {
        Swal.fire('✅ All caught up!', 'You’ve reached out to all students.', 'success');
        return;
      }

      const urgency = s.outreach_score >= 7 ? '🔥 High' :
                      s.outreach_score >= 4 ? '⚠️ Medium' : '💤 Low';

      Swal.fire({
  title: `${s.first_name} ${s.last_name}`,
  html: `
    <div style="font-size:16px; text-align:left; margin-top:10px;">
      <b>Urgency:</b> ${urgency}<br>
      <b>Phone:</b> <a href="tel:${s.phone}">${s.phone}</a><br>
      <b>Lesson:</b> ${s.current_lesson || 'N/A'}<br>
      <b>Last Flight:</b> ${s.last_flight_date || 'N/A'}<br>
      <b>Aircraft Hours:</b> ${s.aircraft_hours_remaining}<br>
      <b>Instructor Hours:</b> ${s.instructor_hours_remaining}
    </div>
    <div class="mt-3">
      <button id="sendPaymentLinkBtn" class="btn btn-outline-primary w-100">
        💳 Send Payment Link
      </button>
    </div>
  `,
  showConfirmButton: true,
  confirmButtonText: '📞 Call',
  showDenyButton: true,
  denyButtonText: '📋 Copy Text',
  showCancelButton: true,
  cancelButtonText: '⏭️ Skip',
  backdrop: true,
  allowOutsideClick: false,
  allowEscapeKey: false,

  didOpen: () => {
    $('#sendPaymentLinkBtn').click(() => {
      // 👇 You can directly call the same SweetAlert for payment link here
      Swal.fire({
        title: 'Send Payment Link',
        html: `
          <input type="hidden" id="student_id" value="${s.student_id}">
          <input type="number" id="aircraft_hours" class="swal2-input" placeholder="Aircraft Hours">
          <input type="number" id="instructor_hours" class="swal2-input" placeholder="Instructor Hours">
          <input type="text" id="coupon_code" class="swal2-input" placeholder="Coupon Code (optional)">
        `,
        preConfirm: () => {
          return {
            student_id: s.student_id,
            aircraft_hours: document.getElementById('aircraft_hours').value,
            instructor_hours: document.getElementById('instructor_hours').value,
            coupon_code: document.getElementById('coupon_code').value
          };
        },
        confirmButtonText: 'Generate Link'
      }).then((result) => {
        if (result.isConfirmed) {
          $.post('/wp-content/plugins/pistonpay/create_payment.php', result.value, function (res) {
            if (res.success) {
              Swal.fire({
                title: '✅ Payment Link Ready',
                html: `
                  <a href="${res.url}" class="btn btn-primary" target="_blank" style="margin-bottom: 15px;">
                    💳 Click Here to Pay
                  </a><br>
                  <button id="emailStudentLink" class="btn btn-outline-secondary">
                    📧 Email This Link to Student
                  </button>
                `,
                icon: 'success',
                didOpen: () => {
                  $('#emailStudentLink').click(() => {
                    $.post('/wp-content/plugins/checkout/email_payment_link.php', {
                      student_id: s.student_id,
                      payment_url: res.url
                    }, function (emailRes) {
                      if (emailRes.success) {
                        Swal.fire('📤 Email Sent', 'The student has received the payment link.', 'success');
                      } else {
                        Swal.fire('❌ Email Failed', emailRes.message, 'error');
                      }
                    });
                  });
                }
              });
            } else {
              Swal.fire('❌ Error', res.message, 'error');
            }
          });
        }
      });
    });
  }
});


    showStudent(index);
  });
}
</script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">

<script>
$(document).ready(function () {
  $(document).on('click', '#sendPaymentLinkTop', function () {
    Swal.fire({
      title: 'Send Payment Link to Student',
      html: `
        <input type="text" id="student_lookup" class="swal2-input" placeholder="Search Student by Name">
        <input type="hidden" id="student_id">
        <input type="number" id="aircraft_hours" class="swal2-input" placeholder="Aircraft Hours">
        <input type="number" id="instructor_hours" class="swal2-input" placeholder="Instructor Hours">
        <input type="text" id="coupon_code" class="swal2-input" placeholder="Coupon Code (optional)">
      `,
      didOpen: () => {
        $('#student_lookup').autocomplete({
          source: function (request, response) {
            $.post('/wp-content/plugins/checkout/fetch_students.php', { query: request.term }, function (data) {
              response(data.map(student => ({
                label: student.name + ' (ID: ' + student.id + ')',
                value: student.name,
                id: student.id
              })));
            });
          },
          minLength: 2,
          select: function (event, ui) {
            $('#student_lookup').val(ui.item.value);
            $('#student_id').val(ui.item.id);
            return false;
          }
        });
      },
      preConfirm: () => {
        const id = document.getElementById('student_id').value;
        if (!id) {
          Swal.showValidationMessage('Please select a student from the list');
          return false;
        }
        return {
          student_id: id,
          aircraft_hours: document.getElementById('aircraft_hours').value,
          instructor_hours: document.getElementById('instructor_hours').value,
          coupon_code: document.getElementById('coupon_code').value
        };
      },
      confirmButtonText: 'Generate Link'
    }).then((result) => {
      if (result.isConfirmed) {
        $.post('/wp-content/plugins/checkout/create_payment.php', result.value, function (res) {
          if (res.success) {
            Swal.fire({
              title: '✅ Payment Link Ready',
              html: `
                <a href="${res.url}" class="btn btn-primary" target="_blank" style="margin-bottom: 15px;">
                  💳 Click Here to Pay
                </a><br>
                <button id="emailStudentLink" class="btn btn-outline-secondary">
                  📧 Email This Link to Student
                </button>
              `,
              icon: 'success',
              didOpen: () => {
                $('#emailStudentLink').click(() => {
                  $.post('/wp-content/plugins/checkout/email_payment_link.php', {
                    student_id: result.value.student_id,
                    payment_url: res.url
                  }, function (emailRes) {
                    if (emailRes.success) {
                      Swal.fire('📤 Email Sent', 'The student has received the payment link.', 'success');
                    } else {
                      Swal.fire('❌ Email Failed', emailRes.message, 'error');
                    }
                  });
                });
              }
            });
          } else {
            Swal.fire('❌ Error', res.message, 'error');
          }
        });
      }
    });
  });
});
</script>

<!-- ✅ Universal Payment Modal Handler -->
<script>
function openPaymentModal(studentId, studentName = '', studentEmail = '') {
  Swal.fire({
    title: 'Send Payment Link',
    html: `
      <p>Student: <strong>${studentName}</strong></p>
      <input type="hidden" id="student_id" value="${studentId}">
      <input type="hidden" id="student_email" value="${studentEmail}">
      <input type="number" id="aircraft_hours" placeholder="Aircraft Hours" class="swal2-input">
      <input type="number" id="instructor_hours" placeholder="Instructor Hours" class="swal2-input">
      <input type="text" id="coupon_code" placeholder="Coupon Code (optional)" class="swal2-input">
    `,
    confirmButtonText: 'Generate Link',
    focusConfirm: false,
    preConfirm: () => {
      return {
        student_id: document.getElementById('student_id').value,
        email: document.getElementById('student_email').value,
        aircraft_hours: document.getElementById('aircraft_hours').value,
        instructor_hours: document.getElementById('instructor_hours').value,
        coupon_code: document.getElementById('coupon_code').value
      };
    }
  }).then((result) => {
    if (result.isConfirmed) {
      $.post('/wp-content/plugins/pistonpay/create_payment.php', result.value, function (res) {
        if (res.success) {
          Swal.fire({
            title: '✅ Payment Link Ready',
            html: `
              <a href="${res.url}" class="btn btn-primary w-100 mb-2" target="_blank">
                💳 Collect Payment Now
              </a>
              <button id="emailStudentLink" class="btn btn-outline-secondary w-100">
                📧 Email This Link to Student
              </button>
            `,
            icon: 'success',
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: 'Close',
            didOpen: () => {
              $('#emailStudentLink').click(() => {
                $.post('/wp-content/plugins/checkout/email_payment_link.php', {
                  student_id: result.value.student_id,
                  payment_url: res.url
                }, function (emailRes) {
                  if (emailRes.success) {
                    Swal.fire('📤 Email Sent', 'The student has received the payment link.', 'success');
                  } else {
                    Swal.fire('❌ Email Failed', emailRes.message, 'error');
                  }
                });
              });
            }
          });
        } else {
          Swal.fire('❌ Error', res.message, 'error');
        }
      });
    }
  });
}

</script>

<!-- ✅ Launch Outreach Mode Script -->
<script>
function launchOutreachMode() {
  $.getJSON('fetch_students_to_contact.php', function(students) {
    if (!Array.isArray(students) || students.length === 0) {
      Swal.fire('😎 No outreach needed right now!');
      return;
    }

    let index = 0;

    function showStudent(i) {
      const s = students[i];
      if (!s) {
        Swal.fire('✅ All caught up!', 'You’ve reached out to all students.', 'success');
        return;
      }

      const urgency = s.outreach_score >= 7 ? '🔥 High' :
                      s.outreach_score >= 4 ? '⚠️ Medium' : '💤 Low';

      Swal.fire({
        title: `${s.first_name} ${s.last_name}`,
        html: `
          <div style="font-size:16px; text-align:left; margin-top:10px;">
            <b>Urgency:</b> ${urgency}<br>
            <b>Phone:</b> <a href="tel:${s.phone}">${s.phone}</a><br>
            <b>Lesson:</b> ${s.current_lesson || 'N/A'}<br>
            <b>Last Flight:</b> ${s.last_flight_date || 'N/A'}<br>
            <b>Aircraft Hours:</b> ${s.aircraft_hours_remaining}<br>
            <b>Instructor Hours:</b> ${s.instructor_hours_remaining}
          </div>
          <div class="mt-3">
            <button id="sendPaymentLinkBtn" class="btn btn-outline-primary w-100">💳 Send Payment Link</button>
          </div>
        `,
        showConfirmButton: true,
        confirmButtonText: '📞 Call',
        showDenyButton: true,
        denyButtonText: '📋 Copy Text',
        showCancelButton: true,
        cancelButtonText: '⏭️ Skip',
        backdrop: true,
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
          $('#sendPaymentLinkBtn').click(() => {
            openPaymentModal(s.student_id, s.first_name, s.email || '');
          });
        },
        preConfirm: () => window.open(`tel:${s.phone}`, '_self'),
        preDeny: () => {
          const msg = `Hey ${s.first_name}! It’s your instructor at Piston. Ready to fly? Let’s get you back in the air! 🛫`;
          navigator.clipboard.writeText(msg).then(() => {
            Swal.fire('Copied!', 'Message copied to clipboard.', 'success');
          });
        },
        willClose: () => {
          index++;
          showStudent(index);
        }
      });
    }

    showStudent(index);
  });
}
</script>

<!-- ✅ Convert ALL buttons to use class-based handler -->
<script>
$(document).ready(function () {
  // Attach modal to ALL send-payment buttons
  $(document).on('click', '.sendPaymentLinkBtn', function () {
    const studentId = $(this).data('id');
    const studentName = $(this).data('name');
    const studentEmail = $(this).data('email');
    openPaymentModal(studentId, studentName, studentEmail);
  });

  // Attach SweetAlert lookup modal (from autocomplete button)
  $(document).on('click', '#sendPaymentLinkTop', function () {
    Swal.fire({
      title: 'Send Payment Link to Student',
      html: `
        <input type="text" id="student_lookup" class="swal2-input" placeholder="Search Student by Name">
        <input type="hidden" id="student_id">
        <input type="number" id="aircraft_hours" class="swal2-input" placeholder="Aircraft Hours">
        <input type="number" id="instructor_hours" class="swal2-input" placeholder="Instructor Hours">
        <input type="text" id="coupon_code" class="swal2-input" placeholder="Coupon Code (optional)">
      `,
      didOpen: () => {
        $('#student_lookup').autocomplete({
          source: function (request, response) {
            $.post('/wp-content/plugins/checkout/fetch_students.php', { query: request.term }, function (data) {
              response(data.map(student => ({
                label: student.name + ' (ID: ' + student.id + ')',
                value: student.name,
                id: student.id
              })));
            });
          },
          minLength: 2,
          select: function (event, ui) {
            $('#student_lookup').val(ui.item.value);
            $('#student_id').val(ui.item.id);
            return false;
          }
        });
      },
      preConfirm: () => {
        const id = document.getElementById('student_id').value;
        if (!id) {
          Swal.showValidationMessage('Please select a student from the list');
          return false;
        }
        return {
          student_id: id,
          aircraft_hours: document.getElementById('aircraft_hours').value,
          instructor_hours: document.getElementById('instructor_hours').value,
          coupon_code: document.getElementById('coupon_code').value
        };
      },
      confirmButtonText: 'Generate Link'
    }).then((result) => {
      if (result.isConfirmed) {
        $.post('/wp-content/plugins/checkout/create_payment.php', result.value, function (res) {
          if (res.success) {
            Swal.fire({
              title: '✅ Payment Link Ready',
              html: `
                <a href="${res.url}" class="btn btn-primary" target="_blank" style="margin-bottom: 15px;">
                  💳 Click Here to Pay
                </a><br>
                <button id="emailStudentLink" class="btn btn-outline-secondary">
                  📧 Email This Link to Student
                </button>
              `,
              icon: 'success',
              didOpen: () => {
                $('#emailStudentLink').click(() => {
                  $.post('/wp-content/plugins/checkout/email_payment_link.php', {
                    student_id: result.value.student_id,
                    payment_url: res.url
                  }, function (emailRes) {
                    if (emailRes.success) {
                      Swal.fire('📤 Email Sent', 'The student has received the payment link.', 'success');
                    } else {
                      Swal.fire('❌ Email Failed', emailRes.message, 'error');
                    }
                  });
                });
              }
            });
          } else {
            Swal.fire('❌ Error', res.message, 'error');
          }
        });
      }
    });
  });
});
</script>
<script>
  // Attach modal handler to every Send Payment Link button
  $(document).on('click', '.sendPaymentLinkBtn', function () {
    const studentId = $(this).data('id');
    const studentName = $(this).data('name');
    const studentEmail = $(this).data('email');
    openPaymentModal(studentId, studentName, studentEmail);
  });

</script>


</body>
</html>







