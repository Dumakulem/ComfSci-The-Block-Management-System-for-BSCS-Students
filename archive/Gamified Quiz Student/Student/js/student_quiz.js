
document.addEventListener('DOMContentLoaded', () => {

    // ==========================================
    // SHARED QUIZ ELEMENTS
    // ==========================================

    let currentIndex = 0;

    const slides = document.querySelectorAll('.question-slide');
    const totalQuestions = slides.length;

    const counter = document.getElementById('question-counter');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const form = document.getElementById('quiz-form');
    const quizPanel = document.getElementById('quiz-panel');

    const timerDisplay = document.getElementById('question-timer-value');
    const timerFill = document.getElementById('question-timer-fill');
    const timerBox = document.getElementById('question-timer');

    const questionTimeLimit = quizPanel
        ? Math.max(0, parseInt(quizPanel.dataset.questionTime || '0', 10))
        : 0;

    const attemptToken =
        form?.querySelector('[name="attempt_token"]')?.value || '';

    let questionTimerInterval = null;
    let isSubmittingQuiz = false;
    let isPreparingFluff = false;
    let caramelActivating = false;

    let caramelUsed = quizPanel?.dataset.caramelUsed === '1';

    function getSkillCsrf(skill) {
        return quizPanel?.dataset[`${skill}Csrf`] || '';
    }

    async function postAction(data) {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: data,
            headers: {
                Accept: 'application/json'
            }
        });

        const responseText = await response.text();

        let result;

        try {
            result = JSON.parse(responseText);
        } catch {
            console.error('Server response:', responseText);
            throw new Error(
                'The server returned an invalid response. Check for a PHP error.'
            );
        }

        if (!response.ok || !result.success) {
            throw new Error(
                result.message || 'The action could not be completed.'
            );
        }

        return result;
    }

    function stopQuestionTimer() {
        if (questionTimerInterval !== null) {
            clearInterval(questionTimerInterval);
            questionTimerInterval = null;
        }
    }

    function submitQuiz() {
        if (!form || isSubmittingQuiz) return;

        isSubmittingQuiz = true;
        stopQuestionTimer();

        if (nextBtn) {
            nextBtn.disabled = true;
            nextBtn.textContent = 'Submitting...';
        }

        if (prevBtn) {
            prevBtn.disabled = true;
        }

        form.submit();
    }

    // ==========================================
    // CARAMEL - CUSTOM TIME RESCUE POPUP
    // ==========================================

    let caramelPopup = null;
    let caramelPopupTimeout = null;

    function createCaramelPopup() {
        if (caramelPopup) return caramelPopup;

        const style = document.createElement('style');

        style.textContent = `
            #caramel-rescue-popup {
                position: fixed;
                inset: 0;
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                background: rgba(20, 15, 12, 0.55);
                opacity: 0;
                visibility: hidden;
                transition: opacity .25s ease, visibility .25s ease;
            }

            #caramel-rescue-popup.active {
                opacity: 1;
                visibility: visible;
            }

            #caramel-rescue-popup .caramel-card {
                width: min(360px, 100%);
                padding: 28px 24px;
                border-radius: 24px;
                background: #fff8ef;
                color: #30251f;
                text-align: center;
                box-shadow: 0 18px 55px rgba(0,0,0,.25);
                transform: translateY(25px) scale(.9);
                transition: transform .35s cubic-bezier(.2,.8,.2,1);
                border: 2px solid #f3c99f;
            }

            #caramel-rescue-popup.active .caramel-card {
                transform: translateY(0) scale(1);
            }

            #caramel-rescue-popup .caramel-character {
                width: 112px;
                height: 112px;
                object-fit: contain;
                margin: 0 auto 12px;
                display: block;
                animation: caramelBounce .8s ease-in-out infinite alternate;
            }

            #caramel-rescue-popup .caramel-title {
                margin: 0 0 8px;
                font-size: 23px;
                font-weight: 800;
                color: #a95c25;
            }

            #caramel-rescue-popup .caramel-message {
                margin: 0;
                font-size: 15px;
                line-height: 1.5;
                color: #665348;
            }

            #caramel-rescue-popup .caramel-time {
                display: inline-block;
                margin-top: 16px;
                padding: 9px 17px;
                border-radius: 30px;
                background: #f5dfc7;
                color: #8d4c20;
                font-size: 14px;
                font-weight: 800;
            }

            @keyframes caramelBounce {
                from {
                    transform: translateY(0) rotate(-4deg);
                }
                to {
                    transform: translateY(-10px) rotate(4deg);
                }
            }

            @media (prefers-reduced-motion: reduce) {
                #caramel-rescue-popup,
                #caramel-rescue-popup .caramel-card {
                    transition: none;
                }

                #caramel-rescue-popup .caramel-character {
                    animation: none;
                }
            }
        `;

        document.head.appendChild(style);

        caramelPopup = document.createElement('div');
        caramelPopup.id = 'caramel-rescue-popup';
        caramelPopup.setAttribute('role', 'status');
        caramelPopup.setAttribute('aria-live', 'polite');

        const card = document.createElement('div');
        card.className = 'caramel-card';

        const avatar = document.createElement('img');
        avatar.className = 'caramel-character';
        avatar.src = 'avatar/caramel.png';
        avatar.alt = 'Caramel avatar';

        avatar.addEventListener('error', () => {
            avatar.style.display = 'none';
        });

        const title = document.createElement('h2');
        title.className = 'caramel-title';
        title.textContent = 'Caramel to the Rescue!';

        const message = document.createElement('p');
        message.className = 'caramel-message';
        message.textContent =
            "Don't worry! I'll give you another chance. Keep going!";

        const time = document.createElement('div');
        time.className = 'caramel-time';
        time.id = 'caramel-rescue-time';

        card.append(avatar, title, message, time);
        caramelPopup.appendChild(card);
        document.body.appendChild(caramelPopup);

        return caramelPopup;
    }

    function showCaramelPopup(extraTime) {
        const popup = createCaramelPopup();
        const timeLabel = document.getElementById('caramel-rescue-time');

        if (timeLabel) {
            timeLabel.textContent = `+${extraTime} seconds added`;
        }

        popup.classList.add('active');

        if (caramelPopupTimeout) {
            clearTimeout(caramelPopupTimeout);
        }

        caramelPopupTimeout = setTimeout(() => {
            popup.classList.remove('active');
        }, 2400);
    }

    async function activateCaramelRescue() {
        if (
            caramelUsed ||
            caramelActivating ||
            questionTimeLimit <= 0 ||
            quizPanel?.dataset.lockedAvatar !== 'caramel.png'
        ) {
            return false;
        }

        caramelActivating = true;

        const data = new FormData();
        data.append('activate_time_rescue', '1');
        data.append('attempt_token', attemptToken);
        data.append('caramel_csrf', getSkillCsrf('caramel'));

        try {
            const result = await postAction(data);

            const extraTime = Math.max(
                0,
                parseInt(result.remaining || '0', 10)
            );

            if (extraTime <= 0) {
                return false;
            }

            caramelUsed = true;

            if (quizPanel) {
                quizPanel.dataset.caramelUsed = '1';
            }

            showCaramelPopup(extraTime);
            startQuestionTimer(extraTime, true);

            return true;

        } catch (error) {
            console.error('Caramel Time Rescue:', error);
            return false;

        } finally {
            caramelActivating = false;
        }
    }

    // ==========================================
    // QUESTION TIMER
    // ==========================================

    function startQuestionTimer(customDuration = null, isRescue = false) {
        stopQuestionTimer();

        if (!timerDisplay || questionTimeLimit <= 0 || totalQuestions === 0) {
            return;
        }

        let remaining = customDuration ?? questionTimeLimit;
        const timerDuration = customDuration ?? questionTimeLimit;

        if (timerFill) {
            timerFill.style.transition = 'none';
            timerFill.style.width = '100%';
            timerFill.offsetWidth;

            timerFill.style.transition =
                'width 1s linear, background .25s ease, box-shadow .25s ease';
        }

        if (timerBox) {
            timerBox.classList.remove('timer-warning', 'timer-danger');
        }

        function updateTimerDisplay() {
            timerDisplay.textContent = `${remaining}s`;

            timerDisplay.classList.toggle(
                'timer-warning',
                remaining <= 10
            );

            if (timerFill) {
                const percentage = Math.max(
                    0,
                    (remaining / timerDuration) * 100
                );

                timerFill.style.width = `${percentage}%`;

                if (remaining <= 5 && timerBox) {
                    timerBox.classList.add('timer-danger');
                    timerBox.classList.remove('timer-warning');
                } else if (remaining <= 10 && timerBox) {
                    timerBox.classList.add('timer-warning');
                }

                const track = timerFill.parentElement;

                if (track) {
                    track.setAttribute('aria-valuenow', String(remaining));
                }
            }
        }

        updateTimerDisplay();

        questionTimerInterval = setInterval(async () => {
            remaining--;
            updateTimerDisplay();

            if (remaining <= 0) {
                stopQuestionTimer();

                if (!caramelUsed && !isRescue) {
                    const rescued = await activateCaramelRescue();

                    if (rescued) return;
                }

                if (currentIndex >= totalQuestions - 1) {
                    await handleFinalSubmission();
                } else {
                    showQuestion(currentIndex + 1);
                }
            }
        }, 1000);
    }

    // ==========================================
    // QUESTION NAVIGATION
    // ==========================================

    function updateNavigation() {
        if (counter) {
            counter.innerText =
                `Question ${currentIndex + 1} of ${totalQuestions}`;
        }

        if (prevBtn) {
            prevBtn.disabled = currentIndex === 0;
            prevBtn.style.opacity = currentIndex === 0 ? '0.5' : '1';
            prevBtn.style.cursor =
                currentIndex === 0 ? 'not-allowed' : 'pointer';
        }

        if (nextBtn) {
            nextBtn.innerText =
                currentIndex === totalQuestions - 1
                    ? 'Submit Quiz'
                    : 'Next Question';
        }
    }

    function showQuestion(index) {
        if (
            index < 0 ||
            index >= totalQuestions ||
            isSubmittingQuiz
        ) {
            return;
        }

        slides.forEach((slide, i) => {
            slide.style.display = i === index ? 'block' : 'none';
        });

        currentIndex = index;

        updateNavigation();
        startQuestionTimer();
    }

    window.changeQuestion = function(direction) {
        if (isSubmittingQuiz) return;

        const nextIndex = currentIndex + direction;

        if (nextIndex < 0) return;

        if (nextIndex >= totalQuestions) {
            handleFinalSubmission();
            return;
        }

        showQuestion(nextIndex);
    };

    // ==========================================
    // MULTIPLE-CHOICE SELECTION
    // ==========================================

    window.selectOption = function(radio) {
        const label = radio.closest('label');
        const grid = label?.parentElement;

        if (!grid) return;

        grid.querySelectorAll('.opt-box').forEach(box => {
            box.style.background = '#222';
            box.style.color = '#fff';
        });

        const selectedBox = radio.nextElementSibling;

        if (selectedBox) {
            selectedBox.style.background = 'var(--o)';
            selectedBox.style.color = '#fff';
        }
    };

    // ==========================================
    // FLUFF - THINK AGAIN
    // ==========================================

    const fluffModal = document.getElementById('fluff-modal');
    const fluffBoxes = document.querySelectorAll('.fluff-box');
    const fluffQuestionArea = document.getElementById('fluff-question-area');
    const fluffQuestionForm = document.getElementById('fluff-question-form');
    const fluffQuestionId = document.getElementById('fluff-question-id');
    const fluffStatus = document.getElementById('fluff-status');
    const fluffKeepAnswer = document.getElementById('fluff-keep-answer');
    const fluffConfirm = document.getElementById('fluff-confirm-answer');

    function getSubmittedAnswers() {
        const answers = {};

        if (!form) return answers;

        form.querySelectorAll('[name^="answer["]').forEach(input => {
            const match = input.name.match(/^answer\[(\d+)\]$/);

            if (!match) return;

            if (input.type === 'radio') {
                if (input.checked) {
                    answers[match[1]] = input.value;
                }
            } else if (input.type === 'checkbox') {
                if (!answers[match[1]]) {
                    answers[match[1]] = [];
                }

                if (input.checked) {
                    answers[match[1]].push(input.value);
                }
            } else {
                answers[match[1]] = input.value;
            }
        });

        return answers;
    }

    function closeFluffModal() {
        if (fluffModal) {
            fluffModal.hidden = true;
        }
    }

    // FIXED: Keep the original form and hidden input in the DOM.
    function showFluffQuestion(question) {
        if (
            !fluffQuestionArea ||
            !fluffQuestionForm ||
            !fluffQuestionId
        ) {
            throw new Error(
                'Fluff question interface is missing from the page.'
            );
        }

        if (!question || !question.id) {
            throw new Error('The selected Fluff question is invalid.');
        }

        // Update the existing heading without deleting the other elements.
        let heading = fluffQuestionArea.querySelector('h3');

        if (!heading) {
            heading = document.createElement('h3');
            fluffQuestionArea.prepend(heading);
        }

        heading.textContent = question.text || 'Review your answer';

        // Keep the hidden question ID attached to the page.
        fluffQuestionId.value = question.id;

        // Clear only the answer choices, not the entire question area.
        fluffQuestionForm.replaceChildren();

        const questionType = String(question.type || '').toLowerCase();

        const isMC =
            questionType.includes('choice') ||
            questionType.includes('multiple');

        let firstInput = null;

        if (isMC) {
            const options = Array.isArray(question.options)
                ? question.options
                : [];

            if (options.length === 0) {
                throw new Error('No answer choices were provided.');
            }

            options.forEach((option, index) => {
                const label = document.createElement('label');
                label.className = 'fluff-answer-option';

                const radio = document.createElement('input');

                radio.type = 'radio';
                radio.name = 'fluff_redemption_answer';
                radio.value = option;
                radio.id = `fluff-option-${index}`;
                radio.disabled = false;
                radio.checked = false;

                const text = document.createElement('span');
                text.textContent = option;

                label.append(radio, text);
                fluffQuestionForm.appendChild(label);

                if (!firstInput) {
                    firstInput = radio;
                }
            });

        } else {
            const input = document.createElement('input');

            input.type = 'text';
            input.name = 'fluff_redemption_answer';
            input.maxLength = 100;
            input.placeholder = 'Type your new answer...';
            input.className = 'fluff-text-answer';

            input.disabled = false;
            input.readOnly = false;

            input.removeAttribute('disabled');
            input.removeAttribute('readonly');

            input.style.pointerEvents = 'auto';
            input.style.userSelect = 'text';
            input.style.cursor = 'text';

            fluffQuestionForm.appendChild(input);

            firstInput = input;
        }

        // Show the existing question area and its answer form.
        fluffQuestionArea.hidden = false;
        fluffQuestionForm.hidden = false;

        if (fluffStatus) {
            fluffStatus.textContent =
                'You have one chance to change your answer.';
        }

        if (fluffKeepAnswer) {
            fluffKeepAnswer.hidden = false;
        }

        if (fluffConfirm) {
            fluffConfirm.hidden = false;
            fluffConfirm.disabled = false;
        }

        // Focus the new answer field after it becomes visible.
        requestAnimationFrame(() => {
            if (!firstInput || !firstInput.isConnected) return;

            firstInput.disabled = false;
            firstInput.focus();
        });
    }

    async function chooseFluffBox(boxNumber) {
        if (!fluffStatus || isSubmittingQuiz) return;

        fluffBoxes.forEach(box => {
            box.disabled = true;
        });

        fluffStatus.textContent = 'Opening your mystery box...';

        const data = new FormData();

        data.append('choose_fluff_box', '1');
        data.append('box_number', String(boxNumber));
        data.append('attempt_token', attemptToken);
        data.append('fluff_csrf', getSkillCsrf('fluff'));

        try {
            const result = await postAction(data);

            if (!result.question) {
                throw new Error('No question was returned by the server.');
            }

            fluffBoxes.forEach(box => {
                box.hidden = true;
            });

            showFluffQuestion(result.question);

        } catch (error) {
            console.error('Fluff Mystery Box:', error);

            fluffStatus.textContent = error.message;

            fluffBoxes.forEach(box => {
                box.disabled = false;
                box.hidden = false;
            });
        }
    }

    async function prepareFluff() {
        if (isPreparingFluff) return false;

        isPreparingFluff = true;

        const data = new FormData();

        data.append('prepare_fluff', '1');
        data.append('attempt_token', attemptToken);
        data.append('fluff_csrf', getSkillCsrf('fluff'));
        data.append(
            'answers_json',
            JSON.stringify(getSubmittedAnswers())
        );

        try {
            const result = await postAction(data);

            if (!result.hasIncorrect) {
                return false;
            }

            if (!fluffModal) {
                throw new Error('Fluff mystery box popup is missing.');
            }

            fluffModal.hidden = false;

            if (fluffStatus) {
                fluffStatus.textContent =
                    'Choose one mystery box to discover your question!';
            }

            fluffBoxes.forEach(box => {
                box.disabled = false;
                box.hidden = false;
            });

            if (fluffQuestionArea) {
                fluffQuestionArea.hidden = true;
            }

            if (fluffQuestionForm) {
                fluffQuestionForm.replaceChildren();
            }

            if (fluffKeepAnswer) {
                fluffKeepAnswer.hidden = true;
            }

            if (fluffConfirm) {
                fluffConfirm.hidden = true;
            }

            return true;

        } catch (error) {
            console.error('Fluff Think Again:', error);
            alert(error.message);
            return false;

        } finally {
            isPreparingFluff = false;
        }
    }

    // Mystery box selection.
    fluffBoxes.forEach(box => {
        box.addEventListener('click', event => {
            event.preventDefault();

            if (box.disabled || box.hidden) return;

            chooseFluffBox(
                parseInt(box.dataset.boxNumber || '0', 10)
            );
        });
    });

    // Final submission and automatic Fluff activation.
    async function handleFinalSubmission() {
        if (isSubmittingQuiz || !form) return;

        const isFluff =
            quizPanel?.dataset.lockedAvatar === 'fluff.png';

        if (isFluff && form.dataset.fluffChecked !== '1') {
            const hasIncorrect = await prepareFluff();

            if (hasIncorrect) return;

            form.dataset.fluffChecked = '1';
        }

        submitQuiz();
    }

    // Prevent the popup form from submitting separately.
    if (fluffQuestionForm) {
        fluffQuestionForm.addEventListener('submit', event => {
            event.preventDefault();
        });
    }

    // Keep the original answer.
    if (fluffKeepAnswer) {
        fluffKeepAnswer.addEventListener('click', event => {
            event.preventDefault();

            if (!form || isSubmittingQuiz) return;

            form.querySelectorAll(
                '[name="fluff_keep_original"]'
            ).forEach(input => input.remove());

            const hidden = document.createElement('input');

            hidden.type = 'hidden';
            hidden.name = 'fluff_keep_original';
            hidden.value = '1';

            form.appendChild(hidden);

            closeFluffModal();
            submitQuiz();
        });
    }

    // Confirm the changed answer.
    if (fluffConfirm && fluffQuestionForm && form) {
        fluffConfirm.addEventListener('click', event => {
            event.preventDefault();

            if (isSubmittingQuiz) return;

            const questionId = fluffQuestionId?.value;

            if (!questionId) {
                alert('Please select a question first.');
                return;
            }

            const selected = fluffQuestionForm.querySelector(
                'input[name="fluff_redemption_answer"]:checked'
            );

            const textInput = fluffQuestionForm.querySelector(
                'input[name="fluff_redemption_answer"]:not([type="radio"])'
            );

            let answer = '';

            if (selected) {
                answer = selected.value;
            } else if (textInput) {
                answer = textInput.value;
            }

            if (!String(answer).trim()) {
                alert('Please choose or enter an answer first.');
                textInput?.focus();
                return;
            }

            // Remove old redemption data to prevent duplicate values.
            form.querySelectorAll(
                '[name="fluff_redemption_question_id"], [name="fluff_redemption_answer"], [name="fluff_keep_original"]'
            ).forEach(input => input.remove());

            const idInput = document.createElement('input');

            idInput.type = 'hidden';
            idInput.name = 'fluff_redemption_question_id';
            idInput.value = questionId;

            const answerInput = document.createElement('input');

            answerInput.type = 'hidden';
            answerInput.name = 'fluff_redemption_answer';
            answerInput.value = answer;

            form.append(idInput, answerInput);

            closeFluffModal();
            submitQuiz();
        });
    }

    // ==========================================
    // TARO - OPTION ELIMINATOR
    // ==========================================

    document.querySelectorAll('.option-eliminator-btn').forEach(button => {
        button.addEventListener('click', async event => {
            event.preventDefault();

            if (button.disabled) return;

            const slide = button.closest('.question-slide');
            const questionId = button.dataset.questionId;

            if (!slide || !questionId) return;

            button.disabled = true;
            button.textContent = 'Eliminating...';

            const data = new FormData();

            data.append('activate_option_eliminator', '1');
            data.append('question_id', questionId);
            data.append('attempt_token', attemptToken);
            data.append('taro_csrf', getSkillCsrf('taro'));

            try {
                const result = await postAction(data);
                const eliminated = result.eliminatedOptions || [];

                slide.querySelectorAll('input[type="radio"]').forEach(radio => {
                    if (eliminated.includes(radio.value)) {
                        const label = radio.closest('label');

                        if (label) {
                            label.hidden = true;
                        }

                        if (radio.checked) {
                            radio.checked = false;
                        }
                    }
                });

                button.textContent = 'Option Eliminator Used';
                button.dataset.used = '1';

            } catch (error) {
                console.error('Taro Option Eliminator:', error);
                alert(error.message);

                button.disabled = false;
                button.textContent = 'Use Option Eliminator';
            }
        });
    });

    // ==========================================
    // MOCHI - MEMORY NOTE COUNTER
    // ==========================================

    const note = document.getElementById('memory-note');
    const noteCounter = document.getElementById('memory-note-counter');

    if (note && noteCounter) {
        function updateNoteCounter() {
            noteCounter.textContent =
                `${Array.from(note.value).length} / 100 characters`;
        }

        note.addEventListener('input', updateNoteCounter);
        updateNoteCounter();
    }

    // ==========================================
    // MATCHA - FOCUS MODE
    // ==========================================

    const focusButton = document.getElementById('focus-mode-btn');

    if (focusButton && quizPanel) {
        let focusInterval = null;

        function startFocusMode(seconds) {
            if (focusInterval) {
                clearInterval(focusInterval);
            }

            let remaining = seconds;

            quizPanel.classList.add('focus-mode-active');
            focusButton.disabled = true;

            function updateButton() {
                focusButton.textContent =
                    `Focus Mode Active (${remaining}s)`;
            }

            updateButton();

            focusInterval = setInterval(() => {
                remaining--;

                if (remaining > 0) {
                    updateButton();
                } else {
                    clearInterval(focusInterval);
                    focusInterval = null;

                    quizPanel.classList.remove('focus-mode-active');
                    focusButton.textContent = 'Focus Mode Used';
                    focusButton.disabled = true;
                }
            }, 1000);
        }

        const focusActive = quizPanel.dataset.focusActive === '1';

        const focusRemaining = parseInt(
            quizPanel.dataset.focusRemaining || '0',
            10
        );

        const alreadyUsed = focusButton.dataset.used === '1';

        if (focusActive && focusRemaining > 0) {
            startFocusMode(focusRemaining);
        } else if (alreadyUsed) {
            focusButton.textContent = 'Focus Mode Used';
            focusButton.disabled = true;
        }

        focusButton.addEventListener('click', async event => {
            event.preventDefault();

            if (focusButton.disabled) return;

            focusButton.disabled = true;
            focusButton.textContent = 'Activating Focus Mode...';

            const data = new FormData();

            data.append('activate_focus_mode', '1');
            data.append('attempt_token', attemptToken);
            data.append(
                'focus_mode_csrf',
                focusButton.dataset.focusCsrf
            );

            try {
                const result = await postAction(data);

                focusButton.dataset.used = '1';
                startFocusMode(result.remaining || 30);

            } catch (error) {
                console.error('Focus Mode:', error);
                alert(error.message);

                focusButton.textContent = 'Activate Focus Mode';
                focusButton.disabled = false;
            }
        });
    }

    // ==========================================
    // AVATAR SELECTION
    // ==========================================

    const launcher = document.getElementById('avatar-launcher');
    const popup = document.getElementById('avatar-popup');
    const closeBtn = document.getElementById('avatar-close');

    const avatarChoices = document.querySelectorAll('.avatar-choice');

    if (launcher && popup) {
        const csrfToken = launcher.dataset.avatarCsrf;

        launcher.addEventListener('click', event => {
            event.preventDefault();

            popup.hidden = !popup.hidden;

            launcher.setAttribute(
                'aria-expanded',
                popup.hidden ? 'false' : 'true'
            );
        });

        closeBtn?.addEventListener('click', event => {
            event.preventDefault();

            popup.hidden = true;
            launcher.setAttribute('aria-expanded', 'false');
        });

        avatarChoices.forEach(choice => {
            choice.addEventListener('click', async event => {
                event.preventDefault();

                const avatar = choice.dataset.avatar;

                if (!avatar || !csrfToken || choice.disabled) return;

                choice.disabled = true;

                const data = new FormData();

                data.append('save_avatar', '1');
                data.append('avatar', avatar);
                data.append('avatar_csrf', csrfToken);

                try {
                    await postAction(data);
                    window.location.reload();

                } catch (error) {
                    console.error('Avatar Selection:', error);
                    alert(error.message);
                    choice.disabled = false;
                }
            });
        });

        document.addEventListener('click', event => {
            if (
                !popup.hidden &&
                !popup.contains(event.target) &&
                !launcher.contains(event.target)
            ) {
                popup.hidden = true;
                launcher.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // ==========================================
    // INITIALIZE
    // ==========================================

    if (slides.length > 0) {
        updateNavigation();
        startQuestionTimer();
    }

});
