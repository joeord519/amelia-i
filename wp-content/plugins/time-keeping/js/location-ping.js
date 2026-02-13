// Ping location every 5 minutes while tab is open
setInterval(() => {
  if (!navigator.geolocation) return;

  navigator.geolocation.getCurrentPosition(
    (position) => {
      const lat = position.coords.latitude;
      const lng = position.coords.longitude;
      const employee_id = localStorage.getItem("employee_id");

      if (!employee_id) return;

      fetch("log_location.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          employee_id: employee_id,
          lat: lat,
          lng: lng
        })
      });
    },
    (error) => {
      console.warn("GPS ping failed:", error);
    }
  );
}, 5 * 60 * 1000); // Every 5 minutes
