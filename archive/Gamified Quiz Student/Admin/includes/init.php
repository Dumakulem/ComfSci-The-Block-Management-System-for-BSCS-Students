<?php
session_start();

require_once __DIR__ . '/../../db.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): void { header('Location: ' . $url); exit; }

function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'; }
function csrf_check(): void {
  if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
    http_response_code(419);
    exit('This form expired. Go back, refresh the page and try again.');
  }
}

function flash(string $msg): void { $_SESSION['flash'] = $msg; }
function take_flash(): ?string {
  $m = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);
  return $m;
}