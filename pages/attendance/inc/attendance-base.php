<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../auth/roles.php';

// Philippine time dapat ang gamit ng PHP. Kapag Europe/Berlin ang default
// (gaya sa XAMPP), mali ang date('Y-m-d') at nagmumukhang Upcoming pa ang
// event na exact date na o nagsimula na.
date_default_timezone_set('Asia/Manila');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();

/* ------------------------------------------------------------------
   Mga link sa loob ng site
   Nakabahagi ang pages sa student, rep at admin folders, kaya
   kinukuha natin ang base URL mula sa script location para isa lang
   ang formula ng links (walang pabalik-balik na ../../).
------------------------------------------------------------------ */
$attendanceScriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
$pagesPosition = strpos($attendanceScriptDir, '/pages');
$attendanceWebRoot = $pagesPosition === false
    ? rtrim($attendanceScriptDir, '/')
    : substr($attendanceScriptDir, 0, $pagesPosition);
$attendancePages = $attendanceWebRoot . '/pages';
$attendanceBase = $attendancePages . '/attendance';

$attendanceAssets = $attendanceBase . '/assets/attendance-icons';
$attendanceImages = $attendanceBase . '/assets';

/* ------------------------------------------------------------------
   Sino ang naka-login at ano ang role
   student -> normal na estudyante
   rep     -> estudyanteng naka-flag na class rep (block rep)
   admin   -> admin account
------------------------------------------------------------------ */
if (empty($_SESSION['authenticated']) || empty($_SESSION['user_type'])) {
    header('Location: ' . $attendanceWebRoot . '/auth/login.php');
    exit;
}

$viewerType = strtolower((string) ($_SESSION['user_type'] ?? ''));
$viewerId = (int) ($_SESSION['user_id'] ?? 0);
$viewerName = trim((string) ($_SESSION['user_name'] ?? ''));
$viewerEmail = (string) ($_SESSION['user_email'] ?? '');
$studentRecord = null;
$attendanceRole = 'student';

try {
    $userContext = currentUserContext();
} catch (RuntimeException $exception) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . $attendanceWebRoot . '/auth/login.php');
    exit;
}

if (in_array('admin', $userContext['roles'], true)) {
    $attendanceRole = 'admin';
    $roleLabel = 'Administrator';
} else {
    $studentStatement = $pdo->prepare(
        'SELECT s.*, b.classBlock, b.courseID, c.courseName
         FROM tblstudent s
         LEFT JOIN tblblock b ON b.blockID = s.blockID
         LEFT JOIN tblcourse c ON c.courseID = b.courseID
         WHERE s.studentID = ? AND s.archivedAt IS NULL'
    );
    $studentStatement->execute([$viewerId]);
    $studentRecord = $studentStatement->fetch() ?: null;

    if ($studentRecord === null) {
        $_SESSION = [];
        session_destroy();
        header('Location: ' . $attendanceWebRoot . '/auth/login.php');
        exit;
    }

    $viewerName = trim(
        (string) $studentRecord['studentFirstName'] . ' ' .
        (string) $studentRecord['studentLastName']
    );
    $roleLabel = 'Student · ' . htmlspecialchars((string) ($studentRecord['classBlock'] ?? ''), ENT_QUOTES, 'UTF-8');

    if (in_array('block_rep', $userContext['roles'], true)) {
        $attendanceRole = 'rep';
        $roleLabel = 'Block Representative · ' . htmlspecialchars((string) ($studentRecord['classBlock'] ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

/* ------------------------------------------------------------------
   Helpers
------------------------------------------------------------------ */
function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function attendance_guard(array|string $allowedRole): void
{
    global $attendanceRole, $attendanceBase;

    $allowedRoles = is_array($allowedRole) ? $allowedRole : [$allowedRole];
    if (in_array($attendanceRole, $allowedRoles, true)) {
        return;
    }

    if ($attendanceRole === 'admin') {
        header('Location: ' . $attendanceBase . '/admin/campus-event.php');
        exit;
    }

    if ($attendanceRole === 'rep') {
        header('Location: ' . $attendanceBase . '/rep/event-attendance.php');
        exit;
    }

    header('Location: ' . $attendanceBase . '/student/campus-event.php');
    exit;
}

function attendance_flash(string $type, string $message): void
{
    $_SESSION['attendance_flash'] = ['type' => $type, 'message' => $message];
}

function attendance_take_flash(): ?array
{
    $flash = $_SESSION['attendance_flash'] ?? null;
    unset($_SESSION['attendance_flash']);

    return is_array($flash) ? $flash : null;
}

function attendance_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function attendance_token(): string
{
    if (empty($_SESSION['attendance_csrf'])) {
        $_SESSION['attendance_csrf'] = bin2hex(random_bytes(16));
    }

    return (string) $_SESSION['attendance_csrf'];
}

function attendance_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(attendance_token()) . '">';
}

function attendance_check_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $sent = (string) ($_POST['csrf'] ?? '');
    if ($sent === '' || !hash_equals(attendance_token(), $sent)) {
        http_response_code(419);
        exit('Your session expired. Please go back and refresh the page.');
    }
}

function attendance_money(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

function attendance_long_date(?string $date): string
{
    if (empty($date)) {
        return '-';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('M j, Y', $timestamp) : (string) $date;
}

function attendance_time(?string $time): string
{
    if (empty($time)) {
        return '-';
    }

    $timestamp = strtotime($time);
    return $timestamp ? date('g:i A', $timestamp) : (string) $time;
}

function attendance_datetime(?string $value): string
{
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('M j, Y g:i A', $timestamp) : (string) $value;
}

function attendance_student_name(array $row): string
{
    $middle = trim((string) ($row['studentMiddleName'] ?? ''));
    $extension = trim((string) ($row['studentExtensionName'] ?? ''));
    $name = trim((string) ($row['studentLastName'] ?? '') . ', ' . (string) ($row['studentFirstName'] ?? ''));

    if ($middle !== '') {
        $name .= ' ' . strtoupper(substr($middle, 0, 1)) . '.';
    }

    if ($extension !== '') {
        $name .= ' ' . $extension;
    }

    return $name;
}

function attendance_student_id(array $row): string
{
    $id = (int) ($row['studentID'] ?? 0);

    return $id > 0 ? (string) $id : '-';
}

/* Maliit na linya sa ilalim ng pangalan: block tapos student ID. */
function attendance_student_sub(array $row): string
{
    $parts = [];
    $block = trim((string) ($row['classBlock'] ?? ''));

    if ($block !== '') {
        $parts[] = $block;
    }

    $id = attendance_student_id($row);
    if ($id !== '-') {
        $parts[] = 'ID ' . $id;
    }

    return h(implode(' · ', $parts));
}

function attendance_admin_name(int $adminId): string
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT CONCAT(a.adminLastName, ", ", a.adminFirstName) AS fullName
                            FROM tbladmin a WHERE a.adminID = ?');
    $stmt->execute([$adminId]);
    $row = $stmt->fetch();

    return $row ? (string) $row['fullName'] : '-';
}

function attendance_marker_name(array $record): string
{
    $adminName = trim(
        (string) ($record['markerAdminFirstName'] ?? '') . ' ' .
        (string) ($record['markerAdminLastName'] ?? '')
    );
    if ($adminName !== '') {
        return 'Administrator: ' . $adminName;
    }

    $studentName = trim(
        (string) ($record['markerStudentFirstName'] ?? '') . ' ' .
        (string) ($record['markerStudentLastName'] ?? '')
    );
    if ($studentName !== '') {
        return 'Block representative: ' . $studentName;
    }

    return empty($record['attendanceStatus']) ? 'Not recorded' : 'System';
}

function attendance_payment_processor(array $payment): string
{
    $adminName = trim(
        (string) ($payment['processorAdminFirstName'] ?? '') . ' ' .
        (string) ($payment['processorAdminLastName'] ?? '')
    );
    if ($adminName !== '') {
        return 'Administrator: ' . $adminName;
    }

    $studentName = trim(
        (string) ($payment['processorStudentFirstName'] ?? '') . ' ' .
        (string) ($payment['processorStudentLastName'] ?? '')
    );
    if ($studentName !== '') {
        return 'Block representative: ' . $studentName;
    }

    if (!empty($payment['receivedByAdminFirstName'])) {
        $receiverName = trim(
            (string) $payment['receivedByAdminFirstName'] . ' ' .
            (string) ($payment['receivedByAdminLastName'] ?? '')
        );
        if ($receiverName !== '') {
            return 'Administrator: ' . $receiverName;
        }
    }

    if ((string) ($payment['paymentStatus'] ?? '') === 'Pending Cash') {
        return 'Awaiting block representative';
    }
    if ((string) ($payment['paymentStatus'] ?? '') === 'For Verification') {
        return 'Awaiting administrator or block representative';
    }

    return 'Not recorded';
}

function attendance_badge(string $status): string
{
    $key = strtolower(trim($status));
    $class = 'badge';

    if (in_array($key, ['upcoming', 'registered', 'pending cash', 'for verification'], true)) {
        $class .= ' upcoming';
    } elseif (in_array($key, ['ongoing', 'present', 'paid', 'verified'], true)) {
        $class .= ' ongoing';
    } else    if (in_array($key, ['ended', 'completed', 'absent', 'unpaid', 'cancelled', 'declined'], true)) {
        $class .= ' ended';
    } elseif (in_array($key, ['late', 'late payment', 'refund pending', 'time in only'], true)) {
        $class .= ' late';
    } elseif ($key === 'refunded') {
        $class .= ' refunded';
    }

    return '<span class="' . $class . '">' . h($status) . '</span>';
}

function attendance_event_start(array $row): int
{
    $date = (string) ($row['eventDate'] ?? '');

    return $date === '' ? 0 : (int) strtotime($date . ' ' . (string) ($row['eventStartTime'] ?? '00:00:00'));
}

function attendance_event_started(array $row): bool
{
    $start = attendance_event_start($row);

    return $start > 0 && time() >= $start;
}

/* Wala pang time in = Upcoming habang hindi pa nagsisimula ang event,
   para hindi ma-absent ang students bago pa mag-umpisa. */
function attendance_display_status(array $row): string
{
    $stored = strtolower(trim((string) ($row['attendanceStatus'] ?? '')));

    if (empty($row['timeIn']) && !attendance_event_started($row)) {
        return 'Upcoming';
    }

    /* Nag-time in pero hindi nag-time out at tapos na ang event: Time In Only
       ang label, hindi Present at hindi rin Absent. */
    if (!empty($row['timeIn']) && empty($row['timeOut']) && attendance_event_status($row) === 'Ended') {
        return 'Time In Only';
    }

    return $stored === '' ? 'Absent' : ucfirst($stored);
}

function attendance_status_counts(array $rows): array
{
    $counts = ['present' => 0, 'late' => 0, 'absent' => 0, 'upcoming' => 0];

    foreach ($rows as $row) {
        $key = strtolower(attendance_display_status($row));

        // Time in only = nakapasok pa rin, kaya counted as present sa totals
        if ($key === 'time in only') {
            $key = 'present';
        }

        if (isset($counts[$key])) {
            $counts[$key]++;
        }
    }

    return $counts;
}

/* ------------------------------------------------------------------
   Sino ang pwedeng sumali at kailan pwedeng magbayad
   Kapag NULL ang courseID, open ang event sa lahat ng course.
   Kapag may courseID, students ng course na yun lang ang makakakita
   at makakapag-register.
------------------------------------------------------------------ */
function attendance_event_course(array $event): int
{
    return (int) ($event['courseID'] ?? 0);
}

function attendance_event_audience(array $event): string
{
    $name = trim((string) ($event['courseName'] ?? ''));

    return attendance_event_course($event) > 0 && $name !== '' ? $name : 'All courses';
}

function attendance_payment_start(array $event): int
{
    $date = (string) ($event['paymentStartDate'] ?? '');

    return $date === '' ? 0 : (int) strtotime($date);
}

function attendance_payment_deadline(array $event): int
{
    $date = (string) ($event['paymentDeadline'] ?? '');

    return $date === '' ? 0 : (int) strtotime($date . ' 23:59:59');
}

/* Bukas ang bayaran sa start date. Pagkalampas ng deadline pwede pa
   ring magbayad, Late Payment lang ang label. */
function attendance_payment_window(array $event): string
{
    $start = attendance_payment_start($event);
    $deadline = attendance_payment_deadline($event);

    if ($start > 0 && time() < $start) {
        return 'not_open';
    }

    if ($deadline > 0 && time() > $deadline) {
        return 'closed';
    }

    return 'open';
}

function attendance_payment_note(array $event): string
{
    $start = attendance_payment_start($event);
    $deadline = attendance_payment_deadline($event);

    if ($start > 0 && time() < $start) {
        return 'Payment opens on ' . attendance_long_date((string) $event['paymentStartDate']) . '.';
    }

    if ($deadline > 0 && time() > $deadline) {
        return 'Deadline passed on ' . attendance_long_date((string) $event['paymentDeadline']) . '. Payments are recorded as late.';
    }

    if ($deadline > 0) {
        return 'Pay on or before ' . attendance_long_date((string) $event['paymentDeadline']) . '.';
    }

    return '';
}

/* Ang hindi pa bayad tapos lumampas sa deadline, Late Payment ang
   label (hindi Overdue) kasi pwede pa namang bayaran. */
function attendance_payment_status(array $row): string
{
    $stored = trim((string) ($row['paymentStatus'] ?? ''));
    if ($stored === '') {
        $stored = 'Unpaid';
    }

    // Cancelled na event: hindi na pwedeng bayaran. Kung unpaid pa,
    // Cancelled ang ipapakita para hindi maguluhan ang student.
    if (strcasecmp(trim((string) ($row['eventStatus'] ?? '')), 'Cancelled') === 0
        && in_array($stored, ['Unpaid', 'Declined', 'Overdue'], true)) {
        return 'Cancelled';
    }

    // Refunded = nag-cancel ang event tapos naibalik na yung bayad
    // Refund Pending = na-cancel ang event, hinihintay pa ang approval ng refund
    if (in_array($stored, ['Paid', 'Pending Cash', 'For Verification', 'Refund Pending', 'Refunded'], true)) {
        return $stored;
    }

    $deadline = attendance_payment_deadline($row);
    if ($deadline > 0 && time() > $deadline) {
        return 'Late Payment';
    }

    return $stored === 'Overdue' ? 'Unpaid' : $stored;
}

function attendance_payment_counts(array $rows): array
{
    $counts = ['paid' => 0, 'unpaid' => 0, 'pending' => 0, 'late' => 0];

    foreach ($rows as $row) {
        $status = attendance_payment_status($row);

        if ($status === 'Paid') {
            $counts['paid']++;
            continue;
        }

        $counts['unpaid']++;

        if ($status === 'Late Payment') {
            $counts['late']++;
        } elseif (in_array($status, ['Pending Cash', 'For Verification'], true)) {
            $counts['pending']++;
        }
    }

    return $counts;
}

/* ------------------------------------------------------------------
   Listahan ng courses
   Ginagamit sa "Open to" field ng event form para hindi na kailangan
   i-type pa ng admin ang course.
------------------------------------------------------------------ */
function attendance_program_guide(): array
{
    return [
        ['code' => 'BSCS', 'name' => 'Bachelor of Science in Computer Science (BSCS)'],
        ['code' => 'BSIT', 'name' => 'Bachelor of Science in Information Technology (BSIT)'],
        ['code' => 'BSEMC', 'name' => 'Bachelor of Science in Entertainment and Multimedia Computing (BSEMC)'],
        ['code' => 'ACT', 'name' => 'Associate in Computer Technology (ACT)'],
    ];
}

function attendance_program_guide_html(): string
{
    $html = '<div class="courseGuide"><span class="courseGuideTitle">Course guide</span><div class="courseGuideGrid">';

    foreach (attendance_program_guide() as $program) {
        $html .= '<span class="courseGuideItem">' . h($program['name']) . '</span>';
    }

    return $html . '</div></div>';
}

/* Oras ng server palagi ang time in / time out. Isang attendance row
   lang kada registration, kaya either gumagawa tayo ng bagong row o
   dinadagdagan yung kulang na oras. */
function attendance_mark(array $record, string $action, ?int $adminId, ?int $studentId = null): array
{
    global $pdo;

    if (($adminId === null) === ($studentId === null)) {
        return ['error', 'The attendance marker could not be identified.'];
    }

    $registrationId = (int) $record['registrationID'];
    $attendanceId = $record['attendanceID'] !== null ? (int) $record['attendanceID'] : 0;

    if ($action === 'mark_in') {
        if (!empty($record['timeIn'])) {
            return ['error', 'Time in was already recorded and cannot be edited.'];
        }

        /* 2 oras na grace period pagkatapos ng exact start time bago mag-late */
        $isLate = strtotime((string) $pdo->query('SELECT NOW()')->fetchColumn()) > attendance_event_start($record) + (2 * 60 * 60);
        $status = $isLate ? 'late' : 'present';

        if ($attendanceId === 0) {
            $insert = $pdo->prepare(
                'INSERT INTO tbleventattendance
                    (registrationID, markedByAdminID, markedByStudentID, attendanceStatus, timeIn, markedAt)
                 VALUES (?, ?, ?, ?, NOW(), NOW())'
            );
            $insert->execute([$registrationId, $adminId, $studentId, $status]);
        } else {
            $update = $pdo->prepare(
                'UPDATE tbleventattendance
                 SET markedByAdminID = ?, markedByStudentID = ?, attendanceStatus = ?, timeIn = NOW(), markedAt = NOW()
                 WHERE attendanceID = ? AND timeIn IS NULL'
            );
            $update->execute([$adminId, $studentId, $status, $attendanceId]);
        }

        return ['success', $isLate ? 'Time in saved. Marked late.' : 'Time in saved. On time.'];
    }

    if ($action === 'mark_out') {
        if (empty($record['timeIn'])) {
            return ['error', 'Record the time in first before the time out.'];
        }

        if (!empty($record['timeOut'])) {
            return ['error', 'Time out was already recorded and cannot be edited.'];
        }

        $update = $pdo->prepare(
            'UPDATE tbleventattendance
             SET timeOut = NOW(), markedByAdminID = ?, markedByStudentID = ?, markedAt = NOW()
             WHERE attendanceID = ? AND timeOut IS NULL'
        );
        $update->execute([$adminId, $studentId, $attendanceId]);

        return ['success', 'Time out saved.'];
    }

    /* Undo ng time in, para sa maling pindot. Bawal kung may time out na,
       at binubura din yung attendance row para balik sa walang record. */
    if ($action === 'undo_in') {
        if (empty($record['timeIn'])) {
            return ['error', 'There is no time in to undo.'];
        }

        if (!empty($record['timeOut'])) {
            return ['error', 'The time in can no longer be undone because a time out is already recorded.'];
        }

        if ($attendanceId === 0) {
            return ['error', 'The attendance record could not be found.'];
        }

        $delete = $pdo->prepare('DELETE FROM tbleventattendance WHERE attendanceID = ? AND timeOut IS NULL');
        $delete->execute([$attendanceId]);

        return ['success', 'Time in undone. You can time this student in again.'];
    }

    if ($action === 'mark_absent') {
        if (!empty($record['timeIn'])) {
            return ['error', 'This student already has a time in, so the record cannot be marked absent.'];
        }

        if ($attendanceId === 0) {
            $insert = $pdo->prepare(
                'INSERT INTO tbleventattendance
                    (registrationID, markedByAdminID, markedByStudentID, attendanceStatus, markedAt)
                 VALUES (?, ?, ?, ?, NOW())'
            );
            $insert->execute([$registrationId, $adminId, $studentId, 'absent']);
        } else {
            $update = $pdo->prepare(
                'UPDATE tbleventattendance
                 SET attendanceStatus = ?, markedByAdminID = ?, markedByStudentID = ?, markedAt = NOW()
                 WHERE attendanceID = ? AND timeIn IS NULL'
            );
            $update->execute(['absent', $adminId, $studentId, $attendanceId]);
        }

        return ['success', 'Student marked absent.'];
    }

    return ['error', 'Unknown action.'];
}

/* Apat lang ang status ng event: Upcoming, Ongoing, Ended, Cancelled.
   Base sa date + start/end time, kaya kapag exact date na at nagsimula
   na ang event, Ongoing na agad — hindi na Upcoming.
   Ang lumang 'Completed' na naka-save ay itinuturing na Ended. */
function attendance_event_status(array $event): string
{
    if (strcasecmp(trim((string) ($event['eventStatus'] ?? '')), 'Cancelled') === 0) {
        return 'Cancelled';
    }

    $date = trim((string) ($event['eventDate'] ?? ''));
    if ($date === '') {
        return 'Upcoming';
    }

    $startTime = trim((string) ($event['eventStartTime'] ?? ''));
    $endTime = trim((string) ($event['eventEndTime'] ?? ''));

    $start = strtotime($date . ' ' . ($startTime !== '' ? $startTime : '00:00:00'));
    $end = strtotime($date . ' ' . ($endTime !== '' ? $endTime : '23:59:59'));

    if ($start === false || $end === false) {
        return 'Upcoming';
    }

    // Kapag lampas hatinggabi ang end time (hal. 22:00 - 02:00), kinabukasan na ito natatapos
    if ($endTime !== '' && $end !== false && $end < $start) {
        $end = strtotime('+1 day', $end);
    }

    $now = time();

    if ($now < $start) {
        return 'Upcoming';
    }

    if ($now <= $end) {
        return 'Ongoing';
    }

    return 'Ended';
}

/* I-register lahat ng student sa course ng event (para sa required event).
   Ginagamit ng admin button na 'Register all students'. */
function attendance_register_all_students(int $eventId): int
{
    global $pdo;

    $eventStatement = $pdo->prepare(
        'SELECT eventID, courseID, participationFee, archivedAt FROM tblevent WHERE eventID = ?'
    );
    $eventStatement->execute([$eventId]);
    $event = $eventStatement->fetch();

    if ($event === false || $event['archivedAt'] !== null) {
        return 0;
    }

    $fee = (float) $event['participationFee'];
    $courseId = $event['courseID'] !== null ? (int) $event['courseID'] : 0;

    $studentStatement = $pdo->prepare(
        'SELECT s.studentID
         FROM tblstudent s
         LEFT JOIN tblblock b ON b.blockID = s.blockID
         WHERE (? = 0 OR b.courseID = ?)'
    );
    $studentStatement->execute([$courseId, $courseId]);
    $students = $studentStatement->fetchAll();

    $count = 0;
    foreach ($students as $student) {
        $studentId = (int) $student['studentID'];

        $check = $pdo->prepare(
            'SELECT registrationID FROM tbleventregistration WHERE eventID = ? AND studentID = ?'
        );
        $check->execute([$eventId, $studentId]);
        if ($check->fetchColumn()) {
            continue;
        }

        $pdo->beginTransaction();
        $insert = $pdo->prepare(
            'INSERT INTO tbleventregistration (eventID, studentID, registrationStatus) VALUES (?, ?, ?)'
        );
        $insert->execute([$eventId, $studentId, 'Registered']);
        $registrationId = (int) $pdo->lastInsertId();

        if ($fee > 0) {
            $payment = $pdo->prepare(
                'INSERT INTO tbleventpayment (registrationID, amountDue, amountPaid, paymentStatus, paymentRemarks)
                 VALUES (?, ?, 0.00, ?, ?)'
            );
            $payment->execute([
                $registrationId,
                $fee,
                'Unpaid',
                'Registered by admin for required event',
            ]);
        }

        $pdo->commit();
        $count++;
    }

    return $count;
}

/* ------------------------------------------------------------------
   Layout ng buong page (sidebar, header, tabs)
------------------------------------------------------------------ */
function attendance_page_start(string $activeTab): void
{
    global $attendanceBase, $attendancePages, $attendanceAssets, $attendanceImages, $attendanceWebRoot;
    global $viewerName, $viewerEmail, $roleLabel, $attendanceRole;

    $tabs = attendance_tabs($attendanceRole);
    $flash = attendance_take_flash();

    ?>
<!DOCTYPE html>
<html lang="en" data-sidebar="closed">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance | ComfySci</title>
    <?php $cssPath = __DIR__ . '/../assets/attendance.css'; ?>
    <link rel="stylesheet" href="<?= h($attendancePages) ?>/assets/theme.css?v=1">
    <link rel="stylesheet" href="<?= h($attendanceBase) ?>/assets/attendance.css?v=<?= is_file($cssPath) ? (int) filemtime($cssPath) : 1 ?>">
    <link rel="stylesheet" href="<?= h($attendancePages) ?>/assets/sidebar.css?v=1">
</head>
<body class="comfsci-shell">
<div class="app">

    <?php require __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main">
        <header class="topBar">
            <h1>Attendance</h1>

            <label class="searchBox">
                <span class="icon"><img src="<?= h($attendanceImages) ?>/searchIcon.png" alt=""></span>
                <input type="search" placeholder="Search...">
            </label>

            <button class="iconBtn" type="button" aria-label="Settings">
                <span class="icon"><img src="<?= h($attendanceImages) ?>/settingsIcon.png" alt=""></span>
            </button>

            <button class="iconBtn" type="button" aria-label="Notifications">
                <span class="icon"><img src="<?= h($attendanceImages) ?>/notificationsIcon.png" alt=""></span>
                <span class="dot"></span>
            </button>
        </header>

        <main class="page">
            <section class="pageBanner">
                <div class="bannerIcon">
                    <img src="<?= h($attendanceAssets) ?>/calendar.png" alt="">
                </div>
                <div>
                    <h2>Attendance &amp; Campus Events</h2>
                    <p>View upcoming events, check your attendance records, manage your event payments, and stay updated with the latest announcements.</p>
                </div>
            </section>

            <nav class="tabBar">
                <?php foreach ($tabs as $key => $tab): ?>
                    <a class="<?= $key === $activeTab ? 'active' : '' ?>" href="<?= h($tab['url']) ?>">
                        <img src="<?= h($attendanceAssets . '/' . $tab['icon']) ?>" alt="">
                        <?= h($tab['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if ($flash !== null): ?>
                <div class="flash <?= h((string) $flash['type']) ?>"><?= h((string) $flash['message']) ?></div>
            <?php endif; ?>
    <?php
}

function attendance_page_end(): void
{
    global $attendanceAssets;
    ?>
        </main>
    </div>
</div>

<script>
(function () {
    // Popup dialogs (event details, payment form, create forms)
    function closeModal(modal) {
        if (!modal) return;
        modal.hidden = true;
    }

    document.addEventListener('click', function (event) {
        const opener = event.target.closest('[data-modal-open]');
        if (opener) {
            event.preventDefault();
            const modal = document.getElementById(opener.getAttribute('data-modal-open'));
            if (modal) {
                modal.hidden = false;
            }
            return;
        }

        const closer = event.target.closest('[data-modal-close]');
        if (closer) {
            event.preventDefault();
            closeModal(closer.closest('.modal'));
            return;
        }

        if (event.target.classList.contains('modalBackdrop')) {
            closeModal(event.target.closest('.modal'));
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.modal:not([hidden])').forEach(closeModal);
        }
    });

    // Announcement details panel
    const detailTitle = document.getElementById('detailTitle');
    const detailMeta = document.getElementById('detailMeta');
    const detailBody = document.getElementById('detailBody');
    const remindersBox = document.getElementById('importantReminders');
    const remindersDefault = remindersBox ? remindersBox.innerHTML : '';

    // Kapag event ang pinindot, announcements ng event na yun ang nasa Important reminders
    function showEventReminders(button) {
        if (!remindersBox) return;

        let reminders = [];
        const raw = button.getAttribute('data-reminders');
        if (raw) {
            try {
                reminders = JSON.parse(raw) || [];
            } catch (error) {
                reminders = [];
            }
        }

        if (reminders.length === 0) {
            remindersBox.innerHTML = remindersDefault;
            return;
        }

        const list = document.createElement('ul');
        reminders.forEach(function (reminder) {
            const entry = document.createElement('li');
            const title = document.createElement('strong');
            title.textContent = reminder.title || '';
            entry.appendChild(title);

            if (reminder.content) {
                const text = document.createElement('p');
                text.className = 'reminderText';
                text.textContent = reminder.content;
                entry.appendChild(text);
            }

            list.appendChild(entry);
        });

        remindersBox.innerHTML = '';
        remindersBox.appendChild(list);
    }

    document.querySelectorAll('[data-announcement]').forEach(function (button) {
        button.addEventListener('click', function () {
            document.querySelectorAll('[data-announcement]').forEach(function (item) {
                item.classList.remove('selected');
            });
            button.classList.add('selected');

            if (detailTitle) detailTitle.textContent = button.getAttribute('data-title') || '';
            if (detailMeta) detailMeta.textContent = button.getAttribute('data-meta') || '';
            if (detailBody) detailBody.textContent = button.getAttribute('data-body') || '';

            showEventReminders(button);
        });
    });

    // Payment method: only Online Payment needs a reference number
    document.querySelectorAll('[data-payment-form]').forEach(function (form) {
        const methodFields = form.querySelectorAll('input[name="method"]');
        const referenceField = form.querySelector('[data-reference-field]');
        const referenceInput = form.querySelector('input[name="reference"]');
        const dueField = form.querySelector('[data-due-field]');
        const registrationSelect = form.querySelector('select[name="registration_id"]');

        function updateMethod() {
            const checked = form.querySelector('input[name="method"]:checked');
            const isGcash = checked && checked.value === 'gcash';

            if (referenceField) referenceField.hidden = !isGcash;
            if (referenceInput) referenceInput.required = isGcash;
        }

        function updateDue() {
            if (!registrationSelect || !dueField) return;
            const option = registrationSelect.options[registrationSelect.selectedIndex];
            dueField.textContent = option ? (option.getAttribute('data-due') || '') : '';
        }

        methodFields.forEach(function (field) {
            field.addEventListener('change', updateMethod);
        });

        // Online Payment reference numbers are digits only
        if (referenceInput) {
            referenceInput.addEventListener('input', function () {
                this.value = this.value.replace(/\D+/g, '').slice(0, 20);
            });
        }

        if (registrationSelect) {
            registrationSelect.addEventListener('change', updateDue);
        }

        updateMethod();
        updateDue();
    });

    // Info boxes: nakatago muna ang laman, lalabas lang kapag pinindot ang title
    document.querySelectorAll('.infoBox').forEach(function (box) {
        const heading = box.querySelector('h4');
        if (!heading) return;

        const panel = Array.prototype.slice.call(box.children).filter(function (child) {
            return child !== heading;
        });
        if (panel.length === 0) return;

        box.classList.add('collapsible');
        heading.setAttribute('role', 'button');
        heading.setAttribute('tabindex', '0');
        heading.setAttribute('aria-expanded', 'false');
        panel.forEach(function (child) { child.hidden = true; });

        function toggleInfo() {
            const open = box.classList.toggle('open');
            heading.setAttribute('aria-expanded', open ? 'true' : 'false');
            panel.forEach(function (child) { child.hidden = !open; });
        }

        heading.addEventListener('click', toggleInfo);
        heading.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggleInfo();
            }
        });
    });
})();
</script>
</body>
</html>
    <?php
}

/* ------------------------------------------------------------------
   Pagination ng mga table: 10 rows kada page, tapos Next o page number
   para makita ang kasunod. Filter links din ang dala ng bawat page.
------------------------------------------------------------------ */
function attendance_per_page(): int
{
    return 10;
}

function attendance_paginate(int $totalRows, ?int $perPage = null): array
{
    $perPage = $perPage ?? attendance_per_page();
    $totalPages = max(1, (int) ceil($totalRows / max(1, $perPage)));

    $page = (int) ($_GET['page'] ?? 1);
    if ($page < 1) {
        $page = 1;
    } elseif ($page > $totalPages) {
        $page = $totalPages;
    }

    return [
        'page' => $page,
        'totalPages' => $totalPages,
        'perPage' => $perPage,
        'offset' => ($page - 1) * $perPage,
        'total' => $totalRows,
    ];
}

function attendance_pagination(string $url, array $query, int $page, int $totalPages): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $queryString = http_build_query($query);
    $separator = $queryString === '' ? '' : '&';

    $link = static function (int $target) use ($url, $queryString, $separator): string {
        return h($url . '?' . $queryString . $separator . 'page=' . $target);
    };

    // Sampung page number lang ang nakikita, tapos ... papunta sa huling page
    $window = 10;
    $start = max(1, min($page - 4, $totalPages - $window + 1));
    $end = min($totalPages, $start + $window - 1);

    $html = '<nav class="pagination" aria-label="Pages">';

    $html .= $page > 1
        ? '<a class="pageStep" href="' . $link($page - 1) . '">Prev</a>'
        : '<span class="pageStep disabled">Prev</span>';

    if ($start > 1) {
        $html .= '<a class="pageNumber" href="' . $link(1) . '">1</a>';
        if ($start > 2) {
            $html .= '<span class="pageGap">...</span>';
        }
    }

    for ($number = $start; $number <= $end; $number++) {
        $html .= $number === $page
            ? '<span class="pageNumber active" aria-current="page">' . $number . '</span>'
            : '<a class="pageNumber" href="' . $link($number) . '">' . $number . '</a>';
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<span class="pageGap">...</span>';
        }
        $html .= '<a class="pageNumber" href="' . $link($totalPages) . '">' . $totalPages . '</a>';
    }

    $html .= $page < $totalPages
        ? '<a class="pageStep" href="' . $link($page + 1) . '">Next</a>'
        : '<span class="pageStep disabled">Next</span>';

    return $html . '</nav>';
}

function attendance_tabs(string $role): array
{
    global $attendanceBase;

    if ($role === 'rep') {
        return [
            'events' => [
                'label' => 'Campus Events',
                'icon' => 'calendar.png',
                'url' => $attendanceBase . '/student/campus-event.php',
            ],
            'attendance' => [
                'label' => 'Event Attendance',
                'icon' => 'clipboard-check.png',
                'url' => $attendanceBase . '/rep/event-attendance.php',
            ],
            'payment' => [
                'label' => 'Event Payment',
                'icon' => 'card.png',
                'url' => $attendanceBase . '/rep/event-payment.php',
            ],
            'announcements' => [
                'label' => 'Event Announcements',
                'icon' => 'bell.png',
                'url' => $attendanceBase . '/student/event-announcement.php',
            ],
        ];
    }

    if ($role === 'admin') {
        return [
            'events' => [
                'label' => 'Campus Events',
                'icon' => 'calendar.png',
                'url' => $attendanceBase . '/admin/campus-event.php',
            ],
            'attendance' => [
                'label' => 'Event Attendance',
                'icon' => 'clipboard-check.png',
                'url' => $attendanceBase . '/admin/event-attendance.php',
            ],
            'payment' => [
                'label' => 'Event Payment',
                'icon' => 'card.png',
                'url' => $attendanceBase . '/admin/event-payment.php',
            ],
            'reps' => [
                'label' => 'Block Reps',
                'icon' => 'users.png',
                'url' => $attendanceBase . '/admin/block-rep.php',
            ],
            'announcements' => [
                'label' => 'Event Announcements',
                'icon' => 'bell.png',
                'url' => $attendanceBase . '/admin/event-announcement.php',
            ],
        ];
    }

    return [
        'events' => [
            'label' => 'Campus Events',
            'icon' => 'calendar.png',
            'url' => $attendanceBase . '/student/campus-event.php',
        ],
        'attendance' => [
            'label' => 'Event Attendance',
            'icon' => 'clipboard-check.png',
            'url' => $attendanceBase . '/student/event-attendance.php',
        ],
        'payment' => [
            'label' => 'Event Payment',
            'icon' => 'card.png',
            'url' => $attendanceBase . '/student/event-payment.php',
        ],
        'announcements' => [
            'label' => 'Event Announcements',
            'icon' => 'bell.png',
            'url' => $attendanceBase . '/student/event-announcement.php',
        ],
    ];
}
