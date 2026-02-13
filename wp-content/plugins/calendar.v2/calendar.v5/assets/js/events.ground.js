function createGroundLesson(data) {
  Swal.fire({ title: "Creating Ground Lesson...", didOpen: () => Swal.showLoading() });

  fetch("create_ground_lesson.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  })
    .then(res => res.json())
    .then(resp => {
      if (resp.success && resp.event) {
        const { event } = resp;

        // Style for ground lesson
        event.backgroundColor = "#22c55e";  // Tailwind green-500
        event.borderColor = "#22c55e";
        event.textColor = "#ffffff";

        calendarInstance.addEvent(event);

        Swal.fire("✅ Ground Lesson Created", "The lesson has been booked successfully.", "success");
      } else {
        Swal.fire("Error", resp.message || "Ground lesson creation failed.", "error");
      }
    })
    .catch(err => {
  console.error("Ground lesson creation failed:", err);
  err.text?.().then(t => console.log("📩 Response body:", t));
  Swal.fire("Error", "Could not create ground lesson.", "error");
});

}

