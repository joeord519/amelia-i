<?php
// /wp-content/plugins/checkout/test-conversation.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>🧪 Test Amelia Conversations</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <style>
  .chat-box {
    background: #f9f9f9;
    border-radius: 12px;
    padding: 20px;
    height: 400px;
    overflow-y: auto;
    border: 1px solid #ddd;
  }

  .chat-message {
    margin-bottom: 15px;
    display: flex;
    align-items: flex-start;
  }

  .chat-message.user {
    justify-content: flex-end;
  }

  .chat-message.bot {
    justify-content: flex-start;
  }

  .chat-message span {
    display: inline-block;
    padding: 12px 16px;
    border-radius: 14px;
    max-width: 80%;
    font-size: 15px;
    line-height: 1.5;
  }

  .chat-message.user span {
    background-color: #007bff;
    color: white;
  }

  .chat-message.bot span {
    background-color: #e3e3e3;
    color: #212529;
  }

  /* 🔗 Normal Links */
  .chat-message.bot span a {
    color: #0d6efd;
    font-weight: 600;
    text-decoration: underline;
  }

  .chat-message.bot span a:hover {
    color: #063c9f;
    text-decoration: none;
  }

  /* 🟦 Bootstrap-Like Button Links */
  .chat-message.bot span a.btn {
    display: inline-block;
    margin-top: 10px;
    padding: 8px 16px;
    font-weight: 600;
    font-size: 14px;
    border-radius: 6px;
    text-align: center;
    text-decoration: none;
    transition: all 0.2s ease-in-out;
  }

  .chat-message.bot span a.btn-primary {
    background-color: #0d6efd;
    color: #fff !important;
  }

  .chat-message.bot span a.btn-primary:hover {
    background-color: #063c9f;
    color: #fff;
  }

  .chat-message.bot span a.btn-success {
    background-color: #198754;
    color: #fff !important;
  }

  .chat-message.bot span a.btn-success:hover {
    background-color: #145c38;
  }

  .chat-message.bot span a.btn-outline-dark {
    background-color: transparent;
    border: 1px solid #343a40;
    color: #343a40 !important;
  }

  .chat-message.bot span a.btn-outline-dark:hover {
    background-color: #343a40;
    color: #fff !important;
  }

  .chat-message.bot span a.text-danger {
    color: #dc3545 !important;
    font-weight: bold;
  }

  .chat-message.bot span a.text-danger:hover {
    color: #a71d2a !important;
    text-decoration: none;
  }

  .chat-message.bot span img.chat-thumbnail {
  max-width: 180px;
  max-height: 120px;
  border-radius: 8px;
  margin-top: 10px;
  display: block;
}
</style>

</head>
<body class="bg-light">

<div class="container py-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>🧪 Test Amelia Conversations</h2>
    <a href="admin-training.php" class="btn btn-outline-dark">← Back to Training Panel</a>
  </div>

  <div class="mb-3">
    <label for="test_category" class="form-label">Choose Category</label>
    <select name="test_category" id="test_category" class="form-select" style="max-width: 300px;">
  <option value="general">General</option>
  <option value="accelerated_ppl">Accelerated PPL</option>
  <option value="discovery_flights">Discovery Flights</option>
  <option value="zero_to_hero">Zero to Hero</option>
  <option value="ground_school">Ground School Bootcamp</option>
  <option value="rusty_pilot">Rusty Pilot</option>
  <option value="pinch_hitter">Pinch Hitter</option>
  <option value="purdue_global">Purdue Global</option>
  <option value="financing">Financing Options</option>
  <option value="pay_as_you_go">Pay as you Go</option>
  <option value="private_pilot_package">Private Pilot Package</option>
  <option value="boeing_package">Boeing Package</option>
  <option value="multi_engine">Multi Engine</option>
  <option value="maintenance">Maintenance Talk</option>
  <option value="pricing">Pricing</option>
  <option value="joe">About Joe</option>
  <option value="meghen">About Meghen</option>
  <option value="cfis">For CFIs</option>
</select>


  </div>

  <div class="chat-box mb-3" id="chatbox"></div>

  <form id="chatForm" class="d-flex gap-2">
    <input type="text" id="userMessage" class="form-control" placeholder="Ask Amelia something..." required>
    <button type="submit" class="btn btn-primary">Send</button>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  const chatbox = document.getElementById('chatbox');
  const chatForm = document.getElementById('chatForm');
  const messageInput = document.getElementById('userMessage');

  function appendMessage(sender, message) {
    const div = document.createElement('div');
    div.className = 'chat-message ' + sender;
    div.innerHTML = `<span>${sender === 'bot' ? message : escapeHTML(message)}</span>`;
    chatbox.appendChild(div);
    chatbox.scrollTop = chatbox.scrollHeight;
  }

  function escapeHTML(text) {
    return text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  chatForm.addEventListener('submit', function (e) {
    e.preventDefault();
    const message = messageInput.value.trim();
    const category = document.getElementById('test_category').value;

    if (!message) return;

    appendMessage('user', message);
    messageInput.value = '';

    fetch('../amelia-chatbot/send-to-openai.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message, category })
    })
    .then(res => res.json())
    .then(data => {
      if (data.reply === '[trigger_registration_flow]') {
        launchAcceleratedSignupFlow();
      } else {
        appendMessage('bot', data.reply);
      }
    })
    .catch(err => {
      appendMessage('bot', '❌ Something went wrong.');
      console.error(err);
    });
  });

  function launchAcceleratedSignupFlow() {
    const steps = [
      {
        title: 'Let’s Get You Locked In!',
        input: 'text',
        inputLabel: 'Full Name',
        inputPlaceholder: 'John Doe',
        inputValidator: (value) => !value && 'Please enter your name',
      },
      {
        title: 'Email Address',
        input: 'email',
        inputLabel: 'Email',
        inputPlaceholder: 'you@example.com',
        inputValidator: (value) => !value && 'Please enter your email',
      },
      {
        title: 'Phone Number',
        input: 'text',
        inputLabel: 'Phone',
        inputPlaceholder: '(314) 555-1234',
        inputValidator: (value) => !value && 'Please enter your phone number',
      },
      {
        title: 'When Do You Want to Start?',
        input: 'select',
        inputOptions: {
          'August 2025': 'August 2025',
          'September 2025': 'September 2025',
          'October 2025': 'October 2025'
        },
        inputPlaceholder: 'Choose a month'
      },
      {
        title: 'Housing Preference',
        input: 'radio',
        inputOptions: {
          'Shared': 'Shared (included)',
          'Solo': 'Solo (+$1500)'
        }
      }
    ];

    let formData = {};

    Swal.mixin({
      progressSteps: ['1', '2', '3', '4', '5'],
      confirmButtonText: 'Next',
      showCancelButton: true,
      allowOutsideClick: false
    }).queue(steps).then((result) => {
      if (result.value) {
        const [full_name, email, phone, selected_month, housing_choice] = result.value;
        formData = { full_name, email, phone, selected_month, housing_choice };

        // Step 1: Register lead
        fetch('/wp-content/plugins/accelerated/register_accelerated.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams(formData)
        })
        .then(res => res.json())
        .then(data => {
          if (data.status === 'success') {
            const lead_id = data.lead_id;

            // Step 2: Generate Stripe link
            return fetch('/wp-content/plugins/accelerated/generate_stripe_link.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: new URLSearchParams({ lead_id })
            });
          } else {
            throw new Error('Registration failed');
          }
        })
        .then(res => res.json())
        .then(data => {
          if (data.status === 'success') {
            appendMessage('bot', `🚀 You’re almost there!<br><a href="${data.checkout_url}" class="btn btn-success" target="_blank">Pay $5,000 Now</a><br>Once paid, we’ll email you next steps. Welcome to the cockpit. 🛫`);
          } else {
            throw new Error('Stripe link error');
          }
        })
        .catch(err => {
          console.error(err);
          appendMessage('bot', '❌ Something went wrong during registration. Please try again or contact support.');
        });
      }
    });
  }
</script>

</body>
</html>
