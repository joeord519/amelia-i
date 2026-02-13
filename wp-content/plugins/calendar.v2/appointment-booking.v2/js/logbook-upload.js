function uploadLogbookMedia(logId) {
  Swal.fire({
    title: '📤 Upload Logbook Files',
    html: `<input type="file" id="logFiles" multiple accept="image/*,video/*,application/pdf" class="swal2-file">`,
    confirmButtonText: 'Upload',
    preConfirm: () => {
      const files = document.getElementById('logFiles').files;
      if (!files.length) return Swal.showValidationMessage('Please select at least one file');
      return Array.from(files);
    }
  }).then(async result => {
    if (!result.isConfirmed) return;
    const files = result.value;

    for (const file of files) {
      const res = await fetch('/wp-content/plugins/calendar.v2/api/generate_presigned_url.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ filename: file.name, filetype: file.type })
      });
      const data = await res.json();

      if (!data.url) {
        Swal.fire("Error", data.error || "Upload failed", "error");
        return;
      }

      await fetch(data.url, {
        method: 'PUT',
        headers: { 'Content-Type': file.type },
        body: file
      });

      await fetch('/wp-content/plugins/calendar.v2/api/save_uploaded_logbook.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          flight_log_id: logId,
          file_url: data.final_url,
          file_type: file.type
        })
      });
    }

    Swal.fire("✅ Done", "Files uploaded successfully.", "success");
  });
}
