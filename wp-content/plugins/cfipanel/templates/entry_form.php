<?php
// /wp-content/plugins/cfipanel/templates/entry_form.php
?>

<h3>Submit Entry (SOF, Discovery, or Ground Lesson)</h3>
<form id="entryForm">
  <label>Flight Category:</label><br>
  <select name="flight_category" required>
    <option value="">Select...</option>
    <option value="SOF Time">SOF Time</option>
    <option value="Ground Lesson">Ground Lesson</option>
    <option value="Discovery">Discovery</option>
  </select><br><br>

  <label>Student ID (optional):</label><br>
  <input type="number" name="student_id" placeholder="Enter student_id if known"><br><br>

  <label>Tail Number (optional):</label><br>
  <input type="text" name="tail_number" placeholder="Ex: N2723B"><br><br>

  <label>Total Flight Time (hrs):</label><br>
  <input type="number" name="total_flight_time" step="0.1"><br><br>

  <label>Ground Time (hrs):</label><br>
  <input type="number" name="ground_time" step="0.1"><br><br>

  <label>Notes:</label><br>
  <textarea name="notes" rows="3" placeholder="Optional notes"></textarea><br><br>

  <button type="submit">✅ Submit Entry</button>
</form>

<script>
$('#entryForm').on('submit', function(e) {
  e.preventDefault();
  $.ajax({
    url: 'submit_entry.php',
    method: 'POST',
    data: $(this).serialize(),
    success: function(response) {
      if (response.status === 'success') {
        Swal.fire('Entry Saved ✅', response.message, 'success');
        $('#entryForm')[0].reset();
      } else {
        Swal.fire('Error ❌', response.message, 'error');
      }
    },
    error: function() {
      Swal.fire('Server Error', 'Something went wrong.', 'error');
    }
  });
});
</script>

