<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
const AGENDA_URL = 'agenda.php';   // <- point this at your agenda / event calendar page
const DAY_NAMES  = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];   // index = PHP date('w')
function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/** Icon from the icons/ folder (icons/NAME.png). It is drawn as a CSS mask, so it takes the text colour (currentColor). */
function icon(string $name, int $size = 18, string $class = ''): string {
    // The image URL is written straight into the style attribute so it is resolved from the page (icons/NAME.png),
    // not from css/style.css (a url() inside a CSS variable would be looked up as css/icons/NAME.png).
    $url = 'url(icons/' . e($name) . '.png)';
    return '<span class="ic' . ($class !== '' ? ' ' . e($class) : '') . '" style="width:' . $size . 'px;height:' . $size . 'px;-webkit-mask-image:' . $url . ';mask-image:' . $url . '" aria-hidden="true"></span>';
}

/** Returns the month as weeks (Sunday-first); empty cells are null. */
function build_calendar(int $year, int $month): array {
    $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
    $cells = array_fill(0, (int)$first->format('w'), null);   // w: 0 = Sunday
    for ($d = 1, $n = (int)$first->format('t'); $d <= $n; $d++) { $cells[] = $d; }
    $cells = array_pad($cells, 42, null);
    return array_chunk($cells, 7);
}

// ---- Forms, flash messages, links ----
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function csrf_ok(): bool { return hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? ''); }
function csrf_field(): string {
    global $ym;
    return '<input type="hidden" name="csrf" value="'.csrf_token().'"><input type="hidden" name="m" value="'.e($ym ?? '').'">';
}
function flash(string $m): void { $_SESSION['flash'] = $m; }
function take_flash(): string { $m = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']); return $m; }
/** Link to the agenda / event calendar, optionally opened on one date ('Y-m-d'). */
function agenda_link(string $date = ''): string { return AGENDA_URL . ($date !== '' ? '?' . http_build_query(['date' => $date]) : ''); }
function link_to(array $p = []): string { global $ym; return '?' . http_build_query(['m' => $ym] + $p); }

// ---- Online class schedules ----
// A schedule repeats weekly: ['subject','professor','days'=>[0..6],'start'=>'HH:MM','end'=>'HH:MM','link'].
/** Turns weekly schedules into dated classes between $from and $to (inclusive, 'Y-m-d'). */
function expand_schedules(array $schedules, string $from, string $to): array {
    $out = [];
    $d   = new DateTimeImmutable($from);
    $end = new DateTimeImmutable($to);
    for (; $d <= $end; $d = $d->modify('+1 day')) {
        $w = (int)$d->format('w');
        foreach ($schedules as $i => $s) {
            if (in_array($w, $s['days'], true)) {
                $out[] = ['sid' => $i, 'subject' => $s['subject'], 'professor' => $s['professor'], 'date' => $d->format('Y-m-d'),
                          'time' => $s['start'], 'end' => $s['end'], 'link' => $s['link']];
            }
        }
    }
    usort($out, fn($a, $b) => strcmp($a['date'] . $a['time'], $b['date'] . $b['time']));
    return $out;
}
function sort_schedules(array $s): array {   // keeps the array keys (database ids)
    $key = fn($x) => [$x['days'] ? min($x['days']) : 9, $x['start'], $x['subject']];
    uasort($s, fn($a, $b) => $key($a) <=> $key($b));
    return $s;
}
function fmt_time(string $t): string { return $t === '' ? '' : date('g:i A', strtotime($t)); }
/** "Just now", "10m ago", "3h ago", "2d ago", or the date for older items. */
function time_ago(string $ts): string {
    $s = max(0, time() - strtotime($ts));
    if ($s < 60)     { return 'Just now'; }
    if ($s < 3600)   { return intdiv($s, 60) . 'm ago'; }
    if ($s < 86400)  { return intdiv($s, 3600) . 'h ago'; }
    if ($s < 604800) { return intdiv($s, 86400) . 'd ago'; }
    return date('M j', strtotime($ts));
}
function time_range(string $a, string $b = ''): string { return fmt_time($a) . ($b !== '' ? ' – ' . fmt_time($b) : ''); }
function days_label(array $d): string {
    sort($d);
    if ($d === [1, 2, 3, 4, 5]) { return 'Weekdays'; }
    if ($d === [0, 6])          { return 'Weekends'; }
    if (count($d) === 7)        { return 'Every day'; }
    return 'Every ' . implode(', ', array_map(fn($x) => DAY_NAMES[$x] ?? '', $d));
}

// ---- Online meetings ----
function upcoming_meetings(array $all, string $today): array {
    $list = array_values(array_filter($all, fn($m) => $m['date'] >= $today));
    usort($list, fn($a, $b) => strcmp($a['date'].$a['time'], $b['date'].$b['time']));
    return $list;
}
function when_chip(string $date, string $today): string {
    $diff = (int)round((strtotime($date) - strtotime($today)) / 86400);
    return $diff === 0 ? 'Today' : ($diff === 1 ? 'Tomorrow' : '');
}
function plural(int $n, string $one, ?string $many = null): string { return $n . ' ' . ($n === 1 ? $one : ($many ?? $one . 's')); }

// ---- To-do helpers ----
/** Returns [deadline text, is overdue]. */
function task_due(array $t): array {
    if (empty($t['date'])) { return [$t['when'] ?? 'No deadline', false]; }
    $time = $t['time'] ?? '';
    $ts   = strtotime($t['date'] . ' ' . ($time ?: '23:59'));
    $txt  = date('D, M j', strtotime($t['date'])) . ($time ? ' · ' . date('g:i A', $ts) : '');
    return [$txt, empty($t['done']) && $ts < time()];
}
