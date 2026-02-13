<?php
// luke-ui.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Luke – Piston Aviation AI Sales Agent</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Load Luke CSS -->
    <link rel="stylesheet" href="/wp-content/plugins/Luke/luke-chat.css?v=3001">

    <style>
        body {
            margin: 0;
            padding: 0;
            background: #f5f7fa;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            text-align: center;
        }
        .info {
            margin-top: 40px;
            font-size: 22px;
            color: #333;
            font-weight: 600;
        }
    </style>
</head>

<body>

<div class="info">
    Luke AI Sales Agent Test Page<br>
    Click the blue Luke bubble to open.
</div>

<!-- FLOATING LUKE CHAT CONTAINER -->
<div id="luke-chat-container">

    <!-- Floating Button -->
    <button id="luke-chat-toggle">
        <span class="bubble-avatar">LU</span>
        <span class="bubble-text">
            <span class="bt-line1">Luke</span>
            <span class="bt-line2">Piston Aviation Expert</span>
            <span class="bt-line3">Ask Me Anything!!</span>
        </span>
    </button>

    <!-- Chat Panel -->
    <div id="luke-chat-panel">

        <div class="luke-chat-header">
            <div class="header-left">
                <div class="header-avatar">LU</div>
                <div class="header-text">
                    <div class="name">Luke</div>
                    <div class="subtitle">Piston Aviation AI Sales Agent</div>
                </div>
            </div>
            <button class="luke-close-btn" type="button">&times;</button>
        </div>

        <!-- Messages -->
        <div id="luke-chat-messages" class="luke-chat-messages">
            <div class="luke-message bot">
                <div class="luke-message-bubble">
                    Hey, I’m Luke. Tell me where you are in your flying journey and what you're trying to do next.
                </div>
            </div>
        </div>

        <!-- Input Form -->
        <form id="luke-chat-form" class="luke-chat-form" autocomplete="off">
            <input id="luke-chat-input" class="luke-chat-input"
                   type="text" name="message"
                   placeholder="Ask about training, financing, programs..." required>
            <button class="luke-chat-send" type="submit">Send</button>
        </form>

    </div>
</div>

<!-- Lightbox Overlay -->
<div id="luke-lightbox-overlay" style="display:none;">
    <div class="luke-lightbox-inner">
        <div class="luke-lightbox-header">
            <span id="luke-lightbox-title">Luke</span>
            <button type="button" class="luke-lightbox-close">&times;</button>
        </div>
        <div class="luke-lightbox-body"></div>
    </div>
</div>

<!-- Load jQuery (required for Luke UI on this standalone page) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Load Luke JS -->
<script src="/wp-content/plugins/Luke/luke-chat.js?v=3001"></script>

</body>
</html>

