```php
<?php
require_once __DIR__ . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('quiz1.php?tab=saved');
}

csrf_check();

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    flash('Invalid quiz ID.');
    redirect('quiz1.php?tab=saved');
}

// Check if quiz exists.
$stmt = $pdo->prepare("SELECT * FROM quiz WHERE quizId = ?");
$stmt->execute([$id]);
$old = $stmt->fetch();

if (!$old) {
    flash('That quiz no longer exists.');
    redirect('quiz1.php?tab=saved');
}

$data = json_decode($_POST['quiz'] ?? '', true);

if (!is_array($data)) {
    flash('Invalid quiz data.');
    redirect('quiz1.php?tab=saved');
}

$title = trim($data['title'] ?? 'Untitled Quiz');

$allow_retake = isset($_POST['allow_retake'])
    ? (int)$_POST['allow_retake']
    : (!empty($data['allow_retake']) ? 1 : 0);

// Answer review setting.
$show_answer_feedback = isset($_POST['show_answer_feedback'])
    ? (int)$_POST['show_answer_feedback']
    : (!empty($data['show_answer_feedback']) ? 1 : 0);

$timer = (int)($data['timer'] ?? 30);
$points = (int)($data['points'] ?? 10);
$xp_per_correct = (int)($data['xp_per_correct'] ?? 5);
$due_date = !empty($data['due']) ? $data['due'] : null;

// Validate XP setting.
if ($xp_per_correct < 1 || $xp_per_correct > 1000) {
    flash('XP per correct answer must be between 1 and 1000.');
    redirect('quiz1.php?tab=saved');
}

// Keep the existing status unless the user publishes it.
$status = ($_POST['publish'] ?? '') === '1'
    ? 'published'
    : $old['status'];

try {
    $pdo->beginTransaction();

    // Update quiz details.
    $upd = $pdo->prepare("
        UPDATE quiz
        SET title = ?,
            allow_retake = ?,
            show_answer_feedback = ?,
            timer = ?,
            points = ?,
            xp_per_correct = ?,
            due_date = ?,
            status = ?
        WHERE quizId = ?
    ");

    $upd->execute([
        $title,
        $allow_retake,
        $show_answer_feedback,
        $timer,
        $points,
        $xp_per_correct,
        $due_date,
        $status,
        $id
    ]);

    // Remove old questions before saving updated ones.
    $delQ = $pdo->prepare("
        DELETE FROM question
        WHERE quizId = ?
    ");

    $delQ->execute([$id]);

    // Save updated questions, choices, and correct answers.
    if (!empty($data['qs']) && is_array($data['qs'])) {

        $qStmt = $pdo->prepare("
            INSERT INTO question
            (quizId, questionText, type, points, choices, correct_answer)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($data['qs'] as $q) {

            $qText = trim($q['q'] ?? '');

            $qType = ($q['type'] ?? 'mc') === 'mc'
                ? 'multiple choice'
                : 'identification';

            if ($qType === 'multiple choice') {

                $choiceArray = $q['ch'] ?? [];
                $choices = json_encode($choiceArray);

                $correctIndex = (int)($q['ok'] ?? -1);
                $correctAnswer = $choiceArray[$correctIndex] ?? '';

            } else {

                $choices = null;
                $correctAnswer = trim($q['ans'] ?? '');

            }

            $qStmt->execute([
                $id,
                $qText,
                $qType,
                $points,
                $choices,
                $correctAnswer
            ]);
        }
    }

    $pdo->commit();

    $_SESSION['clear_draft'] = 1;

    flash(
        $status === 'published'
            ? 'Quiz updated successfully.'
            : 'Quiz details updated.'
    );

    redirect(
        $status === 'published'
            ? 'quiz1.php?tab=pub'
            : 'quiz1.php?tab=saved'
    );

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die('Unable to update quiz: ' . $e->getMessage());
}
```