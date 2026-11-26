
<?php
// Scores submitted answers and records quiz results and earned XP.
require_once __DIR__ . '/includes/init.php';

$stmt_student = $pdo->prepare("SELECT * FROM students WHERE studId = ? LIMIT 1");
$stmt_student->execute([(int) $_SESSION['user_id']]);
$current_student = $stmt_student->fetch();

if (!$current_student || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: student_dashboard.php");
    exit;
}

$quizId = isset($_POST['quizId']) ? (int)$_POST['quizId'] : 0;
$answers = $_POST['answer'] ?? [];
$attemptToken = $_POST['attempt_token'] ?? '';
$userId = (int)$current_student['studId'];

if ($quizId <= 0 || !is_array($answers) || !is_string($attemptToken)) {
    header("Location: student_dashboard.php");
    exit;
}

// ==========================================
// VERIFY THE ATTEMPT SESSION
// ==========================================

$sessionToken = $_SESSION['quiz_attempt_tokens'][$quizId] ?? '';
$attemptStarted = !empty($_SESSION['quiz_attempt_started'][$quizId]);

if (
    !$attemptStarted ||
    empty($sessionToken) ||
    !hash_equals($sessionToken, $attemptToken)
) {
    header("Location: take_quiz.php?id=" . $quizId);
    exit;
}

// ==========================================
// GET THE LOCKED AVATAR
// ==========================================

$lockedAvatar = $_SESSION['quiz_attempt_avatar'][$quizId] ?? '';

$allowedAvatars = [
    'caramel.png',
    'cookie.png',
    'fluff.png',
    'matcha.png',
    'mochi.png',
    'taro.png'
];

if (!in_array($lockedAvatar, $allowedAvatars, true)) {
    header("Location: take_quiz.php?id=" . $quizId);
    exit;
}

// ==========================================
// GET THE ACTUAL QUIZ START TIME
// ==========================================

$attemptStartedAt = (int)(
    $_SESSION['quiz_attempt_started_at'][$quizId] ?? time()
);

$endedAtTimestamp = time();
$timeSpentSeconds = max(0, $endedAtTimestamp - $attemptStartedAt);

$startedAt = gmdate('Y-m-d H:i:s', $attemptStartedAt);
$endedAt = gmdate('Y-m-d H:i:s', $endedAtTimestamp);

// ==========================================
// VERIFY THE ATTEMPT RECORD
// ==========================================

$stmt_attempt = $pdo->prepare("
    SELECT noteId
    FROM quiz_memory_notes
    WHERE userId = ?
      AND quizId = ?
      AND attempt_token = ?
      AND locked_at IS NOT NULL
    LIMIT 1
");

$stmt_attempt->execute([
    $userId,
    $quizId,
    $attemptToken
]);

$attemptRecord = $stmt_attempt->fetch();

if (!$attemptRecord) {
    header("Location: take_quiz.php?id=" . $quizId);
    exit;
}

// ==========================================
// VERIFY PUBLISHED QUIZ
// ==========================================

$stmt_quiz = $pdo->prepare("
    SELECT *
    FROM quiz
    WHERE quizId = ? AND status = 'published'
");

$stmt_quiz->execute([$quizId]);
$quiz = $stmt_quiz->fetch();

if (!$quiz) {
    die("Quiz not found or unavailable.");
}

$showAnswerFeedback = (int)($quiz['show_answer_feedback'] ?? 0) === 1;

// ==========================================
// CHECK PREVIOUS COMPLETION
// ==========================================

$stmt_check = $pdo->prepare("
    SELECT COUNT(*)
    FROM gamequiz
    WHERE userId = ? AND quizId = ?
");

$stmt_check->execute([$userId, $quizId]);
$alreadyCompleted = (int)$stmt_check->fetchColumn() > 0;

if ($alreadyCompleted && (int)($quiz['allow_retake'] ?? 0) !== 1) {
    header("Location: student_dashboard.php?tab=completed");
    exit;
}

// ==========================================
// GET QUESTIONS
// ==========================================

$stmt_q = $pdo->prepare("
    SELECT *
    FROM question
    WHERE quizId = ?
    ORDER BY questionId ASC
");

$stmt_q->execute([$quizId]);
$questions = $stmt_q->fetchAll();

// ==========================================
// FLUFF - THINK AGAIN REDEMPTION
// ==========================================

// The replacement answer must belong to the question
// randomly selected by Fluff during this attempt.

if ($lockedAvatar === 'fluff.png') {

    $fluffKeepOriginal = (
        isset($_POST['fluff_keep_original']) &&
        $_POST['fluff_keep_original'] === '1'
    );

    $fluffQuestionId = isset($_POST['fluff_redemption_question_id'])
        ? (int)$_POST['fluff_redemption_question_id']
        : 0;

    $fluffNewAnswer = $_POST['fluff_redemption_answer'] ?? '';

    $selectedFluffQuestion = (int)(
        $_SESSION['fluff_selected_question'][$attemptToken] ?? 0
    );

    $eligibleFluffQuestions =
        $_SESSION['fluff_eligible_questions'][$attemptToken] ?? [];

    $fluffAlreadyUsed = !empty(
        $_SESSION['fluff_redemption_used'][$attemptToken]
    );

    if (!$fluffAlreadyUsed) {

        if ($fluffKeepOriginal) {

            // Student decided to keep the original answer.
            $_SESSION['fluff_redemption_used'][$attemptToken] = true;

        } elseif (
            $fluffQuestionId > 0 &&
            $fluffQuestionId === $selectedFluffQuestion &&
            is_array($eligibleFluffQuestions) &&
            in_array(
                $fluffQuestionId,
                array_map('intval', $eligibleFluffQuestions),
                true
            ) &&
            is_string($fluffNewAnswer) &&
            trim($fluffNewAnswer) !== ''
        ) {

            // Replace the original answer BEFORE scoring.
            $answers[$fluffQuestionId] = trim($fluffNewAnswer);

            $_SESSION['fluff_redemption_used'][$attemptToken] = true;
        }
    }
}

// ==========================================
// INITIALIZE SCORE AND ANSWER COUNTS
// ==========================================

$score = 0;
$totalPoints = 0;
$correctCount = 0;
$incorrectCount = 0;
$unansweredCount = 0;
$totalQuestions = count($questions);

// Store answers for the result-page review.
$answerReviews = [];

// ==========================================
// STREAK TRACKING
// ==========================================

$currentStreak = 0;
$bestStreak = 0;
$streakBonusXP = 0;

$streakRewards = [
    3 => 5,
    5 => 10,
    7 => 15,
    10 => 25
];

$earnedStreakRewards = [];

// ==========================================
// COOKIE STREAK SAVER
// ==========================================

$cookieUsed = false;

// ==========================================
// SCORE THE QUIZ
// ==========================================

foreach ($questions as $index => $q) {

    $qId = (int)$q['questionId'];
    $pts = (int)($q['points'] ?? 10);

    $totalPoints += $pts;

    // This now uses Fluff's replacement answer when applicable.
    $studentAnswer = $answers[$qId] ?? '';
    $correctAnswer = $q['correct_answer'] ?? '';

    // Empty answers are counted as unanswered.
    if (!is_string($studentAnswer) || trim($studentAnswer) === '') {

        $unansweredCount++;
        $currentStreak = 0;

        $answerReviews[] = [
            'number' => $index + 1,
            'question' => $q['questionText'] ?? '',
            'student_answer' => '',
            'correct_answer' => (string)$correctAnswer,
            'status' => 'unanswered'
        ];

        continue;
    }

    $studentAnswer = trim($studentAnswer);
    $correctAnswer = trim((string)$correctAnswer);

    $isCorrect = false;

    if ($correctAnswer !== '') {

        $qType = strtolower($q['type'] ?? '');

        if (
            str_contains($qType, 'multiple') ||
            str_contains($qType, 'choice') ||
            in_array($qType, ['true/false', 'true false', 'truefalse', 'tf'], true)
        ) {

            $isCorrect = ($studentAnswer === $correctAnswer);

        } else {

            $isCorrect = (
                mb_strtolower($studentAnswer) ===
                mb_strtolower($correctAnswer)
            );
        }
    }

    // Save the final answer for the review section.
    $answerReviews[] = [
        'number' => $index + 1,
        'question' => $q['questionText'] ?? '',
        'student_answer' => $studentAnswer,
        'correct_answer' => $correctAnswer,
        'status' => $isCorrect ? 'correct' : 'incorrect'
    ];

    if ($isCorrect) {

        // Academic score.
        $score += $pts;
        $correctCount++;

        // Update streak.
        $currentStreak++;

        if ($currentStreak > $bestStreak) {
            $bestStreak = $currentStreak;
        }

        // Only Cookie receives streak bonus XP.
        if ($lockedAvatar === 'cookie.png') {

            if (
                isset($streakRewards[$currentStreak]) &&
                !in_array($currentStreak, $earnedStreakRewards, true)
            ) {
                $streakBonusXP += $streakRewards[$currentStreak];
                $earnedStreakRewards[] = $currentStreak;
            }
        }

    } else {

        $incorrectCount++;

        // Cookie protects one existing streak from an incorrect answer.
        if (
            $lockedAvatar === 'cookie.png' &&
            !$cookieUsed &&
            $currentStreak > 0
        ) {
            $cookieUsed = true;
        } else {
            $currentStreak = 0;
        }
    }
}

// ==========================================
// CALCULATE XP
// ==========================================

$xpPerCorrect = max(1, (int)($quiz['xp_per_correct'] ?? 5));

$baseXP = $correctCount * $xpPerCorrect;
$totalXPGained = $baseXP + $streakBonusXP;

// ==========================================
// FORMAT TIME SPENT
// ==========================================

$timeMinutes = intdiv($timeSpentSeconds, 60);
$timeSeconds = $timeSpentSeconds % 60;

if ($timeMinutes > 0) {

    $timeSpentText = $timeMinutes . ' min' .
        ($timeMinutes === 1 ? '' : 's') .
        ' and ' . $timeSeconds . ' sec' .
        ($timeSeconds === 1 ? '' : 's');

} else {

    $timeSpentText = $timeSeconds . ' sec' .
        ($timeSeconds === 1 ? '' : 's');
}

// ==========================================
// SAVE QUIZ RESULT AND XP
// ==========================================

try {

    $pdo->beginTransaction();

    $stmt_insert = $pdo->prepare("
        INSERT INTO gamequiz
        (userId, quizId, score, totalPoints, startedAt, endedAt)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt_insert->execute([
        $userId,
        $quizId,
        $score,
        $totalPoints,
        $startedAt,
        $endedAt
    ]);

    $stmt_xp = $pdo->prepare("
        UPDATE students
        SET total_xp = COALESCE(total_xp, 0) + ?
        WHERE studId = ?
    ");

    $stmt_xp->execute([
        $totalXPGained,
        $userId
    ]);

    $pdo->commit();

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Quiz Submission Error: ' . $e->getMessage());

    die("Unable to submit your quiz. Please try again.");
}

// ==========================================
// CLEAR THIS ATTEMPT'S SESSION DATA
// ==========================================

unset($_SESSION['quiz_attempt_tokens'][$quizId]);
unset($_SESSION['quiz_attempt_started'][$quizId]);
unset($_SESSION['quiz_attempt_avatar'][$quizId]);
unset($_SESSION['quiz_attempt_started_at'][$quizId]);

unset($_SESSION['focus_mode_used'][$attemptToken]);
unset($_SESSION['focus_mode_until'][$attemptToken]);

unset($_SESSION['fluff_spin_used'][$attemptToken]);
unset($_SESSION['fluff_spin_rewards'][$attemptToken]);

unset($_SESSION['fluff_original_answers'][$attemptToken]);
unset($_SESSION['fluff_eligible_questions'][$attemptToken]);
unset($_SESSION['fluff_selected_question'][$attemptToken]);
unset($_SESSION['fluff_redemption_used'][$attemptToken]);

// ==========================================
// RESULT PAGE
// ==========================================

$PAGE_TITLE = 'Gamified Quiz';

include __DIR__ . '/includes/header.php';
?>

<style>
    .answer-review {
        margin-top: 30px;
        border: 1px solid #383838;
        border-radius: 16px;
        overflow: hidden;
        background: #222;
    }

    .answer-review summary {
        cursor: pointer;
        padding: 20px 24px;
        font-size: 16px;
        font-weight: 800;
        color: #fff;
        list-style: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        transition: background 0.2s ease;
    }

    .answer-review summary::-webkit-details-marker {
        display: none;
    }

    .answer-review summary:hover {
        background: #2c2c2c;
    }

    .answer-review summary::after {
        content: '+';
        font-size: 24px;
        color: var(--o);
        font-weight: 700;
    }

    .answer-review[open] summary::after {
        content: '−';
    }

    .review-content {
        padding: 10px 24px 24px;
    }

    .review-item {
        background: #292929;
        border: 1px solid #3b3b3b;
        border-radius: 14px;
        padding: 20px;
        margin-top: 14px;
    }

    .review-question {
        font-size: 15px;
        font-weight: 700;
        color: #fff;
        line-height: 1.6;
        margin: 0 0 16px;
    }

    .review-status {
        display: inline-block;
        padding: 6px 11px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 800;
        margin-bottom: 14px;
    }

    .review-status.correct {
        color: #9be7b0;
        background: #193d28;
    }

    .review-status.incorrect {
        color: #ffaaaa;
        background: #482323;
    }

    .review-status.unanswered {
        color: #ffd89b;
        background: #49371f;
    }

    .review-answer {
        font-size: 13px;
        line-height: 1.6;
        margin: 8px 0 0;
        color: #bbb;
        overflow-wrap: anywhere;
    }

    .review-answer strong {
        color: #fff;
    }

    .review-answer.correct-answer strong {
        color: #9be7b0;
    }

    @media (max-width: 750px) {
        .result-layout {
            grid-template-columns: 1fr !important;
        }

        .result-panel {
            padding: 24px !important;
        }

        .review-content {
            padding: 8px 14px 18px;
        }

        .review-item {
            padding: 16px;
        }
    }
</style>

<section class="panel result-panel" style="width: 100%; max-width: 100%; margin: 0; background: #1a1a1a; border-radius: 24px; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); box-sizing: border-box; color: #fff;">

    <div style="text-align: center; margin-bottom: 30px;">
        <h1 style="font-size: 32px; font-weight: 800; color: var(--o); margin: 0;">
            Quiz Completed!
        </h1>
    </div>

    <div class="result-layout" style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: center;">

        <div style="text-align: center; background: #222; border-radius: 20px; padding: 40px; border: 1px solid #333;">

            <p style="font-size: 18px; color: #aaa; margin: 0 0 10px 0; font-weight: 600;">
                You scored:
            </p>

            <h2 style="font-size: 54px; font-weight: 900; color: var(--o); margin: 0 0 20px 0;">
                <?= $score ?> / <?= $totalPoints ?>
            </h2>

            <div style="font-size: 50px; margin-bottom: 15px;">🏅</div>

            <h3 style="font-size: 22px; font-weight: 800; color: #fff; margin: 0 0 8px 0;">
                <?= ($totalQuestions > 0 && $correctCount === $totalQuestions) ? 'Perfect Score!' : 'Quiz Completed!' ?>
            </h3>

            <p style="font-size: 14px; color: #aaa; margin: 0;">
                Keep practicing and improving!
            </p>

        </div>

        <div style="display: flex; flex-direction: column; gap: 16px;">

            <div style="background: #222; border: 1px solid #333; border-radius: 14px; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 14px; color: #aaa; font-weight: 600;">Correct Answers</span>
                <strong style="font-size: 18px; color: #fff;"><?= $correctCount ?> / <?= $totalQuestions ?></strong>
            </div>

            <div style="background: #222; border: 1px solid #333; border-radius: 14px; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 14px; color: #aaa; font-weight: 600;">Incorrect Answers</span>
                <strong style="font-size: 18px; color: #fff;"><?= $incorrectCount ?> / <?= $totalQuestions ?></strong>
            </div>

            <div style="background: #222; border: 1px solid #333; border-radius: 14px; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 14px; color: #aaa; font-weight: 600;">Unanswered</span>
                <strong style="font-size: 18px; color: #f0b36d;"><?= $unansweredCount ?> / <?= $totalQuestions ?></strong>
            </div>

            <div style="background: #222; border: 1px solid #333; border-radius: 14px; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 14px; color: #aaa; font-weight: 600;">Time Spent</span>
                <strong style="font-size: 18px; color: #fff;"><?= htmlspecialchars($timeSpentText, ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <div style="background: #222; border: 1px solid #333; border-radius: 14px; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 14px; color: #aaa; font-weight: 600;">Best Streak</span>
                <strong style="font-size: 18px; color: #fff;">🔥 <?= $bestStreak ?></strong>
            </div>

            <div style="background: #222; border: 1px solid #333; border-radius: 14px; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 14px; color: #aaa; font-weight: 600;">Base XP</span>
                <strong style="font-size: 18px; color: #f39c12;">+<?= $baseXP ?> XP</strong>
            </div>

            <div style="background: #222; border: 1px solid #333; border-radius: 14px; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 14px; color: #aaa; font-weight: 600;">Streak Bonus</span>
                <strong style="font-size: 18px; color: #f39c12;">+<?= $streakBonusXP ?> XP</strong>
            </div>

            <?php if ($cookieUsed): ?>
                <div style="background: #332719; border: 1px solid #8a6335; border-radius: 14px; padding: 16px 20px; color: #ffd89b; text-align: center;">
                    Cookie protected your streak once!
                </div>
            <?php endif; ?>

            <div style="background: #222; border: 1px solid #333; border-radius: 14px; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 14px; color: #aaa; font-weight: 600;">Total XP Gained</span>
                <strong style="font-size: 18px; color: #f39c12;">⭐ +<?= $totalXPGained ?> XP</strong>
            </div>

        </div>
    </div>

    <?php if ($showAnswerFeedback): ?>

        <details class="answer-review">

            <summary>
                <span>Review Answers</span>
            </summary>

            <div class="review-content">

                <p style="font-size: 13px; color: #aaa; margin: 4px 0 18px;">
                    Check your answers and see which ones you got right.
                </p>

                <?php foreach ($answerReviews as $review): ?>

                    <?php
                        $status = $review['status'];

                        if ($status === 'correct') {
                            $statusLabel = 'Correct';
                        } elseif ($status === 'incorrect') {
                            $statusLabel = 'Incorrect';
                        } else {
                            $statusLabel = 'Unanswered';
                        }
                    ?>

                    <div class="review-item">

                        <span class="review-status <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                        </span>

                        <p class="review-question">
                            Question <?= (int)$review['number'] ?>:
                            <?= htmlspecialchars((string)$review['question'], ENT_QUOTES, 'UTF-8') ?>
                        </p>

                        <p class="review-answer">
                            Your answer:
                            <strong>
                                <?php if ($review['status'] === 'unanswered'): ?>
                                    Not answered
                                <?php else: ?>
                                    <?= htmlspecialchars((string)$review['student_answer'], ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </strong>
                        </p>

                        <?php if ($review['status'] !== 'correct'): ?>
                            <p class="review-answer correct-answer">
                                Correct answer:
                                <strong>
                                    <?= htmlspecialchars((string)$review['correct_answer'], ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                            </p>
                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>

        </details>

    <?php endif; ?>

    <div style="margin-top: 24px;">
        <a href="student_dashboard.php?tab=leaderboard" class="btn o" style="display: block; text-align: center; background: var(--o); color: #fff; border-radius: 14px; padding: 16px; font-weight: 700; text-decoration: none; font-size: 16px; box-shadow: 0 4px 14px rgba(235,104,26,0.4);">
            View Leaderboard
        </a>
    </div>

</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
