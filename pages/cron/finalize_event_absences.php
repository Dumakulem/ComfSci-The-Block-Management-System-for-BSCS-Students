<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

try {
    $pdo = db();
    $statement = $pdo->prepare(
        "INSERT IGNORE INTO tbleventattendance (registrationID, attendanceStatus)
         SELECT r.registrationID, 'absent'
         FROM tbleventattendanceapproval approval
         JOIN tblevent e ON e.eventID = approval.eventID
         JOIN tbleventregistration r ON r.eventID = e.eventID
         JOIN tblstudent s ON s.studentID = r.studentID
         WHERE approval.approvalStatus = 'Approved'
           AND e.archivedAt IS NULL
           AND e.eventStatus <> 'Cancelled'
           AND s.archivedAt IS NULL
           AND s.blockID = approval.blockID
           AND r.registrationStatus = 'Registered'
           AND NOW() > TIMESTAMP(e.eventDate, COALESCE(e.eventEndTime, e.eventStartTime))"
    );
    $statement->execute();
    fwrite(STDOUT, 'Inserted absent attendance records: ' . $statement->rowCount() . PHP_EOL);
} catch (PDOException $exception) {
    error_log('Event absence finalizer failed: ' . $exception->getMessage());
    fwrite(STDERR, "Event absence finalization failed.\n");
    exit(1);
}
