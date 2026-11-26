<?php

declare(strict_types=1);

require_once __DIR__ . '/require_auth.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden: administrator access required.');
}

