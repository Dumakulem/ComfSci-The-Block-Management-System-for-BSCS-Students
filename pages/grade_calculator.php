<?php

declare(strict_types=1);

// Provides a private, configurable weighted-grade breakdown for a signed-in student.
require_once __DIR__ . '/../auth/require_auth.php';
require_once __DIR__ . '/../auth/roles.php';

requireAnyRole(['student']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light" data-sidebar="closed">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Breakdown</title>
    <link rel="stylesheet" href="./assets/dashboard.css?v=10">
    <link rel="stylesheet" href="./assets/grade_calculator.css">
    <link rel="stylesheet" href="./assets/sidebar.css?v=1">
</head>
<body class="comfsci-shell">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="content grade-content">
    <p><a href="./dashboard.php">← Back to dashboard</a></p>
    <section class="hero">
        <h1>Grade breakdown</h1>
        <p>Enter your own grading categories and weights. This tool does not assume a college grading scheme or submit your grades.</p>
    </section>
    <section class="card grade-card">
        <div class="grade-toolbar">
            <button type="button" class="btn btn-solid" id="addGradeItem">Add category</button>
            <button type="button" class="btn" id="clearGradeItems">Clear saved breakdown</button>
        </div>
        <p class="grade-weight" id="weightSummary" aria-live="polite"></p>
        <div class="grade-table-wrap">
            <table class="grade-table">
                <thead>
                    <tr><th>Category</th><th>Score earned</th><th>Score possible</th><th>Weight (%)</th><th></th></tr>
                </thead>
                <tbody id="gradeItems"></tbody>
            </table>
        </div>
        <section class="grade-results" aria-live="polite">
            <div><span>Current weighted average</span><strong id="currentAverage">—</strong></div>
            <label>Target grade (%)
                <input type="number" id="targetGrade" min="0" max="100" step="0.01" value="75">
            </label>
            <div><span>Average needed for remaining weight</span><strong id="neededAverage">—</strong></div>
            <p id="gradeMessage"></p>
        </section>
    </section>
</main>
<script src="./assets/grade_calculator.js"></script>
<?php require __DIR__ . '/includes/ai_chat_widget.php'; ?>
</body>
</html>
