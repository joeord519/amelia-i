<?php
// /calendar.v4/index.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Piston Calendar v4</title>

  <!-- FullCalendar CSS -->
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.8/index.global.min.css" rel="stylesheet" />

  <!-- SweetAlert2 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet" />

  <!-- Custom Styles -->
  <link rel="stylesheet" href="assets/calendar.css" />

  <!-- Modal Styling for Suggestion Box -->
  <style>
    .piston-popup {
      border-radius: 20px !important;
      font-family: 'Segoe UI', sans-serif;
      font-size: 16px !important;
      font-weight: 500;
      padding: 25px !important;
    }

    .piston-field {
      display: flex;
      flex-direction: column;
      gap: 5px;
      margin-bottom: 18px;
    }

    .piston-field label {
      font-weight: 600;
      font-size: 15px;
    }

    .piston-field input,
    .piston-field textarea,
    .piston-field select {
      border: 2px solid #ff6a00;
      border-radius: 12px;
      padding: 10px;
      font-size: 15px;
      font-weight: 500;
      box-shadow: 0 2px 6px rgba(255, 106, 0, 0.2);
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .piston-field input:focus,
    .piston-field textarea:focus,
    .piston-field select:focus {
      border-color: #ff842e;
      box-shadow: 0 0 8px rgba(255, 106, 0, 0.5);
    }

    .piston-confirm {
      background-color: #ff6a00 !important;
      color: #fff !important;
      font-weight: bold !important;
      border-radius: 10px !important;
      padding: 10px 20px !important;
      font-size: 16px !important;
      text-transform: uppercase;
    }

    .piston-cancel {
      background-color: #e0e0e0 !important;
      color: #333 !important;
      font-weight: bold !important;
      border-radius: 10px !important;
      padding: 10px 20px !important;
      font-size: 16px !important;
    }
  </style>
</head>

<body>
  <div id="user-info">
    Logged in as: <span id="userNameDisplay"></span> (<span id="userRoleDisplay"></span>) <a href="#" id="logoutLink">Logout</a>
  </div>

  <div id="calendar-controls">
    <button id="companyEventBtn">📅 Create Company Event</button>
  </div>

  <div id="calendarContainer">
    <div id="calendar"></div>
  </div>

  <!-- 🔘 Floating Suggestion Button -->
  <div onclick="launchSuggestionBox()" style="
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: #ff6a00;
    color: white;
    padding: 14px 20px;
    border-radius: 30px;
    font-weight: bold;
    font-size: 16px;
    cursor: pointer;
    z-index: 9999;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  ">
    💬 Suggest Something
  </div>

  <!-- FullCalendar & Plugins -->
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.8/index.global.min.js"></script>

  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- Modular JS Files -->
  <script src="assets/js/shared.utils.js"></script>
  <script src="assets/js/modal.flight.js"></script>
  <script src="assets/js/modal.ground.js"></script>
  <script src="assets/js/modal.company.js"></script>
  <script src="assets/js/modal.discovery.js"></script>
  <script src="assets/js/events.flight.js"></script>
  <script src="assets/js/events.ground.js"></script>
  <script src="assets/js/calendar.init.js"></script>

  <!-- Suggestion Modal Script -->
  <script>
    function launchSuggestionBox() {
      Swal.fire({
        title: '🛠️ Got Ideas?',
        background: '#fff',
        customClass: {
          popup: 'piston-popup',
          confirmButton: 'piston-confirm',
          cancelButton: 'piston-cancel'
        },
        html: `
          <div class="piston-field">
            <label>Category</label>
            <select id="category">
              <option value="Calendar">Calendar</option>
              <option value="Scheduler">Scheduler</option>
            </select>
          </div>

          <div class="piston-field">
            <label>Your Suggestion</label>
            <textarea id="suggestion" placeholder="Tell us what’s on your mind..."></textarea>
          </div>

          <div class="piston-field">
            <label>Your Name (optional)</label>
            <input id="name" type="text" placeholder="Name">
          </div>

          <div class="piston-field">
            <label>Your Email (optional)</label>
            <input id="email" type="email" placeholder="Email">
          </div>

          <div class="piston-field">
            <label>Your Phone (optional)</label>
            <input id="phone" type="text" placeholder="Phone">
          </div>
        `,
        confirmButtonText: '✈️ Send It',
        cancelButtonText: 'Cancel',
        showCancelButton: true,
        preConfirm: () => {
          const category = document.getElementById('category').value;
          const suggestion = document.getElementById('suggestion').value;
          const name = document.getElementById('name').value;
          const email = document.getElementById('email').value;
          const phone = document.getElementById('phone').value;

          if (!suggestion.trim()) {
            Swal.showValidationMessage('Suggestion is required!');
            return false;
          }

          return fetch('/wp-content/plugins/suggestions/submit_suggestion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ category, suggestion, name, email, phone })
          })
          .then(res => res.json())
          .catch(() => Swal.showValidationMessage('Request failed. Try again.'));
        }
      }).then((result) => {
        if (result.isConfirmed) {
          Swal.fire({
            title: '🎉 Thanks, captain',
            text: 'We logged your feedback — now go be legendary.',
            icon: 'success',
            confirmButtonText: '🚀 Done',
            customClass: {
              popup: 'piston-popup',
              confirmButton: 'piston-confirm'
            }
          });
        }
      });
    }
  </script>
</body>
</html>
</boddy></html>