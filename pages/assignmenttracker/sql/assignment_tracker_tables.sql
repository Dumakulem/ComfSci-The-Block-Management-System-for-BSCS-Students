-- Assignment Tracker module tables for the ComfSci database.
-- Run after sql/00_base_schema.sql and its required migrations.
USE `db_comfysci`;
CREATE TABLE IF NOT EXISTS `at_assignments` (
  `as_id` int NOT NULL AUTO_INCREMENT, `blockID` int NOT NULL, `prof` varchar(150) NOT NULL DEFAULT '',
  `as_title` varchar(200) NOT NULL, `as_subject` varchar(150) NOT NULL DEFAULT '', `as_description` text DEFAULT NULL,
  `as_deadline` datetime NOT NULL, `is_group_as` tinyint(1) NOT NULL DEFAULT 0, `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`as_id`), KEY `idx_at_assignment_block_deadline` (`blockID`, `as_deadline`),
  CONSTRAINT `fk_at_assignments_block` FOREIGN KEY (`blockID`) REFERENCES `tblblock` (`blockID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `at_studentassignments` (
  `sa_id` int NOT NULL AUTO_INCREMENT, `as_id` int NOT NULL, `blockID` int NOT NULL, `studentID` int NOT NULL,
  `as_title` varchar(200) NOT NULL, `as_subject` varchar(150) NOT NULL DEFAULT '', `as_description` text DEFAULT NULL,
  `as_deadline` datetime NOT NULL, `is_group_as` tinyint(1) NOT NULL DEFAULT 0, `as_status` varchar(30) NOT NULL DEFAULT 'Pending', `created_at` timestamp NOT NULL DEFAULT current_timestamp(), `edited_at` datetime DEFAULT NULL,
  PRIMARY KEY (`sa_id`), UNIQUE KEY `uq_at_student_assignment` (`as_id`, `studentID`), KEY `idx_at_student_block_status` (`studentID`, `blockID`, `as_status`),
  CONSTRAINT `fk_at_studentassignments_assignment` FOREIGN KEY (`as_id`) REFERENCES `at_assignments` (`as_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_at_studentassignments_block` FOREIGN KEY (`blockID`) REFERENCES `tblblock` (`blockID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_at_studentassignments_student` FOREIGN KEY (`studentID`) REFERENCES `tblstudent` (`studentID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `at_groupas` (
  `groupID` int NOT NULL AUTO_INCREMENT, `as_id` int NOT NULL, `group_name` varchar(120) NOT NULL, PRIMARY KEY (`groupID`), KEY `idx_at_group_assignment` (`as_id`),
  CONSTRAINT `fk_at_groupas_assignment` FOREIGN KEY (`as_id`) REFERENCES `at_assignments` (`as_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `at_groupmembers` (
  `memberID` int NOT NULL AUTO_INCREMENT, `groupID` int NOT NULL, `studentID` int NOT NULL, `is_leader` tinyint(1) NOT NULL DEFAULT 0, `as_id` int NOT NULL,
  PRIMARY KEY (`memberID`), UNIQUE KEY `uq_at_group_student` (`groupID`, `studentID`), KEY `idx_at_group_member_assignment` (`studentID`, `as_id`),
  CONSTRAINT `fk_at_groupmembers_group` FOREIGN KEY (`groupID`) REFERENCES `at_groupas` (`groupID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_at_groupmembers_student` FOREIGN KEY (`studentID`) REFERENCES `tblstudent` (`studentID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_at_groupmembers_assignment` FOREIGN KEY (`as_id`) REFERENCES `at_assignments` (`as_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `at_files` (
  `sa_id` int NOT NULL AUTO_INCREMENT, `as_id` int NOT NULL, `as_title` varchar(200) NOT NULL, `blockID` int NOT NULL, `as_file` longblob NOT NULL,
  `as_file_name` varchar(255) NOT NULL, `as_file_type` varchar(150) DEFAULT NULL, PRIMARY KEY (`sa_id`), KEY `idx_at_assignment_files` (`blockID`, `as_title`),
  CONSTRAINT `fk_at_files_assignment` FOREIGN KEY (`as_id`) REFERENCES `at_assignments` (`as_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_at_files_block` FOREIGN KEY (`blockID`) REFERENCES `tblblock` (`blockID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `at_student_as_files` (
  `id` int NOT NULL AUTO_INCREMENT, `studentID` int NOT NULL, `as_id` int NOT NULL, `as_title` varchar(200) NOT NULL, `blockID` int NOT NULL,
  `file_name` varchar(255) NOT NULL, `file_data` longblob NOT NULL, `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`), KEY `idx_at_submission_student_assignment` (`studentID`, `blockID`, `as_title`),
  CONSTRAINT `fk_at_submission_assignment` FOREIGN KEY (`as_id`) REFERENCES `at_assignments` (`as_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_at_submission_student` FOREIGN KEY (`studentID`) REFERENCES `tblstudent` (`studentID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_at_submission_block` FOREIGN KEY (`blockID`) REFERENCES `tblblock` (`blockID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `at_feedback` (
  `feedback_id` int NOT NULL AUTO_INCREMENT, `sa_id` int NOT NULL, `adminID` int NOT NULL, `feedback` text NOT NULL, `score` int NOT NULL DEFAULT 0, `score_over` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`feedback_id`), UNIQUE KEY `uq_at_feedback_submission` (`sa_id`),
  CONSTRAINT `fk_at_feedback_submission` FOREIGN KEY (`sa_id`) REFERENCES `at_studentassignments` (`sa_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_at_feedback_admin` FOREIGN KEY (`adminID`) REFERENCES `tbladmin` (`adminID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `at_email_notifications` (
  `notificationID` int NOT NULL AUTO_INCREMENT, `assignmentID` int NOT NULL, `studentID` int NOT NULL, `notificationType` varchar(30) NOT NULL,
  `sentAt` timestamp NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (`notificationID`), UNIQUE KEY `uq_at_notification_student_type` (`assignmentID`, `studentID`, `notificationType`),
  CONSTRAINT `fk_at_notification_assignment` FOREIGN KEY (`assignmentID`) REFERENCES `at_assignments` (`as_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_at_notification_student` FOREIGN KEY (`studentID`) REFERENCES `tblstudent` (`studentID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
