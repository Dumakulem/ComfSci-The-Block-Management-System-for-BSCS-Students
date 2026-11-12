<?php

declare(strict_types=1);

session_start();

if (!empty($_SESSION['authenticated'])) {
    header('Location: pages/index.php');
} else {
    header('Location: auth/login.php');
}
exit;

