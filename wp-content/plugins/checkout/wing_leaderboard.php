<?php
require_once(__DIR__ . '/db_connect.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>CFI Wing Leaderboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron&display=swap" rel="stylesheet">
<style>
  body {
    background-color: #111;
    color: #fff;
    font-family: 'Orbitron', sans-serif;
    padding: 20px;
    overflow-x: hidden;
  }

  .leaderboard-title {
    text-align: center;
    font-size: 3rem;
    margin-bottom: 30px;
    text-transform: uppercase;
    letter-spacing: 2px;
  }

  .container {
    display: flex;
    flex-direction: row;
    overflow-x: auto;
    gap: 30px;
    padding-bottom: 20px;
    scroll-snap-type: x mandatory;
  }

  .wing-card {
    flex: 0 0 420px;
    scroll-snap-align: start;
    background-color: #222;
    border-radius: 15px;
    padding: 20px;
    min-height: 300px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 0 0 10px rgba(255, 255, 255, 0.05);
    transition: transform 0.3s ease;
  }

  .wing-card:hover {
    transform: scale(1.02);
    box-shadow: 0 0 20px rgba(255, 255, 255, 0.2);
  }

  .wing-card .header-glow {
    background: linear-gradient(to right, #2c2c2c, #3a3a3a, #2c2c2c);
    border-radius: 12px;
    padding: 15px;
    margin: -20px -20px 15px -20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 0 12px rgba(255, 255, 255, 0.1);
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  }

  .wing-card .wing-logo {
    max-width: 50px;
    height: auto;
    background-color: white;
    padding: 5px;
    border-radius: 8px;
  }

  .wing-card p {
  font-size: 1.1rem;
  font-weight: bold;
  margin-bottom: 12px;
  color: #dcdcdc;
}


  .flights-today {
    max-height: 160px;
    overflow-y: auto;
    padding-right: 4px;
  }

  .flight-entry {
    background-color: #333;
    padding: 10px;
    margin-bottom: 5px;
    border-radius: 8px;
    font-size: 0.85rem;
  }

  .solo {
    background-color: #6f42c1;
    color: #fff;
  }

  .in-flight {
    animation: pulse 1.5s infinite;
  }

  .scroll-btn {
  background-color: #444;
  color: #fff;
  font-size: 1.2rem;
  padding: 10px 20px;
  margin: 0 10px;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  transition: background 0.3s ease;
}
.scroll-btn:hover {
  background-color: #666;
}


  @keyframes pulse {
    0%   { box-shadow: 0 0 0 0 rgba(0,255,255, 0.4); }
    70%  { box-shadow: 0 0 0 20px rgba(0,255,255, 0); }
    100% { box-shadow: 0 0 0 0 rgba(0,255,255, 0); }
  }

  /* Scrollbar Styling (bonus) */
  body::-webkit-scrollbar,
  .container::-webkit-scrollbar {
    height: 8px;
  }

  .container::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.1);
    border-radius: 4px;
  }

  .wing-card p strong {
  color: #fff;
  font-weight: 900;
}

</style>


</head>
<body>
 <h1 class="leaderboard-title">CFI Wing Leaderboard</h1>

<div style="text-align: center; margin-bottom: 20px;">
  <button class="scroll-btn" id="scrollLeft">⏪</button>
  <button class="scroll-btn" id="toggleFullscreen">🖥️ Fullscreen</button>
  <button class="scroll-btn" id="scrollRight">⏩</button>
</div>

<div id="leaderboardContainer" class="container"></div>


  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
  function fetchLeaderboard() {
    $.getJSON('fetch_leaderboard_data.php', function(data) {
      console.log('Returned data:', data);  // Debug output

      if (!Array.isArray(data)) {
        $('#leaderboardContainer').html('<div class="alert alert-danger">⚠️ No data returned from server.</div>');
        return;
      }

      const container = $('#leaderboardContainer');
      container.empty();

      data.forEach(wing => {
        const wingCard = $(`
          <div class="wing-card">
            <div class="header-glow">
  <img src="${wing.logo_url}" class="wing-logo">
  <h2 class="mb-0">${wing.name}</h2>
</div>

            <p>🕒 <strong>Today:</strong> ${wing.daily_hours} hrs | 📆 <strong>This Week:</strong> ${wing.weekly_hours} hrs | 📅 <strong>This Month:</strong> ${wing.monthly_hours} hrs</p>
            <h5>Today’s Flights:</h5>
            <div class="flights-today"></div>
          </div>
        `);

        const flightsContainer = wingCard.find('.flights-today');

        wing.flights.forEach(flight => {
          const isSolo = flight.flight_type === 'Solo';
          const isFlying = flight.status === 'flying';

          const flightDiv = $(`
            <div class="flight-entry ${isSolo ? 'solo' : ''} ${isFlying ? 'in-flight' : ''}">
              ${flight.cfi_name} ➡️ ${flight.student_name} 
              ${isSolo ? '<span class="badge bg-light text-dark ms-2">SOLO</span>' : ''}
              ${isFlying ? '<span class="badge bg-info text-dark ms-2">IN FLIGHT</span>' : ''}
              ${flight.status === 'completed' ? '<span class="badge bg-success ms-2">COMPLETED</span>' : ''}
            </div>
          `);
          flightsContainer.append(flightDiv);
        });

        container.append(wingCard);
      });
    });
  }

  $(document).ready(function() {
    fetchLeaderboard();
    setInterval(fetchLeaderboard, 30000); // Refresh every 30 seconds
  });

// Auto-scroll every 10 seconds
let autoScrollInterval;
function startAutoScroll() {
  autoScrollInterval = setInterval(() => {
    document.getElementById('leaderboardContainer').scrollBy({ left: 380, behavior: 'smooth' });
  }, 10000);
}
function stopAutoScroll() {
  clearInterval(autoScrollInterval);
}
startAutoScroll();

// Manual scroll buttons
document.getElementById('scrollLeft').addEventListener('click', () => {
  stopAutoScroll();
  document.getElementById('leaderboardContainer').scrollBy({ left: -400, behavior: 'smooth' });
  startAutoScroll();
});
document.getElementById('scrollRight').addEventListener('click', () => {
  stopAutoScroll();
  document.getElementById('leaderboardContainer').scrollBy({ left: 400, behavior: 'smooth' });
  startAutoScroll();
});

// Fullscreen toggle
document.getElementById('toggleFullscreen').addEventListener('click', () => {
  const elem = document.documentElement;
  if (!document.fullscreenElement) {
    elem.requestFullscreen().catch(err => alert(`Error: ${err.message}`));
  } else {
    document.exitFullscreen();
  }
});


</script>

</body>
</html>
