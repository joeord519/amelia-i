// joey.js – SweetAlert2 chat UI for Joey 🤠
// Requires SweetAlert2 and fetch() support.

window.openJoey = function (studentId) {
  if (!studentId) {
    console.error("openJoey called without studentId");
    return;
  }

  const MAX_ROUND = 4;

  const messages = []; // conversation history for OpenAI
  let chatHtml = "";

  // Tracks the last deal Joey priced so we can bump the round
  // shape: { aircraft: number, instructor: number, round: number }
  let lastDeal = null;

  // Tracks the latest deal data returned by joey_chat.php for checkout
  let currentDeal = null; // latest_deal
  let currentDealPriceString = null; // latest_deal_price_string

  // We'll capture the pay button element in didOpen
  let payBtnEl = null;

  function renderChat() {
    return `
      <div id="joey-chat-log" style="max-height:300px;overflow-y:auto;text-align:left;font-size:14px;">
        ${
          chatHtml ||
          '<div style="color:#666;">Joey is ready when you are. Tell him what you&apos;re hoping to do with your training.</div>'
        }
      </div>
      <textarea id="joey-chat-input"
        style="width:100%;margin-top:10px;min-height:60px;font-size:14px;padding:6px;"
        placeholder="Ask Joey for a deal on flight hours..."></textarea>
      <div style="margin-top:4px;font-size:12px;color:#666;">
        Pro tip: Joey reads hours best if you type them like <strong>20/20</strong> (aircraft/instructor) or <strong>10/15</strong>.
      </div>
    `;
  }

  function appendMessage(role, text) {
    const safe = String(text || "")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\n/g, "<br>");
    if (role === "user") {
      chatHtml += `<div style="margin-top:8px;text-align:right;"><div style="display:inline-block;background:#003883;color:#fff;padding:6px 10px;border-radius:12px 12px 0 12px;">${safe}</div></div>`;
    } else {
      chatHtml += `<div style="margin-top:8px;text-align:left;"><div style="display:inline-block;background:#f1f1f1;color:#222;padding:6px 10px;border-radius:12px 12px 12px 0;"><strong>Joey:</strong> ${safe}</div></div>`;
    }
    const log = document.getElementById("joey-chat-log");
    if (log) {
      log.innerHTML = chatHtml;
      log.scrollTop = log.scrollHeight;
    }
  }

  /**
   * Try to parse aircraft / instructor hours from free text.
   * Supports:
   *  - "15 aircraft hours" / "15 plane hours"
   *  - "15 aircraft and 16 instructor hours"
   *  - "10 plane 8 cfi"
   *  - "15/16"  (first = aircraft, second = instructor)
   *  - "A15 I16" / "A 15 I 16"
   *  - "5 and 5" / "5 & 5" / "5 n 5"
   *
   * Returns { aircraft: number|null, instructor: number|null } or null if nothing obvious.
   */
  function parseDealFromText(text) {
    if (!text) return null;
    const lower = text.toLowerCase();

    let aircraft = null;
    let instructor = null;

    // 0) Shorthand "5 and 5" / "5 & 5" / "5 n 5"
    const shorthandMatch = lower.match(
      /(\d+(\.\d+)?)\s*(?:and|&|n)\s*(\d+(\.\d+)?)/i
    );
    if (shorthandMatch) {
      aircraft = parseFloat(shorthandMatch[1]);
      instructor = parseFloat(shorthandMatch[3]);
    }

    // 1) Shorthand "15/16" -> 15 aircraft, 16 instructor
    if (aircraft === null || instructor === null) {
      const slashMatch = lower.match(/(\d+(\.\d+)?)\s*\/\s*(\d+(\.\d+)?)/);
      if (slashMatch) {
        aircraft = parseFloat(slashMatch[1]);
        instructor = parseFloat(slashMatch[3]);
      }
    }

    // 2) Tagged "A15 I16" or "A 15 I 16"
    if (aircraft === null || instructor === null) {
      const aTagMatch = lower.match(/\ba\s*([0-9]+(\.[0-9]+)?)/);
      const iTagMatch = lower.match(/\bi\s*([0-9]+(\.[0-9]+)?)/);
      if (aTagMatch && iTagMatch) {
        aircraft = parseFloat(aTagMatch[1]);
        instructor = parseFloat(iTagMatch[1]);
      }
    }

    // 3) Pair like "10 aircraft ... 8 instructor" or "10 plane 8 cfi"
    if (aircraft === null || instructor === null) {
      const pairMatch = lower.match(
        /(\d+(\.\d+)?)\s*(aircraft|plane|flight)\b.*?(\d+(\.\d+)?)\s*(instructor|cfi)\b/
      );
      if (pairMatch) {
        aircraft = parseFloat(pairMatch[1]);
        instructor = parseFloat(pairMatch[4]);
      }
    }

    // 3b) Reverse pair: "20 instructor ... 8 aircraft"
    if (aircraft === null || instructor === null) {
      const reversePairMatch = lower.match(
        /(\d+(\.\d+)?)\s*(instructor|cfi)\b.*?(\d+(\.\d+)?)\s*(aircraft|plane|flight)\b/
      );
      if (reversePairMatch) {
        instructor = parseFloat(reversePairMatch[1]);
        aircraft = parseFloat(reversePairMatch[4]);
      }
    }

    // 4) Single aircraft: "15 aircraft hours", "15 plane", "15 flight"
    if (aircraft === null) {
      const aircraftMatch1 = lower.match(
        /(\d+(\.\d+)?)\s*(aircraft|plane|flight)\s*hours?/
      );
      const aircraftMatch2 = lower.match(
        /(\d+(\.\d+)?)\s*(aircraft|plane|flight)(?!\w)/
      );
      if (aircraftMatch1) {
        aircraft = parseFloat(aircraftMatch1[1]);
      } else if (aircraftMatch2) {
        aircraft = parseFloat(aircraftMatch2[1]);
      }
    }

    // 5) Single instructor: "16 instructor hours", "16 cfi hours", "16 cfi"
    if (instructor === null) {
      const instructorMatch1 = lower.match(
        /(\d+(\.\d+)?)\s*(instructor|cfi)\s*hours?/
      );
      const instructorMatch2 = lower.match(
        /(\d+(\.\d+)?)\s*(instructor|cfi)(?!\w)/
      );
      if (instructorMatch1) {
        instructor = parseFloat(instructorMatch1[1]);
      } else if (instructorMatch2) {
        instructor = parseFloat(instructorMatch2[1]);
      }
    }

    // If we didn't get anything, bail.
    if (aircraft === null && instructor === null) {
      return null;
    }

    return { aircraft, instructor };
  }

  /**
   * Decide what deal parameters (if any) to send to the backend for this user message.
   *
   * Rules:
   *  - First explicit hours (5&5, 10/10, etc.)        => round 1
   *  - Later explicit hours (upsell / downsize)       => bump round, capped at 4
   *  - "can you do any better?" / "nah" / "stay here" => same hours, next round if <4
   *  - If already at round 4 and they keep haggling   => no new deal params (Joey backs off)
   */
  function determineDealParams(userText) {
    let dealAircraftHours = null;
    let dealInstructorHours = null;
    let dealRound = null;

    const parsed = parseDealFromText(userText);

    if (parsed && parsed.aircraft !== null && parsed.instructor !== null) {
      // User explicitly stated both aircraft and instructor hours
      const a = parsed.aircraft;
      const i = parsed.instructor;

      if (lastDeal) {
        const lastR = lastDeal.round || 1;
        const nextR = Math.min(lastR + 1, MAX_ROUND);
        dealRound = nextR;
      } else {
        // First time we see hours at all
        dealRound = 1;
      }

      dealAircraftHours = a;
      dealInstructorHours = i;
      lastDeal = { aircraft: a, instructor: i, round: dealRound };
    } else {
      // No explicit full pair in this message; maybe it's a negotiation or "stay here" signal
      const negotiationPhrase =
        /(can you\s*(do|cut)\s*(any\s*)?better\b|better deal|sharpen (that|this)|more off|lower price|any lower|better price|cheaper|any wiggle room|tighten.*up|can you improve|\bnah\b|\bno\b|\bnope\b|keep it here|stay here|keep it this size|stay at this size)/i;

      if (lastDeal && negotiationPhrase.test(userText)) {
        const lastR = lastDeal.round || 1;

        // Already at max round → no new deal params (this will trigger Joey's back-off behavior)
        if (lastR >= MAX_ROUND) {
          return null;
        }

        const nextR = Math.min(lastR + 1, MAX_ROUND);
        dealAircraftHours = lastDeal.aircraft;
        dealInstructorHours = lastDeal.instructor;
        dealRound = nextR;
        lastDeal.round = nextR;
      }
    }

    if (
      dealAircraftHours !== null &&
      dealInstructorHours !== null &&
      dealRound !== null &&
      dealRound > 0
    ) {
      return {
        aircraft: dealAircraftHours,
        instructor: dealInstructorHours,
        round: dealRound,
      };
    }

    return null;
  }

  // Start checkout for the most recent deal Joey priced
  function startCheckoutForCurrentDeal() {
    if (!currentDeal || !currentDealPriceString) {
      appendMessage(
        "assistant",
        "I need to price a package first before we can pay. Tell me what hours you want and I’ll run the numbers."
      );
      return;
    }

    fetch("/wp-content/plugins/deal-agent/create_deal_checkout.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        student_id: studentId,
        latest_deal: currentDeal,
        latest_deal_price_string: currentDealPriceString,
      }),
    })
      .then((r) => r.text())
      .then((raw) => {
        console.log("Raw response from create_deal_checkout.php:", raw);

        let data;
        try {
          data = JSON.parse(raw);
        } catch (e) {
          console.error("JSON parse error (checkout):", e);
          appendMessage(
            "assistant",
            "I tried to set up payment but got a weird response from the server. Let the front desk know Joey's checkout endpoint needs a look."
          );
          return;
        }

        if (!data.success) {
          console.error("Checkout error:", data.error);
          appendMessage(
            "assistant",
            "Something glitched setting up payment. Let the front desk know Joey couldn't create the checkout link."
          );
          return;
        }

        // Redirect to hosted checkout
        window.location = data.checkout_url;
      })
      .catch((err) => {
        console.error(err);
        appendMessage(
          "assistant",
          "Payment setup hit some turbulence. Mind trying again in a minute?"
        );
      });
  }

  function sendToJoey(userText) {
    // push user message into history
    messages.push({ role: "user", content: userText });

    const dealParams = determineDealParams(userText);

    const payload = {
      student_id: studentId,
      messages: messages,
    };

    if (dealParams) {
      payload.deal_aircraft_hours = dealParams.aircraft;
      payload.deal_instructor_hours = dealParams.instructor;
      payload.deal_round = dealParams.round;
      console.log("Sending deal params to Joey:", dealParams);
    }

    fetch("/wp-content/plugins/deal-agent/joey_chat.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
    })
      .then(async (r) => {
        const raw = await r.text();
        console.log("Raw response from joey_chat.php:", raw);

        let data;
        try {
          data = JSON.parse(raw);
        } catch (e) {
          console.error("JSON parse error:", e);
          appendMessage(
            "assistant",
            "I hit a little turbulence talking to HQ. I got a weird response instead of a proper deal. Let the front desk know Joey's endpoint needs a quick look."
          );
          return;
        }

        if (!data.success) {
          appendMessage(
            "assistant",
            "Whew, something spooked the server. Tell the front desk Joey hit a snag: " +
              (data.error || "Unknown error.")
          );
          return;
        }

        appendMessage(
          "assistant",
          data.reply || "(Joey got shy and didn't say anything.)"
        );
        messages.push({ role: "assistant", content: data.reply || "" });

        // Only update currentDeal if we actually got a priced deal back.
        if (data.latest_deal && data.latest_deal_price_string) {
          currentDeal = data.latest_deal;
          currentDealPriceString = data.latest_deal_price_string;
          window.joeyCurrentDeal = currentDeal;
          window.joeyCurrentDealPrice = currentDealPriceString;

          // Show / enable the pay button now that we have a priced deal
          if (payBtnEl) {
            payBtnEl.style.display = "inline-block";
            payBtnEl.disabled = false;
          }
        }
      })
      .catch((err) => {
        console.error(err);
        appendMessage(
          "assistant",
          "I hit a little turbulence talking to HQ. Mind trying again in a minute?"
        );
      });
  }

  Swal.fire({
    title: "Joey – Piston Deal Desk 🤠",
    html: renderChat(),
    width: 600,
    showCancelButton: true,
    showConfirmButton: true,
    confirmButtonText: "Send",
    cancelButtonText: "Close",
    footer:
      '<button id="joey-pay-btn" class="swal2-confirm swal2-styled" style="background:#00bf63;margin-top:8px;display:none;">Lock this deal in &amp; pay</button>',
    didOpen: () => {
      // Intro text
      appendMessage(
        "assistant",
        "Hi, I&apos;m Joey, your flight hour deal agent. Tell me what you&apos;re looking for and I&apos;ll see what kind of deal I can get for you."
      );

      const inputEl = document.getElementById("joey-chat-input");
      if (inputEl) {
        inputEl.focus();
        inputEl.addEventListener("keydown", function (e) {
          if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            Swal.clickConfirm();
          }
        });
      }

      payBtnEl = document.getElementById("joey-pay-btn");
      if (payBtnEl) {
        payBtnEl.addEventListener("click", function () {
          startCheckoutForCurrentDeal();
        });
        // Start hidden/disabled until we have a priced deal
        payBtnEl.style.display = "none";
        payBtnEl.disabled = true;
      }
    },
    preConfirm: () => {
      const inputEl = document.getElementById("joey-chat-input");
      if (!inputEl) return false;
      const text = inputEl.value.trim();
      if (!text) {
        return false; // don't close
      }
      appendMessage("user", text);
      inputEl.value = "";
      // kick off backend + OpenAI call
      sendToJoey(text);
      return false; // keep modal open
    },
  });
};
