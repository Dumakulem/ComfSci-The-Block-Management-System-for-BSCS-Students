document.addEventListener('DOMContentLoaded', function () {
    const panel = document.getElementById('comfsci-chat-panel');
    const toggle = document.getElementById('comfsci-chat-toggle');
    const closeButton = document.getElementById('comfsci-chat-close');
    const form = document.getElementById('comfsci-chat-form');
    const input = document.getElementById('comfsci-chat-user-message');
    const sendButton = document.getElementById('comfsci-chat-send');
    const shareContext = document.getElementById('comfsci-chat-share-context');
    const messages = document.getElementById('comfsci-chat-messages');

    if (!panel || !toggle || !closeButton || !form || !input || !sendButton || !shareContext || !messages) {
        return;
    }

    const history = [];

    function openChat() {
        panel.classList.add('comfsci-chat-active');
        panel.setAttribute('aria-hidden', 'false');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.hidden = true;
        input.focus();
    }

    function closeChat() {
        panel.classList.remove('comfsci-chat-active');
        panel.setAttribute('aria-hidden', 'true');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.hidden = false;
        toggle.focus();
    }

    function addMessage(content, sender) {
        const element = document.createElement('div');
        element.className = 'comfsci-chat-message comfsci-chat-message-' + sender;
        element.textContent = content;
        messages.appendChild(element);
        messages.scrollTop = messages.scrollHeight;
        return element;
    }

    function getPageContext(includeText) {
        let text = '';
        if (includeText) {
            const root = document.querySelector('[data-chat-context]') || document.querySelector('main') || document.body;
            const ignored = '#comfsci-chat-toggle, #comfsci-chat-panel, [data-chatbot-ignore]';
            const skipTags = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT', 'LINK']);

            if (root === document.body) {
                text = Array.from(document.body.children)
                    .filter(function (element) {
                        return !skipTags.has(element.tagName) && !element.matches(ignored);
                    })
                    .map(function (element) {
                        return element.innerText || '';
                    })
                    .join('\n');
            } else {
                text = root.innerText || '';
            }
            text = text.replace(/[ \t]+/g, ' ').replace(/\n{3,}/g, '\n\n').trim();
        }

        return {
            title: document.title.slice(0, 200),
            url: location.pathname.slice(0, 300),
            text: text.slice(0, 12000)
        };
    }

    async function sendMessage() {
        const message = input.value.trim();
        if (!message || sendButton.disabled) {
            return;
        }

        addMessage(message, 'user');
        input.value = '';
        sendButton.disabled = true;
        const includePageText = shareContext.checked;
        shareContext.checked = false;
        const responseMessage = addMessage('Thinking...', 'bot');

        try {
            const response = await fetch(panel.dataset.apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': panel.dataset.csrfToken
                },
                body: JSON.stringify({
                    message: message,
                    chatHistory: history.slice(-20),
                    pageContext: getPageContext(includePageText),
                    sharePageText: includePageText
                })
            });
            const data = await response.json().catch(function () {
                return {};
            });
            if (!response.ok) {
                throw new Error(data.error || 'The assistant could not complete that request.');
            }

            responseMessage.textContent = data.reply;
            history.push(
                { role: 'user', content: message },
                { role: 'assistant', content: data.reply }
            );
            if (Number.isInteger(data.remaining)) {
                const privacyNotice = document.querySelector('.comfsci-chat-privacy');
                if (privacyNotice) {
                    privacyNotice.textContent = data.remaining + ' question' + (data.remaining === 1 ? '' : 's') + ' left today. Page text sharing is off unless you opt in below.';
                }
            }
        } catch (error) {
            responseMessage.textContent = error.message;
        } finally {
            sendButton.disabled = false;
            input.focus();
            messages.scrollTop = messages.scrollHeight;
        }
    }

    toggle.addEventListener('click', openChat);
    closeButton.addEventListener('click', closeChat);
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        sendMessage();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && panel.classList.contains('comfsci-chat-active')) {
            closeChat();
        }
    });
});
