-- Dashboard-owned tables for db_comfysci. Safe to run again after restoring db_comfysci.sql.
USE `db_comfysci`;

CREATE TABLE IF NOT EXISTS `tbltask` (
  `taskID` int(25) NOT NULL AUTO_INCREMENT,
  `studentID` int(25) NOT NULL,
  `taskTitle` varchar(60) NOT NULL,
  `taskDate` date NOT NULL,
  `taskTime` time NOT NULL,
  `isDone` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`taskID`),
  KEY `idxTaskStudent` (`studentID`,`taskDate`,`taskTime`),
  CONSTRAINT `fkTaskStudent` FOREIGN KEY (`studentID`) REFERENCES `tblstudent` (`studentID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblclassschedule` (
  `scheduleID` int(25) NOT NULL AUTO_INCREMENT,
  `studentID` int(25) NOT NULL,
  `subject` varchar(60) NOT NULL,
  `professorName` varchar(60) NOT NULL,
  `startTime` time NOT NULL,
  `endTime` time NOT NULL,
  `meetingLink` varchar(300) NOT NULL DEFAULT '',
  PRIMARY KEY (`scheduleID`),
  KEY `idxScheduleStudent` (`studentID`),
  CONSTRAINT `fkScheduleStudent` FOREIGN KEY (`studentID`) REFERENCES `tblstudent` (`studentID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblclassscheduleday` (
  `scheduleID` int(25) NOT NULL,
  `dayOfWeek` tinyint(1) NOT NULL COMMENT '0 = Sunday ... 6 = Saturday',
  PRIMARY KEY (`scheduleID`,`dayOfWeek`),
  CONSTRAINT `fkScheduleDaySchedule` FOREIGN KEY (`scheduleID`) REFERENCES `tblclassschedule` (`scheduleID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblassignmentpriority` (
  `studentID` int(25) NOT NULL,
  `assignmentID` int(25) NOT NULL,
  `priority` enum('High','Medium','Low') NOT NULL DEFAULT 'Medium',
  PRIMARY KEY (`studentID`,`assignmentID`),
  KEY `idxPriorityAssignment` (`assignmentID`),
  CONSTRAINT `fkPriorityStudent` FOREIGN KEY (`studentID`) REFERENCES `tblstudent` (`studentID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fkPriorityAssignment` FOREIGN KEY (`assignmentID`) REFERENCES `tblassignment` (`assignmentID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblnotificationclear` (
  `studentID` int(25) NOT NULL,
  `clearedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`studentID`),
  CONSTRAINT `fkNotificationClearStudent` FOREIGN KEY (`studentID`) REFERENCES `tblstudent` (`studentID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
