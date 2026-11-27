<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['aiChatCsrfToken']) || !is_string($_SESSION['aiChatCsrfToken'])) {
    $_SESSION['aiChatCsrfToken'] = bin2hex(random_bytes(32));
}

$chatScriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$pagesMarker = stripos($chatScriptName, '/pages/');
$pagesBaseUrl = $pagesMarker === false
    ? '/pages'
    : substr($chatScriptName, 0, $pagesMarker) . '/pages';
$chatBaseUrl = htmlspecialchars($pagesBaseUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$chatCsrfToken = htmlspecialchars($_SESSION['aiChatCsrfToken'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<link rel="stylesheet" href="<?= $chatBaseUrl ?>/assets/ai_chat_widget.css?v=2">

<button
    id="chat-toggle"
    type="button"
    aria-label="Open AI Assistant"
    aria-controls="chat-container"
    aria-expanded="false"
>
    💬
</button>

<div id="chat-container"
     aria-hidden="true"
     data-api-url="<?= $chatBaseUrl ?>/ai_chat_api.php"
     data-csrf-token="<?= $chatCsrfToken ?>">
    <div id="chat-header">
        <h3>AI Assistant</h3>
        <button id="close-chat" type="button" aria-label="Close chat">×</button>
    </div>

    <div id="chat-messages" aria-live="polite">
        <div class="message bot">
            Hello! How can I help you?
        </div>
    </div>

    <div id="chat-input">
        <input
            type="text"
            id="user-message"
            placeholder="Ask something..."
            autocomplete="off"
            maxlength="4000"
        >
        <button id="send-button" type="button">Send</button>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.1.6/dist/purify.min.js"></script>
<script src="<?= $chatBaseUrl ?>/assets/ai_chat_widget.js?v=2" defer></script>
