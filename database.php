<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

define('DB_HOST', envValue('COMFSCI_DB_HOST', '127.0.0.1'));
define('DB_NAME', envValue('COMFSCI_DB_NAME', 'db_comfysci'));
define('DB_USER', envValue('COMFSCI_DB_USER', 'root'));
define('DB_PASS', envValue('COMFSCI_DB_PASS', ''));

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function loginAdmin(PDO $pdo, string $name, string $password){
    // Look up by adminEmail or adminFirstName in db_comfysci.tbladmin
    $stmt = $pdo->prepare("SELECT * FROM tbladmin WHERE adminEmail = ? OR adminFirstName = ? LIMIT 1");
    $stmt->execute([$name, $name]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        return false;
    }

    if (!password_verify($password, $admin['adminPassword'])) {
        return false;
    }

    return true;
}

function createAdminForOtherDB(PDO $pdo, $ln, $fn, $mn, $en, $email, $password){
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO tbladmin (adminLastName, adminFirstName, adminMiddleName, adminExtensionName, adminEmail, adminPassword) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$ln, $fn, $mn, $en, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $pdo->commit();        // commit first...
        return true;           // ...then return
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

