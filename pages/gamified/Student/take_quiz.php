
<?php
// Handles student quiz attempts, interactive game features, and answer collection.
require_once __DIR__ . '/includes/init.php';

// ==========================================
// CURRENT STUDENT
// ==========================================

$stmt_student = $pdo->prepare("SELECT * FROM students WHERE studId = ? LIMIT 1");
$stmt_student->execute([(int) $_SESSION['user_id']]);
$current_student = $stmt_student->fetch();

if (!$current_student) {
    die("No student found in the database.");
}

$userId = (int)$current_student['studId'];

// ==========================================
// AVAILABLE AVATARS
// ==========================================

$avatars = [
    'caramel.png' => 'Caramel',
    'cookie.png'  => 'Cookie',
    'fluff.png'   => 'Fluff',
    'matcha.png'  => 'Matcha',
    'mochi.png'   => 'Mochi',
    'taro.png'    => 'Taro'
];

// ==========================================
// CSRF TOKENS
// ==========================================

$csrfNames = [
    'avatar_csrf',
    'memory_note_csrf',
    'focus_mode_csrf',
    'fluff_csrf',
    'caramel_csrf',
    'taro_csrf'
];

foreach ($csrfNames as $csrfName) {
    if (empty($_SESSION[$csrfName])) {
        $_SESSION[$csrfName] = bin2hex(random_bytes(32));
    }
}

// ==========================================
// GET QUIZ
// ==========================================

$quizId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($quizId <= 0) {
    header("Location: student_dashboard.php");
    exit;
}

$stmt_quiz = $pdo->prepare("SELECT * FROM quiz WHERE quizId = ?");
$stmt_quiz->execute([$quizId]);
$quiz = $stmt_quiz->fetch();

if (!$quiz) {
    die("Quiz not found.");
}

// ==========================================
// SESSION ARRAYS
// ==========================================

$sessionArrays = [
    'quiz_attempt_tokens',
    'quiz_attempt_started',
    'quiz_attempt_started_at',
    'quiz_attempt_avatar',
    'focus_mode_used',
    'focus_mode_until',

    'caramel_rescue_used',

    'taro_eliminator_used',
    'taro_eliminated_options',

    'fluff_original_answers',
    'fluff_eligible_questions',
    'fluff_selected_question',
    'fluff_redemption_used'
];

foreach ($sessionArrays as $sessionArray) {
    if (!isset($_SESSION[$sessionArray]) || !is_array($_SESSION[$sessionArray])) {
        $_SESSION[$sessionArray] = [];
    }
}

// ==========================================
// ATTEMPT TOKEN
// ==========================================

if (empty($_SESSION['quiz_attempt_tokens'][$quizId])) {
    $_SESSION['quiz_attempt_tokens'][$quizId] = bin2hex(random_bytes(32));
}

$attemptToken = $_SESSION['quiz_attempt_tokens'][$quizId];
$isStarted = !empty($_SESSION['quiz_attempt_started'][$quizId]);

// ==========================================
// CURRENT AVATAR
// ==========================================

$currentAvatar = $current_student['quiz_avatar'] ?? 'mochi.png';

if (!array_key_exists($currentAvatar, $avatars)) {
    $currentAvatar = 'mochi.png';
}

if ($isStarted) {
    $lockedAvatar = $_SESSION['quiz_attempt_avatar'][$quizId] ?? '';

    if (!array_key_exists($lockedAvatar, $avatars)) {
        $lockedAvatar = $currentAvatar;
        $_SESSION['quiz_attempt_avatar'][$quizId] = $lockedAvatar;
    }

    $currentAvatar = $lockedAvatar;
}

$avatarPath = 'avatar/';

// ==========================================
// HELPER: JSON RESPONSE
// ==========================================

function quiz_json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// ==========================================
// HELPER: CHECK AJAX ATTEMPT
// ==========================================

function quiz_check_attempt(
    string $attemptToken,
    string $postedAttemptToken,
    bool $isStarted
): bool {
    return $isStarted
        && $attemptToken !== ''
        && $postedAttemptToken !== ''
        && hash_equals($attemptToken, $postedAttemptToken);
}

// ==========================================
// HELPER: CHECK CSRF
// ==========================================

function quiz_check_csrf(string $sessionName, string $postedToken): bool
{
    return !empty($_SESSION[$sessionName])
        && $postedToken !== ''
        && hash_equals($_SESSION[$sessionName], $postedToken);
}

// ==========================================
// HELPER: GET QUESTION
// ==========================================

function quiz_get_question(PDO $pdo, int $quizId, int $questionId): ?array
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM question
        WHERE questionId = ?
          AND quizId = ?
        LIMIT 1
    ");

    $stmt->execute([$questionId, $quizId]);

    $question = $stmt->fetch();

    return $question ?: null;
}

// ==========================================
// HELPER: ANSWER COMPARISON
// ==========================================

function quiz_answers_match($submitted, $correct, bool $isMultipleChoice): bool
{
    if ($submitted === null || is_array($submitted)) {
        return false;
    }

    $submitted = trim((string)$submitted);
    $correct = trim((string)$correct);

    if ($isMultipleChoice) {
        return $submitted === $correct;
    }

    return mb_strtolower($submitted, 'UTF-8')
        === mb_strtolower($correct, 'UTF-8');
}

function quizIsMultipleChoice(string $questionType): bool
{
    $questionType = strtolower($questionType);

    return str_contains($questionType, 'choice')
        || str_contains($questionType, 'multiple');
}

function quizIsTrueFalse(string $questionType): bool
{
    $questionType = strtolower(trim($questionType));

    return in_array($questionType, ['true/false', 'true false', 'truefalse', 'tf'], true);
}

function quizIsSelectQuestion(string $questionType): bool
{
    return quizIsMultipleChoice($questionType)
        || quizIsTrueFalse($questionType);
}

// ==========================================
// HANDLE AVATAR SAVING
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_avatar'])) {

    if (!quiz_check_csrf('avatar_csrf', $_POST['avatar_csrf'] ?? '')) {
        quiz_json_response([
            'success' => false,
            'message' => 'Invalid security token.'
        ], 403);
    }

    if ($isStarted) {
        quiz_json_response([
            'success' => false,
            'message' => 'Your avatar is locked while a quiz is in progress.'
        ], 403);
    }

    $selectedAvatar = $_POST['avatar'] ?? '';

    if (!array_key_exists($selectedAvatar, $avatars)) {
        quiz_json_response([
            'success' => false,
            'message' => 'Invalid avatar selected.'
        ], 400);
    }

    $stmt_avatar = $pdo->prepare("
        UPDATE students
        SET quiz_avatar = ?
        WHERE studId = ?
    ");

    $stmt_avatar->execute([
        $selectedAvatar,
        $userId
    ]);

    quiz_json_response([
        'success' => true,
        'avatar' => $selectedAvatar,
        'message' => 'Avatar saved successfully.'
    ]);
}

// ==========================================
// HANDLE MATCHA - FOCUS MODE
// ==========================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['activate_focus_mode'])
) {

    if (!quiz_check_csrf('focus_mode_csrf', $_POST['focus_mode_csrf'] ?? '')) {
        quiz_json_response([
            'success' => false,
            'message' => 'Invalid security token. Reload the page and try again.'
        ], 403);
    }

    if (!quiz_check_attempt(
        $attemptToken,
        $_POST['attempt_token'] ?? '',
        $isStarted
    )) {
        quiz_json_response([
            'success' => false,
            'message' => 'Your quiz attempt is not valid.'
        ], 403);
    }

    $lockedAvatar = $_SESSION['quiz_attempt_avatar'][$quizId] ?? '';

    if ($lockedAvatar !== 'matcha.png') {
        quiz_json_response([
            'success' => false,
            'message' => 'Focus Mode is only available when Matcha was selected.'
        ], 403);
    }

    if (!empty($_SESSION['focus_mode_used'][$attemptToken])) {
        quiz_json_response([
            'success' => false,
            'message' => 'You have already used Focus Mode for this quiz.'
        ], 409);
    }

    $_SESSION['focus_mode_used'][$attemptToken] = true;
    $_SESSION['focus_mode_until'][$attemptToken] = time() + 30;

    quiz_json_response([
        'success' => true,
        'remaining' => 30,
        'message' => 'Focus Mode activated!'
    ]);
}

// ==========================================
// HANDLE CARAMEL - TIME RESCUE
// ==========================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['activate_time_rescue'])
) {

    if (!quiz_check_csrf('caramel_csrf', $_POST['caramel_csrf'] ?? '')) {
        quiz_json_response([
            'success' => false,
            'message' => 'Invalid security token.'
        ], 403);
    }

    if (!quiz_check_attempt(
        $attemptToken,
        $_POST['attempt_token'] ?? '',
        $isStarted
    )) {
        quiz_json_response([
            'success' => false,
            'message' => 'Your quiz attempt is not valid.'
        ], 403);
    }

    $lockedAvatar = $_SESSION['quiz_attempt_avatar'][$quizId] ?? '';

    if ($lockedAvatar !== 'caramel.png') {
        quiz_json_response([
            'success' => false,
            'message' => 'Time Rescue is only available to Caramel.'
        ], 403);
    }

    $questionTimeLimit = max(0, (int)($quiz['timer'] ?? 0));

    if ($questionTimeLimit <= 0) {
        quiz_json_response([
            'success' => false,
            'message' => 'Time Rescue is unavailable because this quiz has no timer.'
        ], 400);
    }

    if (!empty($_SESSION['caramel_rescue_used'][$attemptToken])) {
        quiz_json_response([
            'success' => false,
            'message' => 'Caramel has already used Time Rescue.'
        ], 409);
    }

    $_SESSION['caramel_rescue_used'][$attemptToken] = true;

    quiz_json_response([
        'success' => true,
        'remaining' => $questionTimeLimit,
        'message' => 'Caramel Time Rescue activated!'
    ]);
}

// ==========================================
// HANDLE TARO - OPTION ELIMINATOR
// ==========================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['activate_option_eliminator'])
) {

    if (!quiz_check_csrf('taro_csrf', $_POST['taro_csrf'] ?? '')) {
        quiz_json_response([
            'success' => false,
            'message' => 'Invalid security token.'
        ], 403);
    }

    if (!quiz_check_attempt(
        $attemptToken,
        $_POST['attempt_token'] ?? '',
        $isStarted
    )) {
        quiz_json_response([
            'success' => false,
            'message' => 'Your quiz attempt is not valid.'
        ], 403);
    }

    $lockedAvatar = $_SESSION['quiz_attempt_avatar'][$quizId] ?? '';

    if ($lockedAvatar !== 'taro.png') {
        quiz_json_response([
            'success' => false,
            'message' => 'Option Eliminator is only available to Taro.'
        ], 403);
    }

    if (!empty($_SESSION['taro_eliminator_used'][$attemptToken])) {
        quiz_json_response([
            'success' => false,
            'message' => 'You have already used Option Eliminator.'
        ], 409);
    }

    $questionId = (int)($_POST['question_id'] ?? 0);
    $question = quiz_get_question($pdo, $quizId, $questionId);

    if (!$question) {
        quiz_json_response([
            'success' => false,
            'message' => 'Question not found.'
        ], 404);
    }

    $questionType = strtolower($question['type'] ?? '');

    $isMultipleChoice = quizIsMultipleChoice($questionType);

    if (!$isMultipleChoice) {
        quiz_json_response([
            'success' => false,
            'message' => 'Option Eliminator is only available for multiple-choice questions.'
        ], 400);
    }

    $options = json_decode($question['choices'] ?? '', true);

    if (!is_array($options)) {
        $options = [];
    }

    $correctAnswer = trim((string)($question['correct_answer'] ?? ''));

    $wrongOptions = [];

    foreach ($options as $option) {
        if (!is_string($option) && !is_numeric($option)) {
            continue;
        }

        $option = (string)$option;

        if ($option !== $correctAnswer) {
            $wrongOptions[] = $option;
        }
    }

    $wrongOptions = array_values(array_unique($wrongOptions));

    if (count($wrongOptions) < 2) {
        quiz_json_response([
            'success' => false,
            'message' => 'This question does not have enough incorrect options to eliminate.'
        ], 400);
    }

    shuffle($wrongOptions);

    $eliminatedOptions = array_slice($wrongOptions, 0, 2);

    $_SESSION['taro_eliminator_used'][$attemptToken] = true;

    $_SESSION['taro_eliminated_options'][$attemptToken] = [
        $questionId => $eliminatedOptions
    ];

    quiz_json_response([
        'success' => true,
        'eliminatedOptions' => $eliminatedOptions,
        'message' => 'Two incorrect options have been eliminated.'
    ]);
}

// ==========================================
// HANDLE FLUFF - PREPARE THINK AGAIN
// ==========================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['prepare_fluff'])
) {

    if (!quiz_check_csrf('fluff_csrf', $_POST['fluff_csrf'] ?? '')) {
        quiz_json_response([
            'success' => false,
            'message' => 'Invalid security token.'
        ], 403);
    }

    if (!quiz_check_attempt(
        $attemptToken,
        $_POST['attempt_token'] ?? '',
        $isStarted
    )) {
        quiz_json_response([
            'success' => false,
            'message' => 'Your quiz attempt is not valid.'
        ], 403);
    }

    $lockedAvatar = $_SESSION['quiz_attempt_avatar'][$quizId] ?? '';

    if ($lockedAvatar !== 'fluff.png') {
        quiz_json_response([
            'success' => false,
            'message' => 'Think Again is only available to Fluff.'
        ], 403);
    }

    if (!empty($_SESSION['fluff_redemption_used'][$attemptToken])) {
        quiz_json_response([
            'success' => false,
            'message' => 'Think Again has already been used.'
        ], 409);
    }

    if (isset($_SESSION['fluff_selected_question'][$attemptToken])) {
        quiz_json_response([
            'success' => false,
            'message' => 'You have already opened a mystery box.'
        ], 409);
    }

    $submittedAnswers = json_decode(
        $_POST['answers_json'] ?? '{}',
        true
    );

    if (!is_array($submittedAnswers)) {
        quiz_json_response([
            'success' => false,
            'message' => 'Invalid submitted answers.'
        ], 400);
    }

    $stmt_all_questions = $pdo->prepare("
        SELECT *
        FROM question
        WHERE quizId = ?
        ORDER BY questionId ASC
    ");

    $stmt_all_questions->execute([$quizId]);
    $allQuestions = $stmt_all_questions->fetchAll();

    $incorrectQuestionIds = [];
    $originalAnswers = [];

    foreach ($allQuestions as $question) {

        $questionId = (int)$question['questionId'];
        $answer = $submittedAnswers[(string)$questionId] ?? null;

        if (is_array($answer)) {
            $answer = null;
        }

        $originalAnswers[$questionId] = $answer;

        $questionType = strtolower($question['type'] ?? '');

        $isMultipleChoice = quizIsSelectQuestion($questionType);

        $correctAnswer = $question['correct_answer'] ?? '';

        if (!quiz_answers_match($answer, $correctAnswer, $isMultipleChoice)) {
            $incorrectQuestionIds[] = $questionId;
        }
    }

    $_SESSION['fluff_original_answers'][$attemptToken] = $originalAnswers;
    $_SESSION['fluff_eligible_questions'][$attemptToken] = $incorrectQuestionIds;

    quiz_json_response([
        'success' => true,
        'hasIncorrect' => count($incorrectQuestionIds) > 0
    ]);
}

// ==========================================
// HANDLE FLUFF - CHOOSE MYSTERY BOX
// ==========================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['choose_fluff_box'])
) {

    if (!quiz_check_csrf('fluff_csrf', $_POST['fluff_csrf'] ?? '')) {
        quiz_json_response([
            'success' => false,
            'message' => 'Invalid security token.'
        ], 403);
    }

    if (!quiz_check_attempt(
        $attemptToken,
        $_POST['attempt_token'] ?? '',
        $isStarted
    )) {
        quiz_json_response([
            'success' => false,
            'message' => 'Your quiz attempt is not valid.'
        ], 403);
    }

    $lockedAvatar = $_SESSION['quiz_attempt_avatar'][$quizId] ?? '';

    if ($lockedAvatar !== 'fluff.png') {
        quiz_json_response([
            'success' => false,
            'message' => 'Think Again is only available to Fluff.'
        ], 403);
    }

    if (!empty($_SESSION['fluff_redemption_used'][$attemptToken])) {
        quiz_json_response([
            'success' => false,
            'message' => 'Think Again has already been used.'
        ], 409);
    }

    if (isset($_SESSION['fluff_selected_question'][$attemptToken])) {
        quiz_json_response([
            'success' => false,
            'message' => 'A mystery box has already been opened.'
        ], 409);
    }

    $boxNumber = (int)($_POST['box_number'] ?? 0);

    if ($boxNumber < 1 || $boxNumber > 3) {
        quiz_json_response([
            'success' => false,
            'message' => 'Invalid mystery box.'
        ], 400);
    }

    $eligibleQuestions =
        $_SESSION['fluff_eligible_questions'][$attemptToken] ?? [];

    if (empty($eligibleQuestions)) {
        quiz_json_response([
            'success' => false,
            'message' => 'There are no questions available for Think Again.'
        ], 400);
    }

    // The chosen box does not determine the question.
    // The server randomly selects one incorrect question.
    $selectedQuestionId = (int)$eligibleQuestions[
        array_rand($eligibleQuestions)
    ];

    $question = quiz_get_question(
        $pdo,
        $quizId,
        $selectedQuestionId
    );

    if (!$question) {
        quiz_json_response([
            'success' => false,
            'message' => 'The selected question could not be found.'
        ], 404);
    }

    $questionType = strtolower($question['type'] ?? '');

    $isMultipleChoice = quizIsSelectQuestion($questionType);

    $options = [];

    if ($isMultipleChoice) {
        $decodedOptions = json_decode($question['choices'] ?? '', true);

        if (is_array($decodedOptions)) {
            foreach ($decodedOptions as $option) {
                if (is_string($option) || is_numeric($option)) {
                    $options[] = (string)$option;
                }
            }
        }
    }

    $_SESSION['fluff_selected_question'][$attemptToken] = $selectedQuestionId;

    $originalAnswers =
        $_SESSION['fluff_original_answers'][$attemptToken] ?? [];

    $originalAnswer = $originalAnswers[$selectedQuestionId] ?? '';

    quiz_json_response([
        'success' => true,
        'question' => [
            'id' => $selectedQuestionId,
            'text' => $question['questionText'],
            'type' => $question['type'],
            'options' => $options,
            'originalAnswer' => $originalAnswer
        ]
    ]);
}

// ==========================================
// HANDLE START QUIZ
// ==========================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['start_quiz'])
) {

    $csrfToken = $_POST['memory_note_csrf'] ?? '';
    $postedAttemptToken = $_POST['attempt_token'] ?? '';

    if (!quiz_check_csrf('memory_note_csrf', $csrfToken)) {
        die("Invalid security token. Please reload the page.");
    }

    if (!hash_equals($attemptToken, $postedAttemptToken)) {
        die("Invalid quiz attempt. Please reload the page.");
    }

    if (!$isStarted) {

        $memoryNote = '';

        if ($currentAvatar === 'mochi.png') {
            $memoryNote = trim($_POST['memory_note'] ?? '');
            $memoryNote = mb_substr($memoryNote, 0, 100, 'UTF-8');
        }

        $stmt_note = $pdo->prepare("
            INSERT INTO quiz_memory_notes
            (userId, quizId, attempt_token, memory_note)
            VALUES (?, ?, ?, ?)
        ");

        $stmt_note->execute([
            $userId,
            $quizId,
            $attemptToken,
            $memoryNote
        ]);

        $_SESSION['quiz_attempt_avatar'][$quizId] = $currentAvatar;
        $_SESSION['quiz_attempt_started_at'][$quizId] = time();
        $_SESSION['quiz_attempt_started'][$quizId] = true;
    }

    header("Location: take_quiz.php?id=" . $quizId);
    exit;
}

// ==========================================
// CHECK ATTEMPT STATUS
// ==========================================

$isStarted = !empty($_SESSION['quiz_attempt_started'][$quizId]);

$savedNote = '';

if ($isStarted) {

    $stmt_note = $pdo->prepare("
        SELECT memory_note
        FROM quiz_memory_notes
        WHERE userId = ?
          AND quizId = ?
          AND attempt_token = ?
        LIMIT 1
    ");

    $stmt_note->execute([
        $userId,
        $quizId,
        $attemptToken
    ]);

    $noteRecord = $stmt_note->fetch();

    if ($noteRecord) {
        $savedNote = $noteRecord['memory_note'] ?? '';
    } else {
        unset($_SESSION['quiz_attempt_started'][$quizId]);
        unset($_SESSION['quiz_attempt_avatar'][$quizId]);
        unset($_SESSION['quiz_attempt_started_at'][$quizId]);

        $isStarted = false;

        $attemptToken = bin2hex(random_bytes(32));
        $_SESSION['quiz_attempt_tokens'][$quizId] = $attemptToken;
    }
}

// Reapply locked avatar.
if ($isStarted) {
    $currentAvatar = $_SESSION['quiz_attempt_avatar'][$quizId] ?? $currentAvatar;
}

// ==========================================
// SKILL STATUS
// ==========================================

$focusModeUsed = !empty($_SESSION['focus_mode_used'][$attemptToken]);

$focusModeRemaining = 0;

if ($focusModeUsed) {
    $focusModeRemaining = max(
        0,
        (int)($_SESSION['focus_mode_until'][$attemptToken] ?? 0) - time()
    );
}

$focusModeActive = $focusModeRemaining > 0;

$caramelRescueUsed =
    !empty($_SESSION['caramel_rescue_used'][$attemptToken]);

$taroEliminatorUsed =
    !empty($_SESSION['taro_eliminator_used'][$attemptToken]);

$taroEliminatedOptions =
    $_SESSION['taro_eliminated_options'][$attemptToken] ?? [];

// ==========================================
// GET QUESTIONS
// ==========================================

$stmt_questions = $pdo->prepare("
    SELECT *
    FROM question
    WHERE quizId = ?
    ORDER BY questionId ASC
");

$stmt_questions->execute([$quizId]);
$questions = $stmt_questions->fetchAll();

$questionTimeLimit = max(0, (int)($quiz['timer'] ?? 0));

$PAGE_TITLE = 'Gamified Quiz';

include __DIR__ . '/includes/header.php';
?>

<style>
.quiz-panel {
    position: relative;
    width: 100%;
    max-width: 100%;
    margin: 0;
    background: #fdfaf6;
    border-radius: 24px;
    padding: 35px 35px 100px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    box-sizing: border-box;
}

.memory-note-panel {
    background: #fff5e9;
    border: 1px solid #efd6bd;
    border-radius: 18px;
    padding: 24px;
    margin-bottom: 25px;
}

.memory-note-panel h3 {
    color: var(--ink);
    margin: 0 0 8px;
    font-size: 19px;
    font-weight: 800;
}

.memory-note-panel p {
    color: var(--muted);
    font-size: 14px;
    line-height: 1.5;
}

.memory-note-panel textarea {
    width: 100%;
    min-height: 130px;
    resize: vertical;
    box-sizing: border-box;
    border: 1px solid #dfcbb8;
    border-radius: 12px;
    padding: 15px;
    font-family: inherit;
    font-size: 14px;
    color: var(--ink);
    background: #fff;
    outline: none;
}

.memory-note-panel textarea:focus {
    border-color: var(--o);
}

.memory-note-panel textarea:disabled {
    background: #f4eee7;
    color: #555;
    cursor: not-allowed;
}

.memory-note-counter {
    display: block;
    text-align: right;
    margin-top: 7px;
    color: var(--muted);
    font-size: 12px;
    font-weight: 700;
}

.start-quiz-btn {
    display: block;
    width: 100%;
    border: none;
    border-radius: 14px;
    background: var(--o);
    color: #fff;
    padding: 16px;
    margin-top: 18px;
    font-size: 16px;
    font-weight: 800;
    cursor: pointer;
}

.start-quiz-btn:hover {
    opacity: .9;
}

/* QUESTION TIMER */

.question-timer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    background: #fff5e9;
    border: 1px solid #efd6bd;
    border-radius: 14px;
    padding: 13px 18px;
    margin: 0 0 22px;
    color: var(--ink);
    flex-wrap: wrap;
}

.question-timer-label {
    color: var(--muted);
    font-size: 13px;
    font-weight: 700;
}

.question-timer-value {
    color: var(--o);
    font-size: 20px;
    font-weight: 900;
    font-variant-numeric: tabular-nums;
}

.question-timer-value.timer-warning {
    color: #c0392b;
}

.question-timer-track {
    flex: 0 0 100%;
    height: 9px;
    overflow: hidden;
    border-radius: 999px;
    background: #ead8c7;
    box-shadow: inset 0 1px 2px rgba(70, 42, 20, .10);
}

.question-timer-fill {
    display: block;
    width: 100%;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #ff9b45 0%, #ff6a00 55%, #e85a00 100%);
    transform-origin: left center;
    transition: width 1s linear, background .25s ease, box-shadow .25s ease;
    box-shadow: 0 0 9px rgba(255, 106, 0, .25);
}

.question-timer.timer-warning .question-timer-fill {
    background: linear-gradient(90deg, #f5a16f 0%, #d94b36 100%);
    box-shadow: 0 0 10px rgba(192, 57, 43, .28);
}

.question-timer.timer-danger .question-timer-fill {
    background: #c0392b;
    box-shadow: 0 0 12px rgba(192, 57, 43, .4);
    animation: timer-pulse .65s ease-in-out infinite alternate;
}

@keyframes timer-pulse {
    from { opacity: .78; }
    to { opacity: 1; }
}

@media (prefers-reduced-motion: reduce) {
    .question-timer-fill {
        transition: none;
    }

    .question-timer.timer-danger .question-timer-fill {
        animation: none;
    }
}

/* MATCHA FOCUS MODE */

.focus-mode-btn {
    display: block;
    width: 100%;
    margin: 0 0 20px;
    padding: 14px 18px;
    border: 1px solid #b6cdb7;
    border-radius: 14px;
    background: #eaf3e9;
    color: #315b37;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    transition: .2s;
}

.focus-mode-btn:hover:not(:disabled) {
    background: #dcebdc;
}

.focus-mode-btn:disabled {
    opacity: .65;
    cursor: not-allowed;
}

.focus-mode-btn[hidden] {
    display: none !important;
}

/* TARO OPTION ELIMINATOR */

.option-eliminator-btn {
    display: block;
    width: 100%;
    margin: 0 0 18px;
    padding: 12px 16px;
    border: 1px solid #c9b6df;
    border-radius: 12px;
    background: #f1eafa;
    color: #62418a;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    transition: .2s;
}

.option-eliminator-btn:hover:not(:disabled) {
    background: #e7dcf5;
}

.option-eliminator-btn:disabled {
    opacity: .65;
    cursor: not-allowed;
}

.option-eliminator-btn[hidden] {
    display: none !important;
}

/* FLUFF MYSTERY BOX MODAL */

.fluff-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(25, 18, 13, .65);
}

.fluff-modal[hidden] {
    display: none !important;
}

.fluff-modal-card {
    width: 100%;
    max-width: 520px;
    max-height: 90vh;
    overflow-y: auto;
    box-sizing: border-box;
    padding: 28px;
    border-radius: 22px;
    background: #fdfaf6;
    box-shadow: 0 15px 45px rgba(0,0,0,.25);
    text-align: center;
}

.fluff-modal-card h2 {
    margin: 0 0 8px;
    color: var(--ink);
    font-size: 23px;
    font-weight: 900;
}

.fluff-modal-card p {
    color: var(--muted);
    line-height: 1.5;
    font-size: 14px;
}

.fluff-boxes {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin: 25px 0;
}

.fluff-box {
    min-height: 105px;
    border: 2px solid #e8c7a6;
    border-radius: 16px;
    background: #fff0df;
    color: #9a5722;
    font-size: 34px;
    cursor: pointer;
    transition: .2s;
}

.fluff-box:hover:not(:disabled) {
    transform: translateY(-4px);
    border-color: var(--o);
    background: #ffe4c8;
}

.fluff-box:disabled {
    cursor: default;
    opacity: .5;
}

.fluff-status {
    min-height: 22px;
    font-size: 13px;
    font-weight: 700;
    color: var(--muted);
}

.fluff-question-area {
    margin-top: 20px;
    padding: 20px;
    border: 1px solid #efd6bd;
    border-radius: 16px;
    background: #fff5e9;
    text-align: left;
}

.fluff-question-area[hidden] {
    display: none !important;
}

.fluff-question-area h3 {
    color: var(--ink);
    font-size: 17px;
    line-height: 1.5;
    margin: 0;
}

.fluff-question-form {
    display: grid;
    gap: 10px;
    margin-top: 18px;
}

.fluff-answer-option {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    border: 1px solid #dfcbb8;
    border-radius: 10px;
    background: #fff;
    cursor: pointer;
    color: var(--ink);
    font-size: 14px;
}

.fluff-answer-option input {
    accent-color: var(--o);
}

.fluff-text-answer {
    width: 100%;
    box-sizing: border-box;
    padding: 13px;
    border: 1px solid #dfcbb8;
    border-radius: 10px;
    background: #fff;
    color: var(--ink);
    font-size: 14px;
}

.fluff-modal-actions {
    display: grid;
    gap: 10px;
    margin-top: 18px;
}

.fluff-confirm-btn,
.fluff-keep-btn {
    width: 100%;
    border: 0;
    border-radius: 12px;
    padding: 13px 16px;
    font-weight: 800;
    cursor: pointer;
}

.fluff-confirm-btn {
    background: var(--o);
    color: #fff;
}

.fluff-keep-btn {
    background: #eee2d6;
    color: #684b35;
}

.fluff-keep-btn[hidden] {
    display: none !important;
}

/* FOCUS MODE APPEARANCE */

.quiz-panel.focus-mode-active {
    padding-top: 25px;
}

.quiz-panel.focus-mode-active .quiz-heading h2 {
    display: none;
}

.quiz-panel.focus-mode-active .quiz-heading {
    justify-content: flex-end !important;
}

.quiz-panel.focus-mode-active .memory-note-panel,
.quiz-panel.focus-mode-active .avatar-launcher,
.quiz-panel.focus-mode-active .avatar-popup {
    display: none !important;
}

.quiz-panel.focus-mode-active .question-slide > p:first-child {
    display: none;
}

.quiz-panel.focus-mode-active .question-slide > div:first-of-type {
    padding: 42px;
    margin-bottom: 30px;
}

.quiz-panel.focus-mode-active #question-container {
    max-width: 1100px;
    margin: 0 auto;
}

/* AVATAR */

.avatar-launcher {
    position: absolute;
    right: 25px;
    bottom: 20px;
    width: 62px;
    height: 62px;
    padding: 4px;
    border: 2px solid var(--o);
    border-radius: 50%;
    background: #fff;
    cursor: pointer;
    z-index: 20;
    box-shadow: 0 4px 12px rgba(0,0,0,.15);
}

.avatar-launcher img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}

.avatar-popup {
    position: absolute;
    right: 25px;
    bottom: 95px;
    width: min(340px, calc(100% - 50px));
    background: #fff;
    border: 1px solid #e2d8ce;
    border-radius: 20px;
    padding: 18px;
    box-shadow: 0 10px 30px rgba(0,0,0,.16);
    z-index: 30;
    box-sizing: border-box;
}

.avatar-popup[hidden] {
    display: none !important;
}

.avatar-popup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.avatar-popup-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 800;
    color: var(--ink);
}

.avatar-close {
    border: 0;
    background: #f4e8dc;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    cursor: pointer;
    font-size: 18px;
}

.avatar-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.avatar-choice {
    border: 2px solid transparent;
    background: #fcf5ee;
    border-radius: 14px;
    padding: 10px 5px;
    cursor: pointer;
    text-align: center;
    color: var(--ink);
}

.avatar-choice:hover,
.avatar-choice.selected {
    border-color: var(--o);
    background: #fff0e3;
}

.avatar-choice img {
    width: 70px;
    height: 70px;
    object-fit: contain;
    display: block;
    margin: 0 auto 6px;
}

.avatar-choice span {
    font-size: 12px;
    font-weight: 700;
}

@media(max-width: 560px) {
    .quiz-panel {
        padding: 18px 18px 100px;
    }

    .avatar-launcher {
        right: 16px;
        bottom: 16px;
    }

    .avatar-popup {
        right: 16px;
        bottom: 90px;
    }

    .quiz-panel.focus-mode-active {
        padding: 18px !important;
    }

    .quiz-panel.focus-mode-active .question-slide > div:first-of-type {
        padding: 22px;
    }

    .fluff-modal-card {
        padding: 20px;
    }

    .fluff-box {
        min-height: 80px;
    }
}
</style>

<section
    class="panel quiz-panel <?= $focusModeActive ? 'focus-mode-active' : '' ?>"
    id="quiz-panel"
    data-focus-active="<?= $focusModeActive ? '1' : '0' ?>"
    data-focus-remaining="<?= (int)$focusModeRemaining ?>"
    data-question-time="<?= (int)$questionTimeLimit ?>"
    data-locked-avatar="<?= h($currentAvatar) ?>"
    data-fluff-csrf="<?= h($_SESSION['fluff_csrf']) ?>"
    data-caramel-csrf="<?= h($_SESSION['caramel_csrf']) ?>"
    data-taro-csrf="<?= h($_SESSION['taro_csrf']) ?>"
    data-caramel-used="<?= $caramelRescueUsed ? '1' : '0' ?>"
>

    <div class="quiz-heading" style="border-bottom: 2px solid var(--o); padding-bottom: 14px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; gap: 12px;">

        <h2 style="font-size: 22px; font-weight: 800; color: var(--ink); margin: 0;">
            <?= h($quiz['title']) ?>
        </h2>

        <span id="question-counter" style="font-weight: 700; color: var(--muted); font-size: 14px; white-space: nowrap;">
            <?= $isStarted ? 'Question 1 of ' . count($questions) : 'Quiz Preparation' ?>
        </span>

    </div>

    <?php if ($isStarted && $questionTimeLimit > 0): ?>

        <div class="question-timer" id="question-timer" aria-live="polite">

            <span class="question-timer-label">
                Time for this question
            </span>

            <strong class="question-timer-value" id="question-timer-value">
                <?= (int)$questionTimeLimit ?>s
            </strong>

            <div
                class="question-timer-track"
                role="progressbar"
                aria-label="Time remaining for this question"
                aria-valuemin="0"
                aria-valuemax="<?= (int)$questionTimeLimit ?>"
                aria-valuenow="<?= (int)$questionTimeLimit ?>"
            >
                <span class="question-timer-fill" id="question-timer-fill"></span>
            </div>

        </div>

    <?php endif; ?>

    <?php if (empty($questions)): ?>

        <p class="note" style="text-align: center; padding: 40px;">
            No questions available for this quiz yet.
        </p>

        <div style="margin-top: 20px; text-align: center;">
            <a href="student_dashboard.php" class="btn k">Back to Dashboard</a>
        </div>

    <?php elseif (!$isStarted): ?>

        <div class="memory-note-panel">

            <?php if ($currentAvatar === 'mochi.png'): ?>

                <h3>Mochi's Memory Note</h3>

                <p>
                    Write down important reminders before starting the quiz.
                    Once you start, your note will become read-only.
                    You can write up to 100 characters, including spaces.
                </p>

            <?php else: ?>

                <h3>Quiz Preparation</h3>

                <p>
                    Get ready before starting your quiz.
                    Each companion has a different skill.
                </p>

                <?php if ($currentAvatar === 'fluff.png'): ?>
                    <p>
                        <strong>Fluff's Think Again:</strong>
                        Before submitting, you can choose one mystery box
                        to revisit one question you answered incorrectly.
                    </p>
                <?php elseif ($currentAvatar === 'caramel.png'): ?>
                    <p>
                        <strong>Caramel's Time Rescue:</strong>
                        Get one extra round of time on a question when its timer runs out.
                    </p>
                <?php elseif ($currentAvatar === 'taro.png'): ?>
                    <p>
                        <strong>Taro's Option Eliminator:</strong>
                        Remove two incorrect choices from one multiple-choice question.
                    </p>
                <?php endif; ?>

            <?php endif; ?>

            <form method="POST" action="take_quiz.php?id=<?= (int)$quizId ?>">

                <input type="hidden" name="start_quiz" value="1">

                <input
                    type="hidden"
                    name="memory_note_csrf"
                    value="<?= h($_SESSION['memory_note_csrf']) ?>"
                >

                <input
                    type="hidden"
                    name="attempt_token"
                    value="<?= h($attemptToken) ?>"
                >

                <?php if ($currentAvatar === 'mochi.png'): ?>

                    <textarea
                        id="memory-note"
                        name="memory_note"
                        maxlength="100"
                        placeholder="Write your reminders here..."
                    ></textarea>

                    <span class="memory-note-counter" id="memory-note-counter">
                        0 / 100 characters
                    </span>

                <?php endif; ?>

                <button type="submit" class="start-quiz-btn">
                    Start Quiz
                </button>

            </form>

        </div>

    <?php else: ?>

        <?php if ($currentAvatar === 'mochi.png' && $savedNote !== ''): ?>

            <div class="memory-note-panel">

                <h3>Mochi's Memory Note</h3>

                <p>
                    Your note is locked for this quiz attempt.
                    You can still read it, but you cannot edit it.
                </p>

                <textarea readonly disabled maxlength="100"><?= h($savedNote) ?></textarea>

                <span class="memory-note-counter">
                    <?= mb_strlen($savedNote, 'UTF-8') ?> / 100 characters
                </span>

            </div>

        <?php endif; ?>

        <?php if ($currentAvatar === 'matcha.png'): ?>

            <button
                type="button"
                class="focus-mode-btn"
                id="focus-mode-btn"
                data-attempt-token="<?= h($attemptToken) ?>"
                data-focus-csrf="<?= h($_SESSION['focus_mode_csrf']) ?>"
                data-used="<?= $focusModeUsed ? '1' : '0' ?>"
                <?= $focusModeUsed ? 'disabled' : '' ?>
            >
                <?= $focusModeUsed
                    ? ($focusModeActive ? 'Focus Mode Active' : 'Focus Mode Used')
                    : 'Activate Focus Mode' ?>
            </button>

        <?php endif; ?>

        <form id="quiz-form" action="submit_quiz.php" method="POST">

            <input type="hidden" name="quizId" value="<?= (int)$quizId ?>">

            <input
                type="hidden"
                name="attempt_token"
                value="<?= h($attemptToken) ?>"
            >

            <div id="question-container">

                <?php foreach ($questions as $index => $q):

                    $qNum = $index + 1;
                    $questionId = (int)$q['questionId'];
                    $qType = strtolower($q['type'] ?? 'multiple choice');

                    $isTrueFalse = quizIsTrueFalse($qType);
                    $isMultipleChoice = quizIsSelectQuestion($qType);
                    $canEliminateOptions = quizIsMultipleChoice($qType);

                    $options = $isTrueFalse
                        ? ['True', 'False']
                        : json_decode($q['choices'] ?? '', true);

                    if (!is_array($options)) {
                        $options = [];
                    }

                    $eliminatedForQuestion =
                        $taroEliminatedOptions[$questionId] ?? [];

                    $questionEliminatorUsed =
                        $taroEliminatorUsed &&
                        !empty($eliminatedForQuestion);
                ?>

                    <div
                        class="question-slide"
                        data-index="<?= $index ?>"
                        data-question-number="<?= $qNum ?>"
                        data-question-id="<?= $questionId ?>"
                        style="display: <?= $index === 0 ? 'block' : 'none' ?>;"
                    >

                        <p style="font-weight: 700; font-size: 13px; color: var(--muted); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                            Question <?= $qNum ?>:
                            <?= $isMultipleChoice
                                ? 'Select the correct answer.'
                                : 'Type the correct answer.' ?>
                        </p>

                        <div style="background: #f4e8dc; border-radius: 20px; padding: 32px; margin-bottom: 24px; border: 1px solid rgba(0,0,0,0.03);">

                            <p style="font-size: 20px; font-weight: 700; color: var(--ink); margin-bottom: 20px; line-height: 1.5;">
                                <?= h($q['questionText']) ?>
                            </p>

                            <div style="text-align: right; font-size: 13px; font-weight: 700; color: var(--muted);">
                                <?= (int)($q['points'] ?? 10) ?> points
                            </div>

                        </div>

                        <?php if ($currentAvatar === 'taro.png' && $canEliminateOptions): ?>

                            <button
                                type="button"
                                class="option-eliminator-btn"
                                data-question-id="<?= $questionId ?>"
                                <?= $questionEliminatorUsed ? 'disabled' : '' ?>
                            >
                                <?= $questionEliminatorUsed
                                    ? 'Option Eliminator Used'
                                    : 'Use Option Eliminator' ?>
                            </button>

                        <?php endif; ?>

                        <?php if ($isMultipleChoice): ?>

                            <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px;">

                                <?php foreach ($options as $opt):

                                    if (!is_string($opt) && !is_numeric($opt)) {
                                        continue;
                                    }

                                    $opt = (string)$opt;
                                    $isEliminated = in_array(
                                        $opt,
                                        $eliminatedForQuestion,
                                        true
                                    );
                                ?>

                                    <label
                                        style="cursor: <?= $isEliminated ? 'not-allowed' : 'pointer' ?>;"
                                        <?= $isEliminated ? 'hidden' : '' ?>
                                    >

                                        <input
                                            type="radio"
                                            name="answer[<?= $questionId ?>]"
                                            value="<?= h($opt) ?>"
                                            style="display: none;"
                                            onchange="selectOption(this)"
                                            <?= $isEliminated ? 'disabled' : '' ?>
                                        >

                                        <div class="opt-box" style="background: #222; color: #fff; padding: 20px 24px; border-radius: 14px; text-align: center; font-weight: 700; font-size: 16px; transition: 0.2s; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                                            <?= h($opt) ?>
                                        </div>

                                    </label>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <div style="background: #fff; border-radius: 16px; padding: 20px; margin-bottom: 24px; border: 1px solid #e2d8ce;">

                                <span style="font-size: 12px; color: var(--muted); display: block; margin-bottom: 8px; font-weight: 600;">
                                    Type your answer here...
                                </span>

                                <input
                                    type="text"
                                    name="answer[<?= $questionId ?>]"
                                    maxlength="100"
                                    placeholder="Type your answer here..."
                                    style="background: #fcf9f5; border-radius: 12px; padding: 16px 20px; font-size: 16px; width: 100%; border: 1px solid #dcd2c7; color: var(--ink); outline: none; box-sizing: border-box;"
                                >

                            </div>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-top: 30px; border-top: 1px solid #e2d8ce; padding-top: 20px;">

                <button
                    type="button"
                    id="prev-btn"
                    onclick="changeQuestion(-1)"
                    style="opacity: 0.5; cursor: not-allowed; background: #e2d8ce; color: #777; border-radius: 20px; padding: 12px 24px; font-weight: 700; border: none;"
                    disabled
                >
                    Previous Question
                </button>

                <button
                    type="button"
                    id="next-btn"
                    onclick="changeQuestion(1)"
                    style="background: var(--o); color: #fff; border-radius: 20px; padding: 12px 28px; font-weight: 700; border: none; cursor: pointer;"
                >
                    <?= count($questions) === 1 ? 'Submit Quiz' : 'Next Question' ?>
                </button>

            </div>

        </form>

        <?php if ($currentAvatar === 'fluff.png'): ?>

            <div
                class="fluff-modal"
                id="fluff-modal"
                hidden
                role="dialog"
                aria-modal="true"
                aria-labelledby="fluff-modal-title"
            >

                <div class="fluff-modal-card">

                    <h2 id="fluff-modal-title">
                        Fluff's Think Again!
                    </h2>

                    <p>
                        Before submitting, choose one mystery box.
                        You might get another chance to answer one question!
                    </p>

                    <div class="fluff-boxes">

                        <button
                            type="button"
                            class="fluff-box"
                            data-box-number="1"
                            aria-label="Choose mystery box one"
                        >
                            ?
                        </button>

                        <button
                            type="button"
                            class="fluff-box"
                            data-box-number="2"
                            aria-label="Choose mystery box two"
                        >
                            ?
                        </button>

                        <button
                            type="button"
                            class="fluff-box"
                            data-box-number="3"
                            aria-label="Choose mystery box three"
                        >
                            ?
                        </button>

                    </div>

                    <p class="fluff-status" id="fluff-status" aria-live="polite">
                        Choose one box to continue.
                    </p>

                    <div
                        class="fluff-question-area"
                        id="fluff-question-area"
                        hidden
                    >
                        <h3></h3>

                        <input
                            type="hidden"
                            id="fluff-question-id"
                            value=""
                        >

                        <div
                            class="fluff-question-form"
                            id="fluff-question-form"
                        ></div>

                        <div class="fluff-modal-actions">

                            <button
                                type="button"
                                class="fluff-confirm-btn"
                                id="fluff-confirm-answer"
                            >
                                Submit New Answer
                            </button>

                        </div>

                    </div>

                    <div class="fluff-modal-actions">

                        <button
                            type="button"
                            class="fluff-keep-btn"
                            id="fluff-keep-answer"
                            hidden
                        >
                            Keep My Original Answer
                        </button>

                    </div>

                </div>

            </div>

        <?php endif; ?>

    <?php endif; ?>

    <?php if (!$isStarted): ?>

        <button
            type="button"
            class="avatar-launcher"
            id="avatar-launcher"
            data-avatar-path="<?= h($avatarPath) ?>"
            data-avatar-csrf="<?= h($_SESSION['avatar_csrf']) ?>"
            aria-label="Choose avatar"
            aria-expanded="false"
        >
            <img
                src="<?= h($avatarPath . $currentAvatar) ?>"
                alt="Selected avatar"
                id="current-avatar-image"
            >
        </button>

        <div class="avatar-popup" id="avatar-popup" hidden>

            <div class="avatar-popup-header">

                <h3>Choose your companion</h3>

                <button
                    type="button"
                    class="avatar-close"
                    id="avatar-close"
                    aria-label="Close avatar selection"
                >
                    ×
                </button>

            </div>

            <div class="avatar-grid">

                <?php foreach ($avatars as $file => $name): ?>

                    <button
                        type="button"
                        class="avatar-choice <?= $file === $currentAvatar ? 'selected' : '' ?>"
                        data-avatar="<?= h($file) ?>"
                        data-name="<?= h($name) ?>"
                    >
                        <img
                            src="<?= h($avatarPath . $file) ?>"
                            alt="<?= h($name) ?>"
                        >

                        <span><?= h($name) ?></span>
                    </button>

                <?php endforeach; ?>

            </div>

        </div>

    <?php endif; ?>

</section>

<script src="js/student_quiz.js?v=8"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
