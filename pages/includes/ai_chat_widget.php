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
<link rel="stylesheet" href="<?= $chatBaseUrl ?>/assets/ai_chat_widget.css">
<button
    id="comfsci-chat-toggle"
    class="comfsci-chat-toggle"
    type="button"
    aria-label="Open ComfiBot assistant"
    aria-controls="comfsci-chat-panel"
    aria-expanded="false"
>
    <span aria-hidden="true">💬</span>
</button>

<section
    id="comfsci-chat-panel"
    class="comfsci-chat-panel"
    aria-hidden="true"
    aria-label="ComfiBot assistant"
    data-api-url="<?= $chatBaseUrl ?>/ai_chat_api.php"
    data-csrf-token="<?= $chatCsrfToken ?>"
>
    <header class="comfsci-chat-header">
        <div>
            <h2>AI Assistant</h2>
            <p>ComfiBot · ComfSci</p>
        </div>
        <button id="comfsci-chat-close" type="button" aria-label="Close chat">×</button>
    </header>

    <div id="comfsci-chat-messages" class="comfsci-chat-messages" aria-live="polite">
        <div class="comfsci-chat-message comfsci-chat-message-bot">
            Hello! I’m ComfiBot. How can I help you?
        </div>
    </div>

    <p class="comfsci-chat-privacy">
        Your question goes to the configured AI provider. Page text sharing is off unless you opt in below.
    </p>

    <label class="comfsci-chat-context">
        <input id="comfsci-chat-share-context" type="checkbox">
        <span>Include visible page text with this message</span>
        <small>It may contain personal information. Review the page before sharing.</small>
    </label>

    <form id="comfsci-chat-form" class="comfsci-chat-input">
        <label class="comfsci-chat-sr-only" for="comfsci-chat-user-message">Ask ComfiBot</label>
        <input id="comfsci-chat-user-message" type="text" maxlength="4000" placeholder="Ask something..." autocomplete="off" required>
        <button id="comfsci-chat-send" type="submit">Send</button>
    </form>
</section>
<script src="<?= $chatBaseUrl ?>/assets/ai_chat_widget.js" defer></script>
