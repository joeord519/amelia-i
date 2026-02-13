function loadFlightHistory() {
  $('#dashboard-content').load('flight_history.php');
}

function loadEntryForm() {
  Swal.fire({
    title: 'Submit Log Entry',
    customClass: { popup: 'swal-wide' },
    html: `
      <label>Entry Type:</label><br>
      <select id="entryType" class="swal2-input">
        <option value="">Select...</option>
        <option value="SOF Time">SOF Time</option>
        <option value="Ground Lesson">Ground Lesson</option>
        <option value="Discovery">Discovery</option>
      </select>

      <div id="studentWrap" style="margin-top:10px; display:none;">
        <label>Students:</label><br>
        <select id="studentMulti" multiple="multiple" style="width:100%;"></select>
      </div>

      <div id="passengerWrap" style="margin-top:10px; display:none;">
        <label>Passenger Name:</label>
        <input type="text" id="passengerName" class="swal2-input" placeholder="Full Name">
      </div>

      <div id="tailWrap" style="margin-top:10px; display:none;">
        <label>Tail Number:</label>
        <input type="text" id="tailNumber" class="swal2-input" placeholder="N#####">
      </div>

      <div id="flightWrap" style="margin-top:10px; display:none;">
        <label>Flight Time (hrs):</label>
        <input type="number" id="flightTime" class="swal2-input" step="0.1">
      </div>

      <div id="groundWrap" style="margin-top:10px; display:none;">
        <label>Ground Time (hrs):</label>
        <input type="number" id="groundTime" class="swal2-input" step="0.1">
      </div>

      <div id="notesWrap" style="margin-top:10px;">
        <label>Notes:</label>
        <textarea id="entryNotes" class="swal2-textarea" placeholder="Optional notes..."></textarea>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Submit Entry',
    focusConfirm: false,

    didOpen: () => {
      $('#entryType').on('change', function () {
        const type = $(this).val();
        $('#studentWrap, #tailWrap, #flightWrap, #groundWrap, #passengerWrap').hide();

        if (type === 'SOF Time') {
          $('#groundWrap').show();
        } else if (type === 'Ground Lesson') {
          $('#studentWrap, #groundWrap').show();

          if ($.fn.select2 && $('#studentMulti').hasClass('select2-hidden-accessible')) {
            $('#studentMulti').select2('destroy');
          }

          $('#studentMulti').empty().select2({
            width: '100%',
            placeholder: "Search and select students...",
            maximumSelectionLength: 4,
            allowClear: true,
            language: {
              maximumSelected: function () {
                return '🚫 You can only select up to 4 students.';
              }
            },
            ajax: {
              url: 'search_students.php',
              dataType: 'json',
              delay: 250,
              data: function (params) {
                return { term: params.term };
              },
              processResults: function (data) {
                return {
                  results: data.map(student => ({
                    id: student.id,
                    text: student.text
                  }))
                };
              },
              cache: true
            }
          });

        } else if (type === 'Discovery') {
          $('#passengerWrap, #tailWrap, #flightWrap, #notesWrap').show();
        } else {
          $('#studentWrap, #tailWrap, #flightWrap, #groundWrap').show();
        }
      });
    },

    preConfirm: () => {
      const type = $('#entryType').val();
      const entry = {
        entry_type: type,
        passenger_name: $('#passengerName').val(),
        tail_number: $('#tailNumber').val(),
        total_flight_time: $('#flightTime').val(),
        ground_time: $('#groundTime').val(),
        notes: $('#entryNotes').val()
      };

      if (type === 'Ground Lesson') {
        entry.student_ids = $('#studentMulti').val();
        if (!entry.student_ids || entry.student_ids.length === 0) {
          Swal.showValidationMessage('Please select at least one student.');
          return false;
        }
      }

      if (!entry.entry_type) {
        Swal.showValidationMessage('Entry Type is required');
        return false;
      }

      return fetch('submit_entry.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(entry)
      })
        .then(res => res.json())
        .then(data => {
          if (!data || data.status !== 'success') {
            throw new Error(data.message || 'Submission failed');
          }
          return data;
        })
        .catch(err => {
          Swal.showValidationMessage(err.message);
        });
    }
  }).then(result => {
    if (result.isConfirmed) {
      Swal.fire('✅ Entry Saved!', '', 'success');
    }
  });
}

function loadPaySummary(page = 1) {
  $('#dashboard-content').load('pay_summary.php?page=' + page);
}

function logout() {
  $.post('logout.php', function () {
    location.reload();
  });
}



