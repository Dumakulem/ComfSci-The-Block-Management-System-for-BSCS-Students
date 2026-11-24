-- Optional, repeatable dashboard sample data for the first existing student.
-- Adds no student/admin accounts and does not modify existing records.
USE `db_comfysci`;

SET @student_id = (SELECT MIN(studentID) FROM tblstudent);
SET @block_id = (SELECT blockID FROM tblstudent WHERE studentID = @student_id);
SET @admin_id = (SELECT MIN(adminID) FROM tbladmin);

START TRANSACTION;

INSERT INTO tblassignment (blockID, assignmentTitle, assignmentSubject, assignmentStatus, assignmentDeadline)
SELECT @block_id, 'Yung PurrCom Research.', 'PurCom', 'Active', TIMESTAMP(CURDATE() + INTERVAL 3 DAY, '17:00:00')
WHERE @block_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM tblassignment WHERE blockID = @block_id AND assignmentTitle = 'Yung PurrCom Research.');

INSERT INTO tblassignment (blockID, assignmentTitle, assignmentSubject, assignmentStatus, assignmentDeadline)
SELECT @block_id, 'Operating System Activity 4', 'Operating System', 'Active', TIMESTAMP(CURDATE() + INTERVAL 4 DAY, '23:59:00')
WHERE @block_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM tblassignment WHERE blockID = @block_id AND assignmentTitle = 'Operating System Activity 4');

INSERT INTO tblassignment (blockID, assignmentTitle, assignmentSubject, assignmentStatus, assignmentDeadline)
SELECT @block_id, 'PATHFIT 3 Reflection Paper', 'PATHFIT 3', 'Active', TIMESTAMP(CURDATE() + INTERVAL 6 DAY, '12:00:00')
WHERE @block_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM tblassignment WHERE blockID = @block_id AND assignmentTitle = 'PATHFIT 3 Reflection Paper');

INSERT INTO tblevent (adminID, eventTitle, eventDescription, eventDate, eventStartTime, eventEndTime, eventLocation, eventStatus)
SELECT @admin_id, 'Tech Talk', 'Sample upcoming student event.', CURDATE() + INTERVAL 5 DAY, '11:00:00', '11:45:00', 'AVR', 'Upcoming'
WHERE @admin_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM tblevent WHERE adminID = @admin_id AND eventTitle = 'Tech Talk');

INSERT INTO tbltask (studentID, taskTitle, taskDate, taskTime, isDone)
SELECT @student_id, 'Make a Dart on Networking', CURDATE() + INTERVAL 1 DAY, '09:00:00', 0
WHERE @student_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM tbltask WHERE studentID = @student_id AND taskTitle = 'Make a Dart on Networking');

INSERT INTO tbltask (studentID, taskTitle, taskDate, taskTime, isDone)
SELECT @student_id, 'Finish OS Activity 4', CURDATE() + INTERVAL 1 DAY, '17:00:00', 0
WHERE @student_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM tbltask WHERE studentID = @student_id AND taskTitle = 'Finish OS Activity 4');

INSERT INTO tbltask (studentID, taskTitle, taskDate, taskTime, isDone)
SELECT @student_id, 'Read Data Structures module 5', CURDATE() + INTERVAL 2 DAY, '20:00:00', 0
WHERE @student_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM tbltask WHERE studentID = @student_id AND taskTitle = 'Read Data Structures module 5');

INSERT INTO tbltask (studentID, taskTitle, taskDate, taskTime, isDone)
SELECT @student_id, 'PATHFIT 3 reflection paper', CURDATE() + INTERVAL 3 DAY, '12:00:00', 0
WHERE @student_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM tbltask WHERE studentID = @student_id AND taskTitle = 'PATHFIT 3 reflection paper');

INSERT INTO tbltask (studentID, taskTitle, taskDate, taskTime, isDone)
SELECT @student_id, 'Prepare for programming quiz', CURDATE() + INTERVAL 4 DAY, '08:00:00', 0
WHERE @student_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM tbltask WHERE studentID = @student_id AND taskTitle = 'Prepare for programming quiz');

INSERT INTO tblclassschedule (studentID, subject, professorName, startTime, endTime, meetingLink)
SELECT @student_id, 'Data Structures', 'Sir. Dela Cruz', '10:30:00', '12:00:00', ''
WHERE @student_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM tblclassschedule WHERE studentID = @student_id AND subject = 'Data Structures' AND professorName = 'Sir. Dela Cruz');

INSERT INTO tblclassscheduleday (scheduleID, dayOfWeek)
SELECT scheduleID, 2 FROM tblclassschedule
WHERE studentID = @student_id AND subject = 'Data Structures' AND professorName = 'Sir. Dela Cruz'
  AND NOT EXISTS (SELECT 1 FROM tblclassscheduleday d WHERE d.scheduleID = tblclassschedule.scheduleID AND d.dayOfWeek = 2);

INSERT INTO tblclassschedule (studentID, subject, professorName, startTime, endTime, meetingLink)
SELECT @student_id, 'Operating System (LEC)', 'Sir. Marlon Berces', '12:30:00', '14:00:00', ''
WHERE @student_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM tblclassschedule WHERE studentID = @student_id AND subject = 'Operating System (LEC)' AND professorName = 'Sir. Marlon Berces');

INSERT INTO tblclassscheduleday (scheduleID, dayOfWeek)
SELECT scheduleID, 4 FROM tblclassschedule
WHERE studentID = @student_id AND subject = 'Operating System (LEC)' AND professorName = 'Sir. Marlon Berces'
  AND NOT EXISTS (SELECT 1 FROM tblclassscheduleday d WHERE d.scheduleID = tblclassschedule.scheduleID AND d.dayOfWeek = 4);

INSERT INTO tblassignmentpriority (studentID, assignmentID, priority)
SELECT @student_id, assignmentID,
       CASE assignmentTitle
           WHEN 'Yung PurrCom Research.' THEN 'Medium'
           WHEN 'Operating System Activity 4' THEN 'High'
           ELSE 'Medium'
       END
FROM tblassignment
WHERE blockID = @block_id
  AND assignmentTitle IN ('Yung PurrCom Research.', 'Operating System Activity 4', 'PATHFIT 3 Reflection Paper')
  AND NOT EXISTS (
      SELECT 1 FROM tblassignmentpriority p
      WHERE p.studentID = @student_id AND p.assignmentID = tblassignment.assignmentID
  );

COMMIT;
