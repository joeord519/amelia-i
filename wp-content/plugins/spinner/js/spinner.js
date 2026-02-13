(function () {
  // ---- SweetAlert2 loader (if not present) ----
  if (typeof Swal === 'undefined') {
    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
    s.defer = true;
    document.head.appendChild(s);
  }

  // ---- Tunables for the spin feel ----
  const SPIN_CONFIG = {
    durationMs: 4600,     // total spin time (ms). Try 4200–5200
    fullTurns: 6.5,       // full rotations before easing to target
    randomJitterDeg: 4,   // tiny randomness so repeat spins feel organic
    settleDelayMs: 250    // short pause after spin before showing result
  };

  // ---- Safe fetch helpers (log raw non-JSON to console) ----
  function _parseJSON(url, text) {
    try { return JSON.parse(text); }
    catch (e) { console.error('Non-JSON response from', url, text); throw e; }
  }
  function postJSON(url, obj) {
    return fetch(url, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(obj || {})
    }).then(r => r.text()).then(t => _parseJSON(url, t));
  }
  function getJSON(url) {
    return fetch(url).then(r => r.text()).then(t => _parseJSON(url, t));
  }

  // ---- Canvas wheel helper ----
  function makeWheel(canvas, slices) {
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const size = 340; // logical size
    canvas.width = size * dpr;
    canvas.height = size * dpr;
    canvas.style.width = size + 'px';
    canvas.style.height = size + 'px';
    ctx.scale(dpr, dpr);

    const cx = size/2, cy = size/2, r = size/2 - 14;
    const n = slices.length;
    const arc = (2*Math.PI)/n;

    function draw(angle) {
      ctx.clearRect(0,0,size,size);

      // Wheel slices
      for (let i=0;i<n;i++){
        const start = i*arc + angle;
        const end = start + arc;

        // Slice
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.arc(cx, cy, r, start, end);
        ctx.closePath();
        ctx.fillStyle = (i%2===0) ? '#eef2f7' : '#e3e9f2';
        ctx.fill();

        // Label
        ctx.save();
        ctx.translate(cx, cy);
        ctx.rotate(start + arc/2);
        ctx.textAlign = 'right';
        ctx.fillStyle = '#111';
        ctx.font = 'bold 12px system-ui, -apple-system, Segoe UI, Roboto, Arial';
        const label = (slices[i].label || slices[i].code || '').slice(0, 32);
        ctx.fillText(label, r - 14, 4);
        ctx.restore();
      }

      // Center hub
      // --- inside makeWheel draw(angle) ---
      // Center hub (circle only, no text)
      ctx.beginPath();
      ctx.arc(cx, cy, 14, 0, 2*Math.PI);
      ctx.fillStyle = '#111';
      ctx.fill();

            // Pointer (top, pointing downward into the wheel)
ctx.beginPath();
ctx.moveTo(cx, cy - r + 6);        // tip INSIDE the wheel (points down)
ctx.lineTo(cx - 12, cy - r - 18);  // left base OUTSIDE
ctx.lineTo(cx + 12, cy - r - 18);  // right base OUTSIDE
ctx.closePath();
ctx.fillStyle = '#ef4444';
ctx.fill();

// Pointer stem (optional, sits above the base)
ctx.fillRect(cx - 2, cy - r - 26, 4, 20);


    }

    return { draw, arc, r, cx, cy, size };
  }

  // ---- Spin animation (ease-out cubic, lands on target) ----
  async function spinAnimation(wheel, targetIndex, opts = {}) {
  const n   = opts.sliceCount || 8;
  const arc = wheel.arc;

  // The pointer is drawn at the TOP (12 o’clock) → angle = -π/2
  const POINTER_ANGLE = -Math.PI / 2;

  // Center angle of the target slice before rotation:
  const targetCenter = (targetIndex * arc) + (arc / 2);

  // Small randomness to keep it organic (can set to 0 while testing)
  const jitter = (SPIN_CONFIG.randomJitterDeg || 0) *
                 (Math.PI / 180) * (Math.random() > 0.5 ? 1 : -1);

  // We want: (startAngle + totalRotation + targetCenter) == POINTER_ANGLE
  // => finalAngle = POINTER_ANGLE - targetCenter (+ tiny jitter)
  const finalAngle = POINTER_ANGLE - targetCenter + jitter;

  const TAU      = Math.PI * 2;
  const total    = (SPIN_CONFIG.fullTurns * TAU) + ((finalAngle % TAU) + TAU) % TAU;
  const duration = Math.max(1200, SPIN_CONFIG.durationMs | 0);

  const t0 = performance.now();
  const ease = t => 1 - Math.pow(1 - t, 3); // easeOutCubic

  return new Promise(resolve => {
    function frame(now){
      const raw   = (now - t0) / duration;
      const t     = raw < 0 ? 0 : raw > 1 ? 1 : raw;
      const angle = total * ease(t);
      wheel.draw(angle);
      if (t < 1) requestAnimationFrame(frame);
      else resolve();
    }
    requestAnimationFrame(frame);
  });
}

  // ---- Result modal with countdown + CTA ----
  function showResult(created) {
  // Prefer UTC timestamp from server
  const expiresMs = created.expires_ts ? (created.expires_ts * 1000) : null;

  let interval = null;
  Swal.fire({
    title: `You won: ${created.prize_label}!`,
    html: `
      <p style="margin:6px 0;">Claim it by purchasing hours within <b>24 hours</b>.</p>
      <p id="spin-countdown" style="font-weight:700;margin:6px 0;"></p>
      <p style="font-size:12px;color:#666;margin-top:8px;">Bonus is applied automatically after payment with this link.</p>
    `,
    icon: "info",
    showCancelButton: true,
    confirmButtonText: "Buy Hours Now",
    cancelButtonText: "Later",
    didOpen: () => {
      const el = document.getElementById('spin-countdown');
      if (!expiresMs) { el.textContent = ""; return; }

      const tick = () => {
        const diff = expiresMs - Date.now();
        if (diff <= 0) { el.textContent = "Expired"; clearInterval(interval); return; }
        const h = Math.floor(diff / 3.6e6);
        const m = Math.floor((diff % 3.6e6) / 6e4);
        const s = Math.floor((diff % 6e4) / 1e3);
        el.textContent = `Expires in ${h}h ${m}m ${s}s`;
      };
      tick();
      interval = setInterval(tick, 1000);
    },
    willClose: () => { if (interval) clearInterval(interval); }
  }).then(res => {
    if (res.isConfirmed) {
      const url = created.purchase_url ||
        (PistonSpinnerConfig.HOURS_URL +
          (PistonSpinnerConfig.HOURS_URL.includes('?') ? '&' : '?') +
          'reward_token=' + encodeURIComponent(created.token));
      window.location.href = url;
    }
  });
}

  // ---- Public API ----
  window.PistonSpinner = {
    /**
     * Visual canvas wheel that lands on the server-selected prize.
     * context: "checkout_success" | "booking_success" | "milestone" | "promo"
     */
    maybeSpin: async function (student_id, context) {
      try {
        const elig = await postJSON(PistonSpinnerConfig.API.eligibility, { student_id, context });

        // If not eligible, silently bail (or show reason for testing)
        if (!elig || elig.eligible !== true) {
          // const r = (elig && (elig.reason || elig.error)) ? (elig.reason || elig.error) : 'not eligible';
          // Swal.fire("No spin this time", `Reason: <b>${r}</b>`, "info");
          return;
        }

        // 1) Fetch profile to draw the wheel
        const prof = await getJSON(
          PistonSpinnerConfig.API.profile + '?profile_key=' +
          encodeURIComponent(elig.profile_key || 'standard_nudge')
        );

        if (!prof || !prof.ok || !Array.isArray(prof.slices) || prof.slices.length < 2) {
          // Fallback (no visual) — should rarely happen
          const pre = await Swal.fire({
            title:"🎡 Spin the Hour Wheel!",
            text:"Win a bonus you can claim within 24 hours.",
            icon:"success", showCancelButton:true, confirmButtonText:"Spin it"
          });
          if (!pre.isConfirmed) return;
          const created = await postJSON(PistonSpinnerConfig.API.create, {
            student_id, profile_key: elig.profile_key || 'standard_nudge'
          });
          if (!created || !created.ok) {
            Swal.fire("Oops", created?.msg || "Could not create reward.", "error");
            return;
          }
          await Swal.fire({
            title:"Spinning...",
            html:`<div style="height:120px;display:flex;align-items:center;justify-content:center;font-size:28px;">🎯</div>`,
            timer: 1400, showConfirmButton:false
          });
          showResult(created);
          return;
        }

        // 2) Open a modal that DOES NOT close on confirm; spin inside preConfirm
        const html = `
          <div style="display:flex;flex-direction:column;align-items:center;gap:12px;">
            <canvas id="piston-wheel" width="340" height="340" style="display:block;"></canvas>
            <div class="muted" style="font-size:12px;color:#666">Win a bonus you can claim within 24 hours.</div>
          </div>
        `;

        const result = await Swal.fire({
          title: "🎡 Spin to Win Flight Time!",
          html,
          confirmButtonText: "Spin it",
          cancelButtonText: "Not now",
          showCancelButton: true,
          allowOutsideClick: false,
          allowEscapeKey: false,
          showLoaderOnConfirm: false, // we’re animating ourselves
          didOpen: () => {
            const canvas = document.getElementById('piston-wheel');
            const wheel = makeWheel(canvas, prof.slices);
            wheel.draw(0);
            canvas._wheel = wheel;
          },
          preConfirm: async () => {
            try {
              // Keep modal open while we do server call + animation
              const created = await postJSON(PistonSpinnerConfig.API.create, {
                student_id,
                profile_key: prof.key || 'standard_nudge'
              });
              if (!created || !created.ok) {
                await Swal.showValidationMessage(created?.msg || "Could not create reward.");
                return false;
              }

              const canvas = document.getElementById('piston-wheel');
              const wheel = canvas && canvas._wheel;
              if (!wheel) {
                await Swal.showValidationMessage("Wheel not ready.");
                return false;
              }

              // Map server prize to slice index on the wheel
              const code = (created.prize_code || '').trim();
              let idx = prof.slices.findIndex(s => (s.code || '').trim() === code);
              if (idx < 0) {
                idx = prof.slices.findIndex(s => (s.label || '').trim() === (created.prize_label || '').trim());
              }
              if (idx < 0) idx = Math.floor(Math.random() * prof.slices.length); // safety fallback

              await spinAnimation(wheel, idx, { sliceCount: prof.slices.length });
              await new Promise(r => setTimeout(r, SPIN_CONFIG.settleDelayMs));

              // Return prize payload to the .then() below
              return created;
            } catch (err) {
              console.error('spin preConfirm error', err);
              await Swal.showValidationMessage("Something went wrong.");
              return false;
            }
          }
        });

        // After preConfirm resolves, modal closes and we show the result
        if (result.isConfirmed && result.value) {
          showResult(result.value);
        }

      } catch (e) {
        console.error('PistonSpinner error', e);
      }
    }
  };
})();
