-- OPTIONAL test data so the dashboard has something to show. Run once, after dashboard_tables.sql.
-- It adds a sample course, faculty, block, two students (Pogi + a classmate), assignments, an event,
-- a message, to-dos and class schedules. The sample password is NOT a real hash (these accounts can't log in).
-- Afterwards set DEV_STUDENT_ID in includes/db.php to the id of "Pogi" (the last query shows it).
USE `db_comfysci`;

INSERT INTO tblcourse (courseName, curriculumName) VALUES ('Sample Course', '2025');
SET @course = LAST_INSERT_ID();
INSERT INTO tblfaculty (facultyLastName, facultyFirstName, facultyEmail, facultyPassword) VALUES ('Berces', 'Marlon', 'marlon.sample@example.com', '!sample');
SET @faculty = LAST_INSERT_ID();
INSERT INTO tblblock (classBlock, courseID, facultyID) VALUES ('Sample Block', @course, @faculty);
SET @block = LAST_INSERT_ID();

INSERT INTO tblstudent (studentLastName, studentFirstName, studentEmail, studentPassword, isClassRep, isGroupLeader, blockID)
VALUES ('Sample', 'Pogi', 'pogi.sample@example.com', '!sample', 0, 0, @block);
SET @me = LAST_INSERT_ID();
INSERT INTO tblstudent (studentLastName, studentFirstName, studentEmail, studentPassword, isClassRep, isGroupLeader, blockID)
VALUES ('Santos', 'Maria', 'maria.sample@example.com', '!sample', 1, 0, @block);
SET @rep = LAST_INSERT_ID();
INSERT INTO tbladmin (adminLastName, adminFirstName, adminEmail, adminPassword) VALUES ('Admin', 'Sample', 'admin.sample@example.com', '!sample');
SET @admin = LAST_INSERT_ID();

INSERT INTO tblassignment (blockID, assignmentTitle, assignmentSubject, assignmentStatus, assignmentDeadline) VALUES
  (@block, 'Yung PurrCom Research.',           'PurrCom',          'Active', TIMESTAMP(CURDATE() + INTERVAL 3 DAY, '17:00:00')),
  (@block, 'Operating System Activity 4',      'Operating System', 'Active', TIMESTAMP(CURDATE() + INTERVAL 4 DAY, '23:59:00')),
  (@block, 'PATHFIT 3 Reflection Paper',       'PATHFIT 3',        'Active', TIMESTAMP(CURDATE() + INTERVAL 6 DAY, '12:00:00')),
  (@block, 'Programming Laboratory Exercise',  'Programming',      'Active', TIMESTAMP(CURDATE() + INTERVAL 8 DAY, '18:00:00')),
  (@block, 'Data Structures Quiz Review',      'Data Structures',  'Active', TIMESTAMP(CURDATE() + INTERVAL 9 DAY, '09:00:00'));

INSERT INTO tblevent (adminID, eventTitle, eventDate, eventStartTime, eventEndTime, eventLocation, eventStatus)
VALUES (@admin, 'Tech Talk', CURDATE() + INTERVAL 5 DAY, '11:00:00', '11:45:00', 'AVR', 'Upcoming');
INSERT INTO tblmessage (blockID, studentID, messageContent, messageType) VALUES (@block, @rep, 'Gawa daw Activity 4 ni Sir. Marlon', 'message');

INSERT INTO tbltask (studentID, taskTitle, taskDate, taskTime, isDone) VALUES
  (@me, 'Submit PurrCom research',        CURDATE() + INTERVAL 1 DAY, '09:00:00', 0),
  (@me, 'Finish OS Activity 4',           CURDATE() + INTERVAL 1 DAY, '17:00:00', 0),
  (@me, 'Read Data Structures module 5',  CURDATE() + INTERVAL 2 DAY, '20:00:00', 0),
  (@me, 'PATHFIT 3 reflection paper',     CURDATE() + INTERVAL 3 DAY, '12:00:00', 0),
  (@me, 'Prepare for programming quiz',   CURDATE() + INTERVAL 4 DAY, '08:00:00', 0),
  (@me, 'Print group project cover page', CURDATE(),                  '23:00:00', 1);

INSERT INTO tblclassschedule (studentID, subject, professorName, startTime, endTime, meetingLink) VALUES (@me, 'Operating System (LEC)', 'Sir. Marlon Berces', '12:30:00', '14:00:00', 'https://meet.google.com/abc-defg-hij');
SET @s = LAST_INSERT_ID(); INSERT INTO tblclassscheduleday (scheduleID, dayOfWeek) VALUES (@s, 4);
INSERT INTO tblclassschedule (studentID, subject, professorName, startTime, endTime, meetingLink) VALUES (@me, 'Data Structures', 'Sir. Dela Cruz', '10:30:00', '12:00:00', 'https://meet.google.com/uvw-xyza-bcd');
SET @s = LAST_INSERT_ID(); INSERT INTO tblclassscheduleday (scheduleID, dayOfWeek) VALUES (@s, 2);
INSERT INTO tblclassschedule (studentID, subject, professorName, startTime, endTime, meetingLink) VALUES (@me, 'PATHFIT 3', 'Ma''am Reyes', '07:00:00', '09:00:00', 'https://meet.google.com/klm-nopq-rst');
SET @s = LAST_INSERT_ID(); INSERT INTO tblclassscheduleday (scheduleID, dayOfWeek) VALUES (@s, 5);
INSERT INTO tblclassschedule (studentID, subject, professorName, startTime, endTime, meetingLink) VALUES (@me, 'Computer Programming 2', 'Ma''am Santos', '13:00:00', '14:30:00', 'https://meet.google.com/efg-hijk-lmn');
SET @s = LAST_INSERT_ID(); INSERT INTO tblclassscheduleday (scheduleID, dayOfWeek) VALUES (@s, 3);

SELECT @me AS 'Set DEV_STUDENT_ID to this id (Pogi)';
