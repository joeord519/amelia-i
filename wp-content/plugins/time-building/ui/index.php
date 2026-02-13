<?php
// /wp-content/plugins/time-building/ui/index.php
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Piston Aviation – Time Building</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body { background:#f7f7fb; }
    .week-card { transition: transform .08s ease-in-out; cursor: pointer; border: 2px solid transparent; }
    .week-card:hover { transform: translateY(-2px); }
    .week-card.selected { border-color: #198754; background-color: #e9f7ef; }
    .week-card.disabled { opacity: 0.6; pointer-events: none; }
    .badge-open { background:#198754; }
    .badge-low  { background:#fd7e14; }
    .badge-sold { background:#6c757d; }
    .price-box { font-weight:700; font-size:1.25rem; }
    .sticky-cta { position: sticky; bottom: 0; z-index: 2; background: #fff; border-top: 1px solid #eee; padding: .75rem; }
    .select-indicator { font-weight: 600; color: #198754; text-align: right; user-select: none; }
    .card-footer-mini { font-size:.9rem; color:#6c757d; }
  </style>
</head>
<body>
  <div class="container py-4">
    <div class="mb-4 text-center">
      <h1 class="mb-1">Time Building Weeks</h1>
      <p class="text-muted mb-0">Pick up to <b>4</b> weeks • Sunday–Saturday • $4,950 per week</p>
    </div>

    <div id="weeksRow" class="row g-3"></div>

    <div class="sticky-cta d-flex align-items-center justify-content-between">
      <div>
        <span class="me-3">Selected: <b id="selCount">0</b> / 4</span>
        <span class="price-box">Total: $<span id="totalPrice">0</span></span>
      </div>
      <div class="d-flex gap-2">
        <button id="btnHold" class="btn btn-dark px-4" disabled>Hold Selected Weeks</button>
      </div>
    </div>
        <!-- Return to Info Page Button -->
    <div class="text-center mt-4">
      <a href="https://flypiston.com/time-build/" class="btn btn-outline-primary btn-lg">
        ⬅ Return to Time Building Information Page
      </a>
    </div>

  </div>

  <script>
    const API_BASE = "../"; // points to /wp-content/plugins/time-building/
    const PRICE_PER_WEEK = 4950;
    const leadId = 123; // TODO: wire actual lead/student id

    const weeksRow = document.getElementById('weeksRow');
    const btnHold  = document.getElementById('btnHold');
    const selCount = document.getElementById('selCount');
    const totalPrice = document.getElementById('totalPrice');

    let weeks = [];
    let selected = new Set();

    function formatRange(ws, we) {
      const s = new Date(ws + "T12:00:00"); // avoid TZ weirdness
      const e = new Date(we + "T12:00:00");
      const opts = { month:'short', day:'numeric' };
      return `${s.toLocaleDateString(undefined, opts)} – ${e.toLocaleDateString(undefined, opts)}`;
    }

    function badgeFor(spots) {
      if (spots <= 0) return '<span class="badge badge-sold">Sold Out</span>';
      if (spots <= 2) return `<span class="badge badge-low">Only ${spots} left</span>`;
      return `<span class="badge badge-open">${spots} spots</span>`;
    }

    function render() {
      weeksRow.innerHTML = weeks.map(w => {
        const disabled = Number(w.spots_left) <= 0;
        const checked  = selected.has(w.week_id);
        return `
          <div class="col-12 col-md-6 col-lg-4">
            <div class="card week-card h-100 shadow-sm ${checked ? 'selected' : ''} ${disabled ? 'disabled' : ''}" 
                 data-week="${w.week_id}" ${disabled ? 'data-disabled="1"' : ''} role="button" aria-pressed="${checked}">
              <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <h5 class="card-title mb-0">${formatRange(w.week_start, w.week_end)}</h5>
                  ${badgeFor(Number(w.spots_left))}
                </div>
                <div class="text-muted small mb-3">Week ${w.week_id}</div>

                <div class="mt-auto d-flex justify-content-between align-items-center">
                  <div class="select-indicator ${checked ? 'selected' : ''}" data-week="${w.week_id}">
                    ${checked ? 'Selected' : 'Select'}
                  </div>
                  <div class="fw-bold">$4,950</div>
                </div>
              </div>
            </div>
          </div>
        `;
      }).join('');

      document.querySelectorAll('.week-card').forEach(card => {
        card.addEventListener('click', () => {
          const id = Number(card.dataset.week);
          if (card.dataset.disabled) return;

          if (selected.has(id)) {
            selected.delete(id);
            card.classList.remove('selected');
            card.setAttribute('aria-pressed', 'false');
            const ind = card.querySelector('.select-indicator');
            if (ind) ind.textContent = 'Select';
          } else {
            if (selected.size >= 4) {
              Swal.fire('Max 4 weeks', 'You can choose up to four weeks per order.', 'info');
              return;
            }
            selected.add(id);
            card.classList.add('selected');
            card.setAttribute('aria-pressed', 'true');
            const ind = card.querySelector('.select-indicator');
            if (ind) ind.textContent = 'Selected';
          }

          selCount.textContent = selected.size;
          totalPrice.textContent = (selected.size * PRICE_PER_WEEK).toLocaleString();
          btnHold.disabled = selected.size === 0;
        });
      });

      selCount.textContent = selected.size;
      totalPrice.textContent = (selected.size * PRICE_PER_WEEK).toLocaleString();
      btnHold.disabled = selected.size === 0;
    }

    async function fetchWeeks() {
      const res = await fetch(API_BASE + 'get_weeks.php?limit=48');
      const data = await res.json();
      weeks = data.weeks || [];
      render();
    }

    async function holdSelected() {
      const week_ids = Array.from(selected);
      btnHold.disabled = true;
      try {
        const form = new URLSearchParams();
        form.append('lead_id', leadId);
        week_ids.forEach(w => form.append('week_ids[]', w));

        const res = await fetch(API_BASE + 'create_hold.php', {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body: form.toString()
        });
        const data = await res.json();
        if (data.status !== 'ok') throw new Error(data.message || 'Hold failed');

        // Save reservation IDs for checkout
        const reservationIds = data.reservation_ids || [];
        sessionStorage.setItem('tb_reservation_ids', JSON.stringify(reservationIds));

        const total = (week_ids.length * PRICE_PER_WEEK).toLocaleString();
        const go = await Swal.fire({
          icon: 'success',
          title: 'Weeks Held!',
          html: `
            <div class="text-start">
              <p><b>${week_ids.length}</b> week(s) held for 15 minutes.</p>
              <p>Total Due: <b>$${total}</b></p>
              <p class="mb-0">Next: proceed to payment to finalize your slots.</p>
            </div>
          `,
          confirmButtonText: 'Proceed to Payment',
          showCancelButton: true,
          cancelButtonText: 'Keep Browsing'
        });
        if (go.isConfirmed) await goToCheckout(reservationIds);

      } catch (err) {
        console.error(err);
        Swal.fire('Oops', err.message || 'Something went wrong.', 'error');
      } finally {
        btnHold.disabled = false;
      }
    }

    async function goToCheckout(reservationIds) {
      if (!reservationIds || !reservationIds.length) {
        Swal.fire('No Holds', 'Nothing to pay for.', 'info');
        return;
      }
      const form = new URLSearchParams();
      form.append('lead_id', leadId);
      reservationIds.forEach(id => form.append('reservation_ids[]', id));

      // Hit your existing PistonPay creator
      const res = await fetch('/wp-content/plugins/pistonpay/create_payment.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: form.toString()
      });
      const data = await res.json();
      if (data.success && data.url) {
        window.location.href = data.url;
      } else {
        Swal.fire('Payment Error', data.message || 'Could not create checkout session', 'error');
      }
    }

    // show toast if coming back with ?paid or ?canceled
    function showResultToasts() {
      const params = new URLSearchParams(window.location.search);
      if (params.has('paid')) {
        Swal.fire('Paid!', 'Thanks—your payment was processed.', 'success');
      } else if (params.has('canceled')) {
        Swal.fire('Payment canceled', 'Your holds will expire if not completed.', 'info');
      }
    }

    btnHold.addEventListener('click', holdSelected);
    fetchWeeks().then(showResultToasts);
  </script>
</body>
</html>
