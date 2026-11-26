<?php
// delete_quiz.php
require_once __DIR__ . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('quiz1.php?tab=saved');
}

csrf_check();

$id = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    $delQuestions = $pdo->prepare("DELETE FROM question WHERE quizId = ?");
    $delQuestions->execute([$id]);

    $del = $pdo->prepare("DELETE FROM quiz WHERE quizId = ?");
    $del->execute([$id]);

    flash('Quiz deleted successfully.');
} else {
    flash('Invalid quiz ID.');
}

$tab = $_POST['tab'] ?? 'saved';
redirect('quiz1.php?tab=' . $tab);