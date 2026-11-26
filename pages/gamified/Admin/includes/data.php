<?php
// Quiz data layer.
// Right now quizzes live in $_SESSION so the pages work without a database.
// To go live, replace the bodies of quiz_all / quiz_get / quiz_put / quiz_remove /
// quiz_next_id with MySQL queries. Nothing else in the project needs to change.

const QUIZ_TIMERS   = [15, 30, 45, 60, 90];
const QUIZ_POINTS   = [5, 10, 15, 20];
const QUIZ_SECTIONS = ['BSCS 2A', 'BSCS 2B', 'BSCS 3A', 'BSCS 3B'];

function quiz_seed(): array {
  $filler = [];
  for ($k = 1; $k <= 5; $k++) {
    $filler[] = ['type' => 'mc', 'q' => 'Sample question ' . $k, 'ch' => ['Option A', 'Option B', 'Option C', 'Option D'], 'ok' => 0, 'ans' => ''];
  }
  return [
    1 => ['id' => 1, 'status' => 'draft', 'title' => 'Midterm review', 'timer' => 30, 'points' => 10, 'due' => '2026-11-20', 'assign' => 'BSCS 2B', 'qs' => [
      ['type' => 'mc', 'q' => 'What does HTML stand for?', 'ch' => ['Hyper Text Markup Language', 'High Tech Modern Language', 'Hyperlink Text Mode', 'Home Tool Markup Language'], 'ok' => 0, 'ans' => ''],
      ['type' => 'mc', 'q' => 'Which tag creates a hyperlink?', 'ch' => ['<a>', '<link>', '<href>', '<url>'], 'ok' => 0, 'ans' => ''],
      ['type' => 'id', 'q' => 'What does CSS stand for?', 'ch' => ['', '', '', ''], 'ok' => -1, 'ans' => 'Cascading Style Sheets'],
    ]],
    2 => ['id' => 2, 'status' => 'published', 'title' => 'Intro to Computing', 'timer' => 30, 'points' => 10, 'due' => '2026-10-30', 'assign' => 'BSCS 2B', 'qs' => $filler, 'sub' => 24, 'tot' => 30, 'avg' => 67],
  ];
}

function quiz_all(): array {
  if (!isset($_SESSION['quizzes'])) { $_SESSION['quizzes'] = quiz_seed(); $_SESSION['quiz_uid'] = 10; }
  return $_SESSION['quizzes'];
}
function quiz_get(int $id): ?array { return quiz_all()[$id] ?? null; }
function quiz_put(array $z): void { quiz_all(); $_SESSION['quizzes'][$z['id']] = $z; }
function quiz_remove(int $id): void { quiz_all(); unset($_SESSION['quizzes'][$id]); }
function quiz_next_id(): int { quiz_all(); return ++$_SESSION['quiz_uid']; }

// ---- Helpers ----
function quiz_pts(array $z): int { return count($z['qs']) * $z['points']; }
function fmt_date(string $d): string { $t = strtotime($d); return $d && $t ? date('M j, Y', $t) : 'No date'; }
function quiz_meta(array $z): string {
  $n = count($z['qs']);
  return $n . ' question' . ($n > 1 ? 's' : '') . ' · ' . quiz_pts($z) . ' pts · Due ' . fmt_date($z['due']) . ' · ' . $z['assign'];
}

// Turns the JSON the browser sends into a clean quiz array (never trust the client).
function quiz_clean($json): ?array {
  $r = json_decode((string)$json, true);
  if (!is_array($r)) return null;
  $z = [
    'title'  => trim((string)($r['title'] ?? '')),
    'timer'  => (int)($r['timer'] ?? 30),
    'points' => (int)($r['points'] ?? 10),
    'due'    => (string)($r['due'] ?? ''),
    'assign' => (string)($r['assign'] ?? ''),
    'qs'     => [],
  ];
  foreach ((array)($r['qs'] ?? []) as $q) {
    if (!is_array($q)) continue;
    $ch = array_map(fn($c) => is_scalar($c) ? (string)$c : '', array_slice(array_values((array)($q['ch'] ?? [])), 0, 4));
    $z['qs'][] = [
      'type' => (($q['type'] ?? '') === 'id') ? 'id' : 'mc',
      'q'    => trim((string)($q['q'] ?? '')),
      'ch'   => array_pad($ch, 4, ''),
      'ok'   => (int)($q['ok'] ?? -1),
      'ans'  => trim((string)($q['ans'] ?? '')),
    ];
  }
  return $z;
}

// Returns an error message, or null when the quiz is valid.
function quiz_error(array $z): ?string {
  if ($z['title'] === '') return 'Give your quiz a title first.';
  if (!in_array($z['timer'], QUIZ_TIMERS, true)) return 'Pick a valid timer.';
  if (!in_array($z['points'], QUIZ_POINTS, true)) return 'Pick valid points per question.';
  if (!in_array($z['assign'], QUIZ_SECTIONS, true)) return 'Pick a section to assign the quiz to.';
  $d = DateTime::createFromFormat('Y-m-d', $z['due']);
  if (!$d || $d->format('Y-m-d') !== $z['due']) return 'Pick a due date.';
  if (!$z['qs']) return 'A quiz needs at least one question.';
  foreach ($z['qs'] as $k => $q) {
    $p = 'Question ' . ($k + 1) . ': ';
    if ($q['q'] === '') return $p . 'Type the question first.';
    if ($q['type'] === 'mc') {
      foreach ($q['ch'] as $c) if (trim($c) === '') return $p . 'Fill in all four choices (A to D).';
      if ($q['ok'] < 0 || $q['ok'] > 3) return $p . 'Select the correct answer.';
    } elseif ($q['ans'] === '') {
      return $p . 'Enter the expected answer.';
    }
  }
  return null;
}