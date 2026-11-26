```php
<?php
require_once __DIR__ . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('quiz1.php');
}

csrf_check();

$id = (int)($_POST['id'] ?? 0);
$mode = $_POST['mode'] ?? '';

$allow_retake = isset($_POST['allow_retake'])
    ? (int)$_POST['allow_retake']
    : null;

$show_answer_feedback = isset($_POST['show_answer_feedback'])
    ? (int)$_POST['show_answer_feedback']
    : null;

if ($id > 0) {
    if ($mode === 'publish') {

        if (
            $allow_retake !== null &&
            $show_answer_feedback !== null
        ) {
            $stmt = $pdo->prepare("
                UPDATE quiz
                SET status = 'published',
                    allow_retake = ?,
                    show_answer_feedback = ?
                WHERE quizId = ?
            ");

            $stmt->execute([
                $allow_retake,
                $show_answer_feedback,
                $id
            ]);

        } elseif ($allow_retake !== null) {

            $stmt = $pdo->prepare("
                UPDATE quiz
                SET status = 'published',
                    allow_retake = ?
                WHERE quizId = ?
            ");

            $stmt->execute([$allow_retake, $id]);

        } elseif ($show_answer_feedback !== null) {

            $stmt = $pdo->prepare("
                UPDATE quiz
                SET status = 'published',
                    show_answer_feedback = ?
                WHERE quizId = ?
            ");

            $stmt->execute([$show_answer_feedback, $id]);

        } else {

            $stmt = $pdo->prepare("
                UPDATE quiz
                SET status = 'published'
                WHERE quizId = ?
            ");

            $stmt->execute([$id]);
        }

        flash('Quiz published successfully.');
        redirect('quiz1.php?tab=pub');

    } elseif ($mode === 'unpublish') {

        $stmt = $pdo->prepare("
            UPDATE quiz
            SET status = 'draft'
            WHERE quizId = ?
        ");

        $stmt->execute([$id]);

        flash('Quiz moved to drafts.');
        redirect('quiz1.php?tab=saved');
    }
}

redirect('quiz1.php');