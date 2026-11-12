<?php

declare(strict_types=1);

session_start();

if (empty($_SESSION['authenticated']) || empty($_SESSION['user_type'])) {
    header('Location: ../auth/login.php');
    exit;
}

