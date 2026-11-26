<?php

declare(strict_types=1);

/**
 * One-command installer (CLI only).
 *
 *   php install.php                       # create schema + run all migrations
 *   php install.php admin@school.edu Pass123 "First" "Last"   # ...and create an admin login
 *
 * Safe to re-run: every migration is idempotent.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config.php';

$host = envValue('COMFSCI_DB_HOST', '127.0.0.1');
$name = 'db_comfysci'; // the migrations contain `USE db_comfysci`
$user = envValue('COMFSCI_DB_USER', 'root');
$pass = envValue('COMFSCI_DB_PASS', '');

$files = [
    'sql/00_base_schema.sql',
    'pages/sql/admin_archive_columns.sql',
    'pages/sql/assignment_tracking.sql',
    'pages/sql/event_tracking.sql',
    'pages/sql/attendance_tracker_update.sql',
    'pages/sql/dashboard_tables.sql',
    'pages/sql/forum_tables.sql',
    'pages/sql/group_activity_tables.sql',
    'pages/sql/resource_tables.sql',
    'pages/sql/module_sources.sql',
    'pages/sql/ai_consultation_tables.sql',
    'pages/sql/gamified_quiz_tables.sql',
];

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => true,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Cannot connect to MySQL at $host as $user: " . $e->getMessage() . "\n");
    exit(1);
}

foreach ($files as $file) {
    $sql = file_get_contents(__DIR__ . '/' . $file);
    if ($sql === false) {
        fwrite(STDERR, "Missing $file\n");
        exit(1);
    }
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` DEFAULT CHARACTER SET utf8mb4");
        $pdo->exec("USE `$name`");
        $statement = $pdo->prepare(str_replace("\r\n", "\n", $sql));
        $statement->execute();
        while ($statement->nextRowset()) {
            // drain multi-statement results so errors surface
        }
        echo "ok   $file\n";
    } catch (PDOException $e) {
        fwrite(STDERR, "FAIL $file: " . $e->getMessage() . "\n");
        exit(1);
    }
}

if (isset($argv[1], $argv[2])) {
    [$email, $password] = [$argv[1], $argv[2]];
    $first = $argv[3] ?? 'Admin';
    $last = $argv[4] ?? 'User';
    $pdo->prepare(
        'INSERT INTO tbladmin (adminFirstName, adminLastName, adminEmail, adminPassword)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE adminPassword = VALUES(adminPassword)'
    )->execute([$first, $last, $email, password_hash($password, PASSWORD_DEFAULT)]);
    echo "ok   admin account ready: $email\n";
}

echo "\nDone. Open /auth/login.php\n";
