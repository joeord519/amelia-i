<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// ⏰ Auto-logout after 25 minutes of inactivity
$timeout = 1500; // 25 minutes
if (isset($_SESSION['LAST_ACTIVITY']) && time() - $_SESSION['LAST_ACTIVITY'] > $timeout) {
  session_unset();
  session_destroy();
  header('Location: admin-login.php?timeout=1');
  exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

require_once __DIR__ . '/db_connect.php';
$conn = getDB();

// 🔐 Require valid login
if (empty($_SESSION['admin_logged_in'])) {
  header('Location: admin-login.php');
  exit;
}

// ✅ Pull session vars
$adminEmail = $_SESSION['admin_email'] ?? '';
$adminPhone = $_SESSION['admin_phone'] ?? '';
$adminId = $_SESSION['admin_id'] ?? null;

$adminName = '';
$adminTitle = '';
$customSig = '';

// 🧠 Optionally pull admin data from DB for display
if ($adminId) {
  $stmt = $conn->prepare("SELECT * FROM wp_admin_users WHERE id = ? LIMIT 1");
  $stmt->execute([$adminId]);
  if ($admin = $stmt->fetch()) {
    $adminName = $admin['name'] ?? '';
    $adminTitle = $admin['title'] ?? '';
    $customSig = $admin['signature_html'] ?? '';
  }
}

if ($adminEmail) {
    $stmt = $conn->prepare("SELECT name, phone, title, signature_html FROM wp_admin_users WHERE email = ?");
    $stmt->execute([$adminEmail]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin) {
        $adminName = htmlspecialchars($admin['name']);
        $adminPhone = htmlspecialchars($admin['phone']);
        $adminTitle = htmlspecialchars($admin['title']);
        $customSig = $admin['signature_html'];
    }
}

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as low_count FROM wp_students WHERE aircraft_hours_remaining < 5 OR instructor_hours_remaining < 5");
    $stmt->execute();
    $lowStudents = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $lowStudents['low_count'] = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <meta charset="UTF-8">
  <title>Piston Aviation | Admin Panel</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/wp-content/plugins/checkout/tinymce/tinymce.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- jQuery + jQuery UI (make sure these are present and in this order) -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
  <!-- jQuery UI CSS -->
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">

  <style>
    .tox-dialog { z-index: 100001 !important; }
    .tox { z-index: 100000 !important; }
    .swal2-container.zindex-fix { z-index: 99999 !important; }
  </style>

<style>
.autocomplete-results {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: white;
  border: 1px solid #ccc;
  border-radius: 8px;
  max-height: 200px;
  overflow-y: auto;
  list-style: none;
  margin: 0;
  padding: 0;
  z-index: 10000;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.autocomplete-results li {
  padding: 10px;
  cursor: pointer;
  border-bottom: 1px solid #eee;
}

.autocomplete-results li:last-child {
  border-bottom: none;
}

.autocomplete-results li:hover {
  background-color: #f3f4f6;
}

.ui-autocomplete {
  z-index: 999999 !important;
}
</style>

</head>
<body class="bg-light">
  <nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
      <span class="navbar-brand mb-0 h1">🚀 Piston Aviation Admin Panel</span>
    </div>
  </nav>

  <div class="container mt-4">
    <div class="row">
      
      <!-- Sidebar Include -->
<?php include($_SERVER['DOCUMENT_ROOT'] . '/wp-content/plugins/common-ui/admin-sidebar.php'); ?>

      <!-- Main Column -->
      <div class="col-md-9">

        <!-- Top Button Row -->
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <button class="btn btn-primary" onclick="toggleComposer()">✍️ Compose</button>
            <a href="email-log.php" class="btn btn-outline-dark">📬 View Email Log</a>
            <button class="btn btn-outline-success" onclick="document.getElementById('flightLogOverlay').style.display = 'flex'; return false;">✈️ Flight Log Entry</button>
            <button id="sendPaymentLinkTop" class="btn btn-warning">💳 Send Payment Link</button>
           </div>
        </div>

        <!-- Welcome Card -->
        <div class="card shadow-sm mb-4">
          <div class="card-body">
            <h3>✨ Welcome, Admin!</h3>
            <p>Select an option from the menu to get started.</p>
            <hr>
            <p class="text-muted">This panel will evolve into your central management hub for all operations, analytics, and administration of Piston Aviation.</p>
          </div>
        </div>

        <!-- Composer -->
        <div id="composerCard" class="card mb-4" style="display: none;">
          <div class="card-body">
            <h5 class="card-title">📧 Compose Email</h5>
            <form id="emailForm">
              <div class="mb-3">
                <label for="emailSubject" class="form-label">Subject</label>
                <input type="text" class="form-control" id="emailSubject" required>
              </div>
              <div class="mb-3">
                <label for="emailBody" class="form-label">Body</label>
                <textarea id="emailBody" class="form-control"></textarea>
                <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="insertSignature()">✍️ Insert Signature</button>
              </div>
              <div class="mb-3">
                <label for="sendMode" class="form-label">Send Mode</label>
                <select class="form-select" id="sendMode">
                  <option value="test">🧪 Send to Me Only (Test)</option>
                  <option value="live">🚀 Send to All Active Students</option>
                </select>
              </div>
              <button type="submit" class="btn btn-success">Send Emails</button>
            </form>
          </div>
        </div>

        <!-- Low Hours Card -->
        <div class="row g-3">
          <div class="col-md-6">
            <div class="card text-center shadow-sm">
              <div class="card-body">
                <h5>⚠️ Students Low on Hours</h5>
                <h1 class="display-4"><?php echo $lowStudents['low_count']; ?></h1>
                <a href="admin-student-management.php" class="btn btn-warning mt-2">View Students</a>
              </div>
            </div>
          </div>
        </div>

      </div> <!-- end col-md-9 -->
      
    </div> <!-- end row -->
  </div> <!-- end container -->


<script>
const ADMIN_NAME = <?php echo json_encode($adminName); ?>;
const ADMIN_PHONE = <?php echo json_encode($adminPhone); ?>;
const ADMIN_TITLE = <?php echo json_encode($adminTitle); ?>;
const CUSTOM_SIGNATURE = <?php echo json_encode($customSig); ?>;

tinymce.init({
  selector: '#emailBody',
  height: 300,
  menubar: false,
  plugins: 'lists link image',
  toolbar: 'undo redo | formatselect | bold italic underline | fontsizeselect | forecolor backcolor | alignleft aligncenter alignright | bullist numlist outdent indent | link',
  branding: false,
  skin: 'oxide',
  content_css: 'default',
  license_key: 'gpl',
  
  // 👇 These fix the relative URL rewrite problem:
  relative_urls: false,
  remove_script_host: false,
  convert_urls: true
});


function toggleComposer() {
  const card = document.getElementById('composerCard');
  card.style.display = card.style.display === 'none' ? 'block' : 'none';
}

function insertSignature() {
  let signature = CUSTOM_SIGNATURE;
  if (!signature) {
    signature = `<br><br>—<br><strong>${ADMIN_NAME}</strong><br>${ADMIN_TITLE ? `<em>${ADMIN_TITLE}</em><br>` : ''}${ADMIN_PHONE}<br><a href="https://flypiston.com">flypiston.com</a>`;
  }
  tinymce.activeEditor.execCommand('mceInsertContent', false, signature);
}

document.getElementById('emailForm').addEventListener('submit', async function (e) {
  e.preventDefault();
  tinymce.triggerSave();

  const subject = document.getElementById('emailSubject').value.trim();
  const body = document.getElementById('emailBody').value;
  const sendMode = document.getElementById('sendMode').value;

  if (!subject || !body) {
    Swal.fire('Missing Fields', 'Subject and body are required.', 'warning');
    return;
  }

  if (sendMode === 'live') {
    const confirmSend = await Swal.fire({
      icon: 'warning',
      title: 'Are you sure?',
      text: 'This will send your email to ALL active students.',
      showCancelButton: true,
      confirmButtonText: '✅ Yes, Send It',
      cancelButtonText: 'Cancel',
      allowOutsideClick: false,
      allowEscapeKey: false
    });
    if (!confirmSend.isConfirmed) return;
  }

  Swal.fire({
    icon: 'info',
    title: 'Sending Emails...',
    text: 'Please wait while we process your request.',
    allowOutsideClick: false,
    showConfirmButton: false,
    didOpen: () => { Swal.showLoading(); }
  });

  try {
    const res = await fetch('send_emails.php', {
      method: 'POST',
      body: new URLSearchParams({ subject, body, sendMode })
    });
    const msg = await res.text();
    Swal.fire({
      icon: res.ok ? 'success' : 'error',
      title: res.ok ? '✅ Emails Sent' : '❌ Failed to Send',
      html: msg
    });
  } catch (err) {
    Swal.fire('Sending Failed', 'Could not reach the server. Check logs or console.', 'error');
  }
});
</script>

<div style="text-align: center; margin: 40px 0;">
  <a href="/wp-content/plugins/calendar.v4/index.php" class="admin-calendar-btn">🗓️ View Admin Calendar</a>
</div>
<div style="margin: 2rem 0; text-align: center;">
  <a href="/wp-content/plugins/checkout/leaderboard.html" target="_blank"
     style="display: inline-block; background-color: #00BFFF; color: white; font-size: 1.2rem; font-weight: bold; padding: 14px 28px; border-radius: 12px; text-decoration: none; box-shadow: 0 4px 12px rgba(0,0,0,0.3); transition: 0.3s;">
    📊 View Wing Leaderboard
  </a>
</div>

<style>
.admin-calendar-btn {
  display: inline-block;
  padding: 18px 36px;
  background: #1e40af;
  color: white;
  font-size: 1.5rem;
  font-weight: bold;
  border-radius: 12px;
  margin-top: 20px;
  text-decoration: none;
  box-shadow: 0 6px 18px rgba(0,0,0,0.25);
  transition: background 0.2s ease, transform 0.2s ease;
}
.admin-calendar-btn:hover {
  background: #3b82f6;
  transform: translateY(-2px);
}
</style>


<!-- ✈️ Flight Log Entry Modal Overlay -->
<style>
  .flight-log-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
  }

  .flight-log-modal {
    background: #fff;
    padding: 30px;
    border-radius: 12px;
    max-width: 700px;
    width: 90%;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.3);
    position: relative;
  }

  .flight-log-modal input,
  .flight-log-modal select,
  .flight-log-modal textarea {
    width: 100%;
    margin-bottom: 16px;
    padding: 10px;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 1rem;
  }

  .autocomplete-results {
    position: absolute;
    background: white;
    border: 1px solid #ccc;
    border-radius: 8px;
    max-height: 200px;
    overflow-y: auto;
    z-index: 10000;
    width: 100%;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
  }

  .autocomplete-results li {
    padding: 10px;
    cursor: pointer;
  }

  .autocomplete-results li:hover {
    background-color: #f0f0f0;
  }
</style>

<div id="flightLogOverlay" class="flight-log-overlay">
  <div class="flight-log-modal">
    <button class="close-btn" onclick="document.getElementById('flightLogOverlay').style.display = 'none';">×</button>
    <h2 style="text-align:center;">Manual Flight Log Entry</h2>

    <form method="POST" action="submit_flight_log.php">
      <div class="mb-3">
        <label for="flightType">Flight Type</label>
        <select name="flightType" class="form-select" required>
          <option value="">-- Select Type --</option>
          <option value="Dual Training">Dual Training</option>
          <option value="Solo">Solo</option>
          <option value="Rental">Rental</option>
          <option value="Discovery Flight">Discovery Flight</option>
          <option value="Company">Company</option>
          <option value="Ground Lesson">Ground Lesson</option>
          <option value="SOF Time">SOF Time</option>
        </select>
      </div>

      <div class="mb-3 field-student d-none position-relative">
        <label for="studentName">Student Name</label>
        <input type="text" id="studentName" class="form-control" placeholder="Type to search..." autocomplete="off">
        <input type="hidden" name="studentId" id="studentId">
        <ul id="studentResults" class="autocomplete-results"></ul>
      </div>

      <div class="mb-3 field-cfi d-none position-relative">
        <label for="cfiName">CFI Name</label>
        <input type="text" id="cfiName" class="form-control" placeholder="Type to search..." autocomplete="off">
        <input type="hidden" name="cfiId" id="cfiId">
        <ul id="cfiResults" class="autocomplete-results"></ul>
      </div>

      <div class="mb-3 field-aircraft d-none position-relative">
        <label for="tailNumber">Aircraft Tail Number</label>
        <input type="text" name="tail_number" id="tailNumber" class="form-control" placeholder="e.g., N123AB" autocomplete="off">
        <input type="hidden" name="aircraftId" id="aircraftId">
        <input type="hidden" name="flight_time_factor" id="flight_time_factor">
        <ul id="tailResults" class="autocomplete-results"></ul>
      </div>

      <div class="mb-3">
        <label for="date">Date</label>
        <input type="date" class="form-control" name="date" required>
      </div>

      <div class="mb-3 field-duration d-none">
        <label for="flightHours">Flight Duration (hours)</label>
        <input type="number" class="form-control" name="flightHours" step="0.1">
      </div>

      <div class="mb-3 field-ground">
  <label for="groundHours">Ground Time (hours)</label>
  <input type="number" class="form-control" name="groundHours" step="0.1">
</div>

      <div class="mb-3">
        <label for="notes">Notes</label>
        <textarea name="notes" class="form-control" rows="4" placeholder="Optional notes..."></textarea>
      </div>

      <div class="d-grid">
        <button type="submit" class="btn btn-success">✅ Log Flight</button>
      </div>
    </form>
  </div>
</div>

<script>
$(document).ready(function () {
  function setupAutocomplete(inputId, resultsId, endpoint, callback) {
    $(`#${inputId}`).on('input', function () {
      const query = $(this).val().trim();
      if (query.length < 2) {
        $(`#${resultsId}`).empty();
        return;
      }

      $.post(endpoint, { query }, function (data) {
        $(`#${resultsId}`).empty();
        const results = Array.isArray(data) ? data : JSON.parse(data);
        if (!results.length) return;

        results.forEach(item => {
          const li = $('<li></li>').text(item.name || item.tail_number).on('click', function () {
            $(`#${inputId}`).val(item.name || item.tail_number);
            callback(item);
            $(`#${resultsId}`).empty();
          });
          $(`#${resultsId}`).append(li);
        });
      }).fail(function (xhr) {
        console.error(`${endpoint} fetch failed:`, xhr.responseText);
      });
    });

    $(document).on('click', function (e) {
      if (!$(e.target).closest(`#${inputId}`).length) {
        $(`#${resultsId}`).empty();
      }
    });
  }

  setupAutocomplete('studentName', 'studentResults', 'fetch_students.php', function (student) {
    $('#studentId').val(student.id || student.student_id);
  });

  setupAutocomplete('cfiName', 'cfiResults', 'fetch-cfis-flight-entry.php', function (cfi) {
    $('#cfiId').val(cfi.id);
  });

  setupAutocomplete('tailNumber', 'tailResults', 'fetch-aircraft-flight-entry.php', function (ac) {
    $('#aircraftId').val(ac.id);
    $('#flight_time_factor').val(ac.flight_time_factor || 1);
  });

  function updateFieldVisibility() {
  const type = $('select[name="flightType"]').val();
  $('.field-student, .field-cfi, .field-aircraft, .field-duration, .field-ground, .field-time').addClass('d-none');

  if (type === 'Dual Training') {
    $('.field-student, .field-cfi, .field-aircraft, .field-duration, .field-ground').removeClass('d-none');
  } else if (type === 'Solo' || type === 'Rental') {
    $('.field-student, .field-aircraft, .field-duration').removeClass('d-none');
    // field-ground remains hidden
  } else if (type === 'Ground Lesson') {
    $('.field-student, .field-cfi, .field-ground').removeClass('d-none');
  } else if (type === 'Discovery Flight') {
    $('.field-cfi, .field-aircraft, .field-duration').removeClass('d-none');
  } else if (type === 'SOF Time') {
    $('.field-cfi, .field-ground').removeClass('d-none');
  }
  else if (type === 'Company') {
  $('.field-cfi, .field-aircraft, .field-duration').removeClass('d-none');
  }

}


  $('select[name="flightType"]').on('change', updateFieldVisibility);
  updateFieldVisibility();
});
</script>

<?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
<script>
Swal.fire({
  icon: 'success',
  title: '✅ Flight Logged!',
  text: 'The flight log entry was saved successfully.',
  confirmButtonText: 'OK'
}).then(() => {
  const url = new URL(window.location.href);
  url.searchParams.delete('success');
  url.searchParams.delete('cb'); // 🔧 also remove cache buster
  window.history.replaceState({}, document.title, url.toString());
});
</script>

<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- jQuery UI + SweetAlert2 assets (make sure these are already in your page once) -->
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
        <input type="hidden" id="student_email">
      `,
      didOpen: () => {
        $('#student_lookup').autocomplete({
          source: function (request, response) {
            $.post('/wp-content/plugins/checkout/fetch_students.php', { query: request.term }, function (data) {
              response(data.map(student => ({
                label: student.name + ' (ID: ' + student.id + ')',
                value: student.name,
                id: student.id,
                email: student.email
              })));
            });
          },
          minLength: 2,
          select: function (event, ui) {
            $('#student_lookup').val(ui.item.value);
            $('#student_id').val(ui.item.id);
            $('#student_email').val(ui.item.email); 
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
          email: document.getElementById('student_email').value,
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

</body>

</html>