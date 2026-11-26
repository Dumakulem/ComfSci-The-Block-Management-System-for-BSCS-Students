<?php

declare(strict_types=1);

require_once __DIR__ . '/require_auth.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/auth/portal-auth.css">
</head>
<body>
    <main class="notice">
        <h1>Access denied</h1>
        <p>This page is for administrators only.</p>
        <a class="button" href="../pages/dashboard.php">Back to dashboard</a>
    </main>
</body>
</html>
    <?php
    exit;
}
