<?php
/**
 * Simple config-driven CRUD for db_comfysci.
 * Uses the db() helper from database.php.
 */
require_once __DIR__ . '/config/database.php';
$pdo = db();

/*
 * Field format: 'column' => [label, type, options]
 * Types: text, textarea, email, password, number, date, time, datetime, checkbox, select
 * Options: null (allow NULL), default, step, ref [table, idCol, labelSQL], options [..]
 */
$adminRef   = ['tbladmin', 'adminID', "CONCAT(adminFirstName,' ',adminLastName)"];
$blockRef   = ['tblblock', 'blockID', 'classBlock'];
$courseRef  = ['tblcourse', 'courseID', 'courseName'];
$facultyRef = ['tblfaculty', 'facultyID', "CONCAT(facultyFirstName,' ',facultyLastName)"];
$quizRef    = ['tblquiz', 'quizID', 'quizTitle'];
$questRef   = ['tblquestion', 'questionID', "CONCAT(questionID,' - ',LEFT(questionText,50))"];

$tables = [
    'tblcourse' => ['label' => 'Courses', 'pk' => 'courseID', 'fields' => [
        'courseName'     => ['Course Name', 'text'],
        'curriculumName' => ['Curriculum Name', 'text'],
    ]],
    'tblblock' => ['label' => 'Blocks', 'pk' => 'blockID', 'fields' => [
        'classBlock' => ['Class Block', 'text'],
        'courseID'   => ['Course', 'select', ['ref' => $courseRef]],
        'facultyID'  => ['Faculty', 'select', ['ref' => $facultyRef, 'null' => true]],
    ]],
    'tblstudent' => ['label' => 'Students', 'pk' => 'studentID', 'fields' => [
        'studentLastName'      => ['Last Name', 'text'],
        'studentFirstName'     => ['First Name', 'text'],
        'studentMiddleName'    => ['Middle Name', 'text', ['null' => true]],
        'studentExtensionName' => ['Extension', 'text', ['null' => true]],
        'studentEmail'         => ['Email', 'email'],
        'studentPassword'      => ['Password', 'password'],
        'isClassRep'           => ['Class Rep', 'checkbox'],
        'isGroupLeader'        => ['Group Leader', 'checkbox'],
        'blockID'              => ['Block', 'select', ['ref' => $blockRef]],
    ]],
    'tblfaculty' => ['label' => 'Faculty', 'pk' => 'facultyID', 'fields' => [
        'facultyLastName'      => ['Last Name', 'text'],
        'facultyFirstName'     => ['First Name', 'text'],
        'facultyMiddleName'    => ['Middle Name', 'text', ['null' => true]],
        'facultyExtensionName' => ['Extension', 'text', ['null' => true]],
        'facultyEmail'         => ['Email', 'email'],
        'facultyPassword'      => ['Password', 'password'],
    ]],
    'tbladmin' => ['label' => 'Admins', 'pk' => 'adminID', 'fields' => [
        'adminLastName'      => ['Last Name', 'text'],
        'adminFirstName'     => ['First Name', 'text'],
        'adminMiddleName'    => ['Middle Name', 'text', ['null' => true]],
        'adminExtensionName' => ['Extension', 'text', ['null' => true]],
        'adminEmail'         => ['Email', 'email'],
        'adminPassword'      => ['Password', 'password'],
    ]],
    'tblannouncement' => ['label' => 'Announcements', 'pk' => 'announcementID', 'fields' => [
        'adminID'             => ['Posted By', 'select', ['ref' => $adminRef]],
        'announcementTitle'   => ['Title', 'text'],
        'announcementContent' => ['Content', 'textarea'],
    ]],
    'tblassignment' => ['label' => 'Assignments', 'pk' => 'assignmentID', 'fields' => [
        'blockID'               => ['Block', 'select', ['ref' => $blockRef]],
        'assignmentTitle'       => ['Title', 'text'],
        'assignmentSubject'     => ['Subject', 'text'],
        'assignmentDescription' => ['Description', 'textarea', ['null' => true]],
        'assignmentFilePath'    => ['File Path', 'text', ['null' => true]],
        'assignmentStatus'      => ['Status', 'text', ['default' => 'Active']],
        'assignmentDeadline'    => ['Deadline', 'datetime'],
        'isEmailSent'           => ['Email Sent', 'checkbox'],
    ]],
    'tblevent' => ['label' => 'Events', 'pk' => 'eventID', 'fields' => [
        'adminID'           => ['Created By', 'select', ['ref' => $adminRef]],
        'eventTitle'        => ['Title', 'text'],
        'eventDescription'  => ['Description', 'textarea', ['null' => true]],
        'eventDate'         => ['Date', 'date'],
        'eventStartTime'    => ['Start Time', 'time'],
        'eventEndTime'      => ['End Time', 'time', ['null' => true]],
        'eventLocation'     => ['Location', 'text'],
        'participationFee'  => ['Participation Fee', 'number', ['step' => '0.01', 'default' => '0.00']],
        'eventStatus'       => ['Status', 'select', ['options' => ['Upcoming', 'Ongoing', 'Completed', 'Cancelled']]],
    ]],
    'tblquiz' => ['label' => 'Quizzes', 'pk' => 'quizID', 'fields' => [
        'adminID'         => ['Created By', 'select', ['ref' => $adminRef]],
        'quizTitle'       => ['Title', 'text'],
        'quizDescription' => ['Description', 'textarea', ['null' => true]],
        'quizType'        => ['Type', 'text'],
        'isPublic'        => ['Public', 'checkbox', ['default' => 1]],
    ]],
    'tblquestion' => ['label' => 'Questions', 'pk' => 'questionID', 'fields' => [
        'quizID'         => ['Quiz', 'select', ['ref' => $quizRef]],
        'questionText'   => ['Question', 'textarea'],
        'questionType'   => ['Type', 'text'],
        'questionPoints' => ['Points', 'number', ['default' => 1]],
    ]],
    'tblanswer' => ['label' => 'Answers', 'pk' => 'answerID', 'fields' => [
        'questionID' => ['Question', 'select', ['ref' => $questRef]],
        'answerText' => ['Answer', 'text'],
        'isCorrect'  => ['Correct', 'checkbox'],
    ]],
];

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function getOptions(PDO $pdo, array $opt): array {
    if (!empty($opt['ref'])) {
        [$tbl, $id, $label] = $opt['ref'];
        return $pdo->query("SELECT $id AS v, $label AS l FROM $tbl ORDER BY l")->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    if (!empty($opt['options'])) {
        return array_combine($opt['options'], $opt['options']);
    }
    return [];
}

$t = $_GET['t'] ?? array_key_first($tables);
if (!isset($tables[$t])) $t = array_key_first($tables);
$cfg    = $tables[$t];
$pk     = $cfg['pk'];
$fields = $cfg['fields'];
$msg = $err = '';

/* ---------- Handle create / update / delete ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    try {
        if ($action === 'delete') {
            $pdo->prepare("DELETE FROM $t WHERE $pk = ?")->execute([$id]);
            $msg = 'Record deleted.';
        } elseif ($action === 'create' || $action === 'update') {
            $cols = [];
            $vals = [];
            foreach ($fields as $name => $f) {
                $type = $f[1];
                $opt  = $f[2] ?? [];
                if ($type === 'checkbox') {
                    $v = isset($_POST[$name]) ? 1 : 0;
                } else {
                    $raw = $_POST[$name] ?? '';
                    if ($type === 'password') {
                        if ($raw === '' && $action === 'update') continue; // keep old password
                        $v = password_hash($raw, PASSWORD_DEFAULT);
                    } else {
                        $v = trim($raw);
                        if ($type === 'datetime') $v = str_replace('T', ' ', $v);
                        if ($v === '' && !empty($opt['null'])) $v = null;
                    }
                }
                $cols[] = $name;
                $vals[] = $v;
            }
            if ($action === 'create') {
                $sql = "INSERT INTO $t (" . implode(',', $cols) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")";
                $pdo->prepare($sql)->execute($vals);
                $msg = 'Record created.';
            } else {
                $sql = "UPDATE $t SET " . implode(',', array_map(fn($c) => "$c = ?", $cols)) . " WHERE $pk = ?";
                $vals[] = $id;
                $pdo->prepare($sql)->execute($vals);
                $msg = 'Record updated.';
            }
        }
    } catch (PDOException $e) {
        $err = $e->getMessage();
    }
}

/* ---------- Load record for editing ---------- */
$editRow = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare("SELECT * FROM $t WHERE $pk = ?");
    $st->execute([(int)$_GET['edit']]);
    $editRow = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/* ---------- Load list ---------- */
$rows = $pdo->query("SELECT * FROM $t ORDER BY $pk DESC")->fetchAll(PDO::FETCH_ASSOC);
$refCache = [];
foreach ($fields as $name => $f) {
    if (($f[1] === 'select')) $refCache[$name] = getOptions($pdo, $f[2] ?? []);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ComfySci CRUD - <?= h($cfg['label']) ?></title>
<style>
    body { font-family: system-ui, sans-serif; margin: 0; background: #f4f5f7; color: #222; }
    header { background: #1f2937; padding: 12px 20px; display: flex; flex-wrap: wrap; gap: 6px; }
    header a { color: #cbd5e1; text-decoration: none; padding: 6px 12px; border-radius: 6px; font-size: 14px; }
    header a.active, header a:hover { background: #374151; color: #fff; }
    main { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
    .card { background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
    h2 { margin-top: 0; }
    form.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; }
    label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; }
    input[type=text], input[type=email], input[type=password], input[type=number],
    input[type=date], input[type=time], input[type=datetime-local], select, textarea {
        width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font: inherit;
    }
    .check label { display: flex; align-items: center; gap: 8px; font-weight: 600; }
    .actions { grid-column: 1 / -1; display: flex; gap: 8px; }
    button, .btn { background: #2563eb; color: #fff; border: 0; padding: 8px 16px; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 14px; }
    button.danger { background: #dc2626; }
    .btn.gray { background: #6b7280; }
    .msg { padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; }
    .ok { background: #dcfce7; color: #166534; }
    .bad { background: #fee2e2; color: #991b1b; }
    .scroll { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
    td.wrap { white-space: normal; min-width: 200px; }
    th { background: #f9fafb; }
    td form { display: inline; }
</style>
</head>
<body>
<header>
    <?php foreach ($tables as $key => $c): ?>
        <a href="?t=<?= h($key) ?>" class="<?= $key === $t ? 'active' : '' ?>"><?= h($c['label']) ?></a>
    <?php endforeach; ?>
</header>

<main>
    <?php if ($msg): ?><div class="msg ok"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="msg bad"><?= h($err) ?></div><?php endif; ?>

    <div class="card">
        <h2><?= $editRow ? 'Edit' : 'Add' ?> <?= h($cfg['label']) ?></h2>
        <form method="post" action="?t=<?= h($t) ?>" class="grid">
            <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>">
            <input type="hidden" name="id" value="<?= $editRow ? (int)$editRow[$pk] : 0 ?>">

            <?php foreach ($fields as $name => $f):
                [$label, $type] = $f;
                $opt = $f[2] ?? [];
                $val = $editRow[$name] ?? ($opt['default'] ?? '');
                $required = empty($opt['null']) && $type !== 'checkbox'
                            && !($type === 'password' && $editRow);
            ?>
                <div class="<?= $type === 'checkbox' ? 'check' : '' ?>" <?= $type === 'textarea' ? 'style="grid-column:1/-1"' : '' ?>>
                <?php if ($type === 'checkbox'): ?>
                    <label><input type="checkbox" name="<?= h($name) ?>" value="1" <?= $val ? 'checked' : '' ?>> <?= h($label) ?></label>
                <?php else: ?>
                    <label for="<?= h($name) ?>"><?= h($label) ?><?= $required ? ' *' : '' ?></label>
                    <?php if ($type === 'textarea'): ?>
                        <textarea id="<?= h($name) ?>" name="<?= h($name) ?>" rows="4" <?= $required ? 'required' : '' ?>><?= h($val) ?></textarea>
                    <?php elseif ($type === 'select'): ?>
                        <select id="<?= h($name) ?>" name="<?= h($name) ?>" <?= $required ? 'required' : '' ?>>
                            <option value=""><?= $required ? '-- select --' : '(none)' ?></option>
                            <?php foreach ($refCache[$name] as $v => $l): ?>
                                <option value="<?= h($v) ?>" <?= (string)$v === (string)$val ? 'selected' : '' ?>><?= h($l) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($type === 'password'): ?>
                        <input type="password" id="<?= h($name) ?>" name="<?= h($name) ?>" autocomplete="new-password"
                               placeholder="<?= $editRow ? 'Leave blank to keep current' : '' ?>" <?= $required ? 'required' : '' ?>>
                    <?php else:
                        if ($type === 'datetime') { $inType = 'datetime-local'; $val = $val ? str_replace(' ', 'T', substr($val, 0, 16)) : ''; }
                        elseif ($type === 'time') { $inType = 'time'; $val = $val ? substr($val, 0, 5) : ''; }
                        else { $inType = $type; }
                    ?>
                        <input type="<?= h($inType) ?>" id="<?= h($name) ?>" name="<?= h($name) ?>" value="<?= h($val) ?>"
                               <?= isset($opt['step']) ? 'step="' . h($opt['step']) . '"' : '' ?> <?= $required ? 'required' : '' ?>>
                    <?php endif; ?>
                <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="actions">
                <button type="submit"><?= $editRow ? 'Save Changes' : 'Create' ?></button>
                <?php if ($editRow): ?><a class="btn gray" href="?t=<?= h($t) ?>">Cancel</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h2><?= h($cfg['label']) ?> (<?= count($rows) ?>)</h2>
        <div class="scroll">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <?php foreach ($fields as $name => $f): if ($f[1] === 'password') continue; ?>
                        <th><?= h($f[0]) ?></th>
                    <?php endforeach; ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="99">No records yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= (int)$r[$pk] ?></td>
                    <?php foreach ($fields as $name => $f):
                        if ($f[1] === 'password') continue;
                        $v = $r[$name];
                        if ($f[1] === 'checkbox') $show = $v ? 'Yes' : 'No';
                        elseif ($f[1] === 'select') $show = $refCache[$name][$v] ?? $v;
                        elseif ($f[1] === 'textarea') $show = mb_strimwidth((string)$v, 0, 60, '...');
                        else $show = $v;
                    ?>
                        <td class="<?= $f[1] === 'textarea' ? 'wrap' : '' ?>"><?= h($show) ?></td>
                    <?php endforeach; ?>
                    <td>
                        <a class="btn" href="?t=<?= h($t) ?>&edit=<?= (int)$r[$pk] ?>">Edit</a>
                        <form method="post" action="?t=<?= h($t) ?>" onsubmit="return confirm('Delete this record?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$r[$pk] ?>">
                            <button type="submit" class="danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</main>
</body>
</html>
