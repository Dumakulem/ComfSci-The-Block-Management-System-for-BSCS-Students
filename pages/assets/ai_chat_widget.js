document.addEventListener('DOMContentLoaded', function () {
    const chatContainer = document.getElementById('chat-container');
    const chatToggle = document.getElementById('chat-toggle');
    const closeChat = document.getElementById('close-chat');
    const userMessage = document.getElementById('user-message');
    const sendButton = document.getElementById('send-button');
    const chatMessages = document.getElementById('chat-messages');

    if (!chatContainer || !chatToggle || !closeChat || !userMessage || !sendButton || !chatMessages) {
        return;
    }

    const apiURL = chatContainer.dataset.apiUrl;
    const csrfToken = chatContainer.dataset.csrfToken || '';
    const conversationHistory = [];

    function openChat() {
        chatContainer.classList.add('active');
        chatContainer.setAttribute('aria-hidden', 'false');
        chatToggle.setAttribute('aria-expanded', 'true');
        chatToggle.hidden = true;
        userMessage.focus();
    }

    function closeChatPanel() {
        chatContainer.classList.remove('active');
        chatContainer.setAttribute('aria-hidden', 'true');
        chatToggle.setAttribute('aria-expanded', 'false');
        chatToggle.hidden = false;
        chatToggle.focus();
    }

    function addMessage(message, sender) {
        const messageElement = document.createElement('div');
        messageElement.classList.add('message', sender);
        messageElement.textContent = message;
        chatMessages.appendChild(messageElement);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        return messageElement;
    }

    function getPageContext() {
        const skipTags = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT', 'LINK']);
        const ignore = '#chat-toggle, #chat-container, [data-chatbot-ignore]';

        const root = document.querySelector('[data-chat-context]')
                  || document.querySelector('main')
                  || document.body;

        let text;
        if (root === document.body) {
            text = Array.from(document.body.children)
                .filter(function (element) {
                    return !skipTags.has(element.tagName) && !element.matches(ignore);
                })
                .map(function (element) {
                    return element.innerText || '';
                })
                .join('\n');
        } else {
            text = root.innerText || '';
        }

        text = text.replace(/[ \t]+/g, ' ').replace(/\n{3,}/g, '\n\n').trim();
        const limit = 12000;

        return {
            title: document.title,
            url: location.pathname + location.search,
            text: text.slice(0, limit),
            truncated: text.length > limit
        };
    }

    function renderBotReply(element, reply) {
        if (typeof marked !== 'undefined' && typeof DOMPurify !== 'undefined') {
            element.innerHTML = DOMPurify.sanitize(marked.parse(String(reply || '')));
        } else {
            element.textContent = reply;
        }
    }

    async function sendMessage() {
        const message = userMessage.value.trim();
        if (!message || sendButton.disabled) {
            return;
        }

        addMessage(message, 'user');
        userMessage.value = '';
        sendButton.disabled = true;

        const loadingMessage = addMessage('Thinking...', 'bot');

        try {
            const response = await fetch(apiURL, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    message: message,
                    chatHistory: conversationHistory.slice(-20),
                    pageContext: getPageContext(),
                    sharePageText: true
                })
            });

            const data = await response.json().catch(function () {
                return {};
            });

            if (!response.ok) {
                throw new Error(data.error || 'Request failed');
            }

            renderBotReply(loadingMessage, data.reply);
            conversationHistory.push(
                { role: 'user', content: message },
                { role: 'assistant', content: data.reply }
            );
        } catch (error) {
            loadingMessage.textContent = 'Error: ' + error.message;
        }

        sendButton.disabled = false;
        userMessage.focus();
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    chatToggle.addEventListener('click', openChat);
    closeChat.addEventListener('click', closeChatPanel);
    sendButton.addEventListener('click', sendMessage);

    userMessage.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            sendMessage();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && chatContainer.classList.contains('active')) {
            closeChatPanel();
        }
    });
});
