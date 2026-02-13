const chatbox = document.getElementById('chatbox');
const input = document.getElementById('input');
const sendBtn = document.getElementById('sendBtn');

sendBtn.addEventListener('click', sendMessage);
input.addEventListener('keypress', e => {
  if (e.key === 'Enter') sendMessage();
});

function sendMessage() {
  const userText = input.value.trim();
  if (!userText) return;
  
  appendMessage('You', userText);
  input.value = '';

  fetch('send-to-openai.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ message: userText })
  })
  .then(res => res.json())
  .then(data => appendMessage('Amelia', data.reply))
  .catch(err => appendMessage('Amelia', 'Something went wrong.'));
}

function appendMessage(sender, text) {
  const msg = document.createElement('div');
  msg.innerHTML = `<strong>${sender}:</strong> ${text}`;
  chatbox.appendChild(msg);
  chatbox.scrollTop = chatbox.scrollHeight;
}
