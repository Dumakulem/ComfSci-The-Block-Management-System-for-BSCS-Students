<?php
declare(strict_types=1);

$env = static fn (string $k, string $default): string => getenv($k) !== false ? (string) getenv($k) : $default;

return [
    'host' => $env('DB_HOST', '127.0.0.1'),
    'port' => $env('DB_PORT', '3306'),
    'name' => $env('DB_NAME', 'db_comfysci'),
    'user' => $env('DB_USER', 'root'),
    'pass' => $env('DB_PASS', ''),

];
