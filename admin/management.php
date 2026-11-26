<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/require_admin.php';
require_once __DIR__ . '/../config/database.php';

$pdo = db();
$error = '';
$success = '';
$action = (string) ($_POST['action'] ?? '');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $required = static function (string $key): string {
            $value = trim((string) ($_POST[$key] ?? ''));
            if ($value === '') {
                throw new InvalidArgumentException('All required fields must be completed.');
            }
            return $value;
        };

        switch ($action) {
            case 'course':
                $statement = $pdo->prepare(
                    'INSERT INTO tblcourse (courseName, curriculumName) VALUES (?, ?)'
                );
                $statement->execute([$required('course_name'), $required('curriculum_name')]);
                $success = 'Course created.';
                break;
            case 'block':
                $statement = $pdo->prepare(
                    'INSERT INTO tblblock (classBlock, courseID, facultyID) VALUES (?, ?, ?)'
                );
                $facultyId = (int) ($_POST['faculty_id'] ?? 0);
                $statement->execute([
                    $required('class_block'),
                    (int) $required('course_id'),
                    $facultyId > 0 ? $facultyId : null,
                ]);
                $success = 'Block created.';
                break;
            case 'faculty':
                $statement = $pdo->prepare(
                    'INSERT INTO tblfaculty
                     (facultyLastName, facultyFirstName, facultyMiddleName, facultyExtensionName,
                      facultyEmail, facultyPassword)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $facultyPassword = $required('faculty_password');
                if (strlen($facultyPassword) < 8) {
                    throw new InvalidArgumentException('Faculty password must be at least 8 characters.');
                }
                $statement->execute([
                    $required('faculty_last_name'),
                    $required('faculty_first_name'),
                    trim((string) ($_POST['faculty_middle_name'] ?? '')) ?: null,
                    trim((string) ($_POST['faculty_extension_name'] ?? '')) ?: null,
                    $required('faculty_email'),
                    password_hash($facultyPassword, PASSWORD_DEFAULT),
                ]);
                $success = 'Faculty account created.';
                break;
            case 'student':
                $studentPassword = $required('student_password');
                if (strlen($studentPassword) < 8) {
                    throw new InvalidArgumentException('Student password must be at least 8 characters.');
                }
                $statement = $pdo->prepare(
                    'INSERT INTO tblstudent
                     (studentLastName, studentFirstName, studentMiddleName, studentExtensionName,
                      studentEmail, studentPassword, isClassRep, isGroupLeader, blockID)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $statement->execute([
                    $required('student_last_name'),
                    $required('student_first_name'),
                    trim((string) ($_POST['student_middle_name'] ?? '')) ?: null,
                    trim((string) ($_POST['student_extension_name'] ?? '')) ?: null,
                    $required('student_email'),
                    password_hash($studentPassword, PASSWORD_DEFAULT),
                    isset($_POST['is_class_rep']) ? 1 : 0,
                    isset($_POST['is_group_leader']) ? 1 : 0,
                    (int) $required('student_block_id'),
                ]);
                $success = 'Student account created.';
                break;
            case 'assignment':
                $statement = $pdo->prepare(
                    'INSERT INTO tblassignment
                     (blockID, assignmentTitle, assignmentSubject, assignmentDescription,
                      assignmentStatus, assignmentDeadline)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $statement->execute([
                    (int) $required('assignment_block_id'),
                    $required('assignment_title'),
                    $required('assignment_subject'),
                    trim((string) ($_POST['assignment_description'] ?? '')) ?: null,
                    $required('assignment_status'),
                    $required('assignment_deadline'),
                ]);
                $success = 'Assignment created.';
                break;
            case 'event':
                $statement = $pdo->prepare(
                    'INSERT INTO tblevent
                     (adminID, eventTitle, eventDescription, eventDate, eventStartTime,
                      eventEndTime, eventLocation, participationFee, eventStatus)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $statement->execute([
                    (int) $_SESSION['user_id'],
                    $required('event_title'),
                    trim((string) ($_POST['event_description'] ?? '')) ?: null,
                    $required('event_date'),
                    $required('event_start_time'),
                    trim((string) ($_POST['event_end_time'] ?? '')) ?: null,
                    $required('event_location'),
                    (float) ($_POST['participation_fee'] ?? 0),
                    $required('event_status'),
                ]);
                $success = 'Event created.';
                break;
            case 'announcement':
                $statement = $pdo->prepare(
                    'INSERT INTO tblannouncement (adminID, announcementTitle, announcementContent)
                     VALUES (?, ?, ?)'
                );
                $statement->execute([
                    (int) $_SESSION['user_id'],
                    $required('announcement_title'),
                    $required('announcement_content'),
                ]);
                $success = 'Announcement created.';
                break;
            default:
                throw new InvalidArgumentException('Unknown management action.');
        }
    }
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (PDOException $exception) {
    $error = (($exception->errorInfo[1] ?? null) === 1062)
        ? 'That email or unique value already exists.'
        : 'The record could not be created. Check the selected related record and database schema.';
}

$courses = $pdo->query('SELECT courseID, courseName FROM tblcourse ORDER BY courseName')->fetchAll();
$blocks = $pdo->query('SELECT blockID, classBlock FROM tblblock ORDER BY classBlock')->fetchAll();
$faculty = $pdo->query('SELECT facultyID, facultyFirstName, facultyLastName FROM tblfaculty ORDER BY facultyLastName')->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Management</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f3f3f3; margin: 0; padding: 2rem; }
    main { max-width: 70rem; margin: auto; }
    section { background: #fff; padding: 1.25rem; margin-bottom: 1rem; border-radius: 0.6rem; }
    form { display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: 0.75rem; }
    label { display: flex; flex-direction: column; gap: 0.3rem; font-weight: bold; }
    input, select, textarea { padding: 0.55rem; font: inherit; }
    textarea { min-height: 5rem; }
    button { padding: 0.65rem 1rem; cursor: pointer; }
    .wide { grid-column: 1 / -1; }
    .message { font-weight: bold; }
    .error { color: #b00020; }
    .success { color: #176b2c; }
  </style>
</head>
<body>
<main>
  <h1>Administrator Management</h1>
  <p>Signed in as <?= htmlspecialchars((string) $_SESSION['user_email'], ENT_QUOTES, 'UTF-8') ?></p>
  <?php if ($error !== ''): ?><p class="message error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
  <?php if ($success !== ''): ?><p class="message success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

  <section><h2>Course</h2><form method="post">
    <input type="hidden" name="action" value="course">
    <label>Course name<input name="course_name" required></label>
    <label>Curriculum name<input name="curriculum_name" required></label>
    <button type="submit">Create course</button>
  </form></section>

  <section><h2>Block</h2><form method="post">
    <input type="hidden" name="action" value="block">
    <label>Class block<input name="class_block" required></label>
    <label>Course<select name="course_id" required><?php foreach ($courses as $course): ?><option value="<?= (int) $course['courseID'] ?>"><?= htmlspecialchars($course['courseName'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
    <label>Faculty (optional)<select name="faculty_id"><option value="0">None</option><?php foreach ($faculty as $person): ?><option value="<?= (int) $person['facultyID'] ?>"><?= htmlspecialchars($person['facultyLastName'] . ', ' . $person['facultyFirstName'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
    <button type="submit">Create block</button>
  </form></section>

  <section><h2>Faculty account</h2><form method="post">
    <input type="hidden" name="action" value="faculty">
    <label>First name<input name="faculty_first_name" required></label><label>Last name<input name="faculty_last_name" required></label>
    <label>Middle name<input name="faculty_middle_name"></label><label>Extension name<input name="faculty_extension_name"></label>
    <label>Email<input type="email" name="faculty_email" required></label><label>Password<input type="password" name="faculty_password" minlength="8" required></label>
    <button type="submit">Create faculty</button>
  </form></section>

  <section><h2>Student account</h2><form method="post">
    <input type="hidden" name="action" value="student">
    <label>First name<input name="student_first_name" required></label><label>Last name<input name="student_last_name" required></label>
    <label>Middle name<input name="student_middle_name"></label><label>Extension name<input name="student_extension_name"></label>
    <label>Email<input type="email" name="student_email" required></label><label>Password<input type="password" name="student_password" minlength="8" required></label>
    <label>Block<select name="student_block_id" required><?php foreach ($blocks as $block): ?><option value="<?= (int) $block['blockID'] ?>"><?= htmlspecialchars($block['classBlock'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
    <label><span>Flags</span><span><input type="checkbox" name="is_class_rep"> Class representative</span><span><input type="checkbox" name="is_group_leader"> Group leader</span></label>
    <button type="submit">Create student</button>
  </form></section>

  <section><h2>Assignment</h2><form method="post">
    <input type="hidden" name="action" value="assignment">
    <label>Block<select name="assignment_block_id" required><?php foreach ($blocks as $block): ?><option value="<?= (int) $block['blockID'] ?>"><?= htmlspecialchars($block['classBlock'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
    <label>Title<input name="assignment_title" required></label><label>Subject<input name="assignment_subject" required></label>
    <label>Status<select name="assignment_status"><option>Pending</option><option>Published</option><option>Completed</option></select></label>
    <label>Deadline<input type="datetime-local" name="assignment_deadline" required></label>
    <label class="wide>Description<textarea name="assignment_description"></textarea></label>
    <button type="submit">Create assignment</button>
  </form></section>

  <section><h2>Event</h2><form method="post">
    <input type="hidden" name="action" value="event">
    <label>Title<input name="event_title" required></label><label>Date<input type="date" name="event_date" required></label>
    <label>Start time<input type="time" name="event_start_time" required></label><label>End time<input type="time" name="event_end_time"></label>
    <label>Location<input name="event_location" required></label><label>Participation fee<input type="number" name="participation_fee" min="0" step="0.01" value="0"></label>
    <label>Status<select name="event_status"><option>Upcoming</option><option>Ongoing</option><option>Completed</option><option>Cancelled</option></select></label>
    <label class="wide>Description<textarea name="event_description"></textarea></label>
    <button type="submit">Create event</button>
  </form></section>

  <section><h2>Announcement</h2><form method="post">
    <input type="hidden" name="action" value="announcement">
    <label class="wide">Title<input name="announcement_title" required></label>
    <label class="wide">Content<textarea name="announcement_content" required></textarea></label>
    <button type="submit">Create announcement</button>
  </form></section>
</main>
</body>
</html>
