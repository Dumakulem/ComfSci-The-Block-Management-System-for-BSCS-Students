<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/attendance-base.php';

/* Pagpasok sa attendance.php, ipinapakita yung mga category depende sa role:
   student -> Campus Events, Event Attendance, Event Payment, Announcements
   block rep -> Campus Events muna, tapos Event Attendance, Event Payment
   admin -> parehong apat tapos may management tools */
if ($attendanceRole === 'admin') {
    attendance_redirect($attendanceBase . '/admin/campus-event.php');
}

// rep: sa Campus Events muna para makapili ng event bago mag-mark ng attendance
if ($attendanceRole === 'rep') {
    attendance_redirect($attendanceBase . '/student/campus-event.php');
}

attendance_redirect($attendanceBase . '/student/campus-event.php');
