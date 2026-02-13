jQuery(function ($) {

  const $toggle   = $('#luke-chat-toggle');
  const $panel    = $('#luke-chat-panel');
  const $close    = $('.luke-close-btn');
  const $form     = $('#luke-chat-form');
  const $input    = $('#luke-chat-input');
  const $messages = $('#luke-chat-messages');

  const $overlay       = $('#luke-lightbox-overlay');
  const $overlayBody   = $('.luke-lightbox-body');
  const $overlayTitle  = $('#luke-lightbox-title');
  const $overlayClose  = $('.luke-lightbox-close');

  const sessionId = 'sess_' + Math.random().toString(36).substring(2, 10);

  let typingEl = null;

  // ----- UI TOGGLE -----
  $toggle.on('click', function () {
    if ($panel.is(':visible')) {
      $panel.hide();
    } else {
      $panel.css('display', 'flex');
    }
  });

  $close.on('click', function () {
    $panel.hide();
  });

  // ----- MESSAGES -----
  function appendMessage(text, from) {
    const cls = (from === 'user') ? 'user' : 'bot';
    const $bubble = $('<div/>', { class: 'luke-message ' + cls })
      .append($('<div/>', { class: 'luke-message-bubble', html: text }));
    $messages.append($bubble);
    $messages.scrollTop($messages[0].scrollHeight);
  }

  // ----- TYPING INDICATOR -----
  function showTyping() {
    if (typingEl) return;
    typingEl = $('<div/>', { class: 'luke-message bot luke-typing' });
    const $bubble = $('<div/>', { class: 'luke-message-bubble' });
    for (let i = 0; i < 3; i++) {
      $bubble.append($('<span/>', { class: 'luke-typing-dot' }));
    }
    typingEl.append($bubble);
    $messages.append(typingEl);
    $messages.scrollTop($messages[0].scrollHeight);
  }

  function hideTyping() {
    if (typingEl) {
      typingEl.remove();
      typingEl = null;
    }
  }

  // ----- ACTION BUTTONS / LIGHTBOX -----
  function renderActions(actions) {
  if (!actions || !Array.isArray(actions) || !actions.length) return;

  const $wrap = $('<div/>', { class: 'luke-actions-wrapper' });
  actions.forEach(action => {
    if (!action || !action.label) return;

    const $btn = $('<button/>', {
      type: 'button',
      class: 'luke-action-btn',
      text: action.label
    });

    if (action.type === 'stripe_checkout' && action.program_slug) {
      // Stripe checkout action
      $btn.on('click', () => startStripeCheckout(action));
    } else {
      // Fallback to lightbox / info
      $btn.on('click', () => openLightbox(action));
    }

    $wrap.append($btn);
  });

  $messages.append($wrap);
  $messages.scrollTop($messages[0].scrollHeight);
}

  function openLightbox(action) {
    if (!action || !action.url) return;
    $overlayBody.empty();

    const url = action.url;
    $overlayTitle.text(action.label || 'Luke');

    if (url.match(/\.(mp4|webm|mov)$/i)) {
      $overlayBody.html('<video controls autoplay src="' + url + '"></video>');
    } else if (url.match(/\.(jpg|jpeg|png|gif)$/i)) {
      $overlayBody.html('<img src="' + url + '" alt="">');
    } else {
      $overlayBody.html('<iframe src="' + url + '" frameborder="0" allowfullscreen></iframe>');
    }

    $overlay.css('display', 'flex');
  }

  function startStripeCheckout(action) {
  // Show a little feedback in the chat
  appendMessage("Got it — let me pull up a secure checkout link for that option.", 'bot');

  $.ajax({
    method: 'POST',
    url: 'luke-checkout.php',
    contentType: 'application/json',
    dataType: 'json',
    data: JSON.stringify({
      program_slug: action.program_slug
    })
  })
  .done(function (resp) {
    if (resp && resp.ok && resp.checkout_url) {
      // Optionally show it in the chat too
      appendMessage(
        'Here’s your secure checkout link. I’ll be here when you’re done if you have more questions.',
        'bot'
      );
      window.location.href = resp.checkout_url;
    } else {
      appendMessage("I couldn't get a checkout link for that one. Try again in a moment or ask me about another option.", 'bot');
    }
  })
  .fail(function () {
    appendMessage("Something glitched while talking to Stripe. Try again in a second.", 'bot');
  });
}

  $overlayClose.on('click', () => $overlay.hide());
  $overlay.on('click', function (e) {
    if (e.target === this) $overlay.hide();
  });

  // ----- FORM SUBMIT / BACKEND CALL -----
  $form.on('submit', function (e) {
    e.preventDefault();

    const msg = $.trim($input.val());
    if (!msg) return;

    appendMessage(msg, 'user');
    $input.val('').prop('disabled', true);
    showTyping();

    $.ajax({
      method: 'POST',
      url: 'luke-api.php',
      contentType: 'application/json',
      dataType: 'json',
      data: JSON.stringify({
        session_id: sessionId,
        message: msg
      })
    })
    .done(function (resp) {
      hideTyping();

      if (resp && resp.ok && resp.reply) {
        appendMessage(resp.reply, 'bot');
      } else if (resp && resp.reply) {
        appendMessage(resp.reply, 'bot');
      } else {
        appendMessage("I hit a little turbulence on the backend. Try again in a second.", 'bot');
      }

      if (resp && resp.meta && Array.isArray(resp.meta.actions)) {
        renderActions(resp.meta.actions);
      }
    })
    .fail(function () {
      hideTyping();
      appendMessage("My connection glitched. Mind sending that one more time?", 'bot');
    })
    .always(function () {
      $input.prop('disabled', false).focus();
    });
  });

});
