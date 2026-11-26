-- Additive, rerunnable schema for ComfSci group activities.
USE `db_comfysci`;

CREATE TABLE IF NOT EXISTS `tblgroupactivity` (
  `activityID` int(25) NOT NULL AUTO_INCREMENT,
  `blockID` int(25) NOT NULL,
  `createdBy` int(25) NOT NULL,
  `subject` varchar(100) NOT NULL,
  `activityTitle` varchar(150) NOT NULL,
  `activityDescription` text NOT NULL,
  `deadline` datetime NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`activityID`),
  KEY `idxGroupActivityBlockSubject` (`blockID`,`subject`),
  KEY `idxGroupActivityDeadline` (`deadline`),
  CONSTRAINT `fkGroupActivityBlock` FOREIGN KEY (`blockID`) REFERENCES `tblblock` (`blockID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fkGroupActivityCreator` FOREIGN KEY (`createdBy`) REFERENCES `tblstudent` (`studentID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @groupActivityBlockFkExists = (
  SELECT COUNT(*)
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tblgroupactivity'
    AND COLUMN_NAME = 'blockID'
    AND REFERENCED_TABLE_NAME = 'tblblock'
    AND REFERENCED_COLUMN_NAME = 'blockID'
);
SET @groupActivityBlockFkSql = IF(
  @groupActivityBlockFkExists > 0,
  'SELECT 1',
  'ALTER TABLE `tblgroupactivity` ADD CONSTRAINT `fkGroupActivityBlock` FOREIGN KEY (`blockID`) REFERENCES `tblblock` (`blockID`) ON DELETE CASCADE ON UPDATE CASCADE'
);
PREPARE groupActivityBlockFkStmt FROM @groupActivityBlockFkSql;
EXECUTE groupActivityBlockFkStmt;
DEALLOCATE PREPARE groupActivityBlockFkStmt;

CREATE TABLE IF NOT EXISTS `tblgroupactivitygroup` (
  `groupID` int(25) NOT NULL AUTO_INCREMENT,
  `activityID` int(25) NOT NULL,
  `groupName` varchar(80) NOT NULL,
  `leaderID` int(25) NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`groupID`),
  UNIQUE KEY `uqGroupActivityGroupPair` (`groupID`,`activityID`),
  KEY `idxGroupActivityGroupActivity` (`activityID`),
  KEY `idxGroupActivityGroupLeader` (`leaderID`),
  CONSTRAINT `fkGroupActivityGroupActivity` FOREIGN KEY (`activityID`) REFERENCES `tblgroupactivity` (`activityID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fkGroupActivityGroupLeader` FOREIGN KEY (`leaderID`) REFERENCES `tblstudent` (`studentID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblgroupactivitymember` (
  `activityID` int(25) NOT NULL,
  `groupID` int(25) NOT NULL,
  `studentID` int(25) NOT NULL,
  `joinedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`activityID`,`studentID`),
  UNIQUE KEY `uqGroupActivityMemberPair` (`groupID`,`studentID`),
  KEY `idxGroupActivityMemberStudent` (`studentID`),
  CONSTRAINT `fkGroupActivityMemberGroup` FOREIGN KEY (`groupID`,`activityID`) REFERENCES `tblgroupactivitygroup` (`groupID`,`activityID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fkGroupActivityMemberStudent` FOREIGN KEY (`studentID`) REFERENCES `tblstudent` (`studentID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblgroupactivitytimeline` (
  `timelineID` int(25) NOT NULL AUTO_INCREMENT,
  `groupID` int(25) NOT NULL,
  `createdBy` int(25) NOT NULL,
  `entryTitle` varchar(150) NOT NULL,
  `entryDetails` text NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`timelineID`),
  KEY `idxGroupActivityTimelineGroup` (`groupID`,`createdAt`),
  CONSTRAINT `fkGroupActivityTimelineGroup` FOREIGN KEY (`groupID`) REFERENCES `tblgroupactivitygroup` (`groupID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fkGroupActivityTimelineCreator` FOREIGN KEY (`createdBy`) REFERENCES `tblstudent` (`studentID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblgroupactivitytask` (
  `taskID` int(25) NOT NULL AUTO_INCREMENT,
  `groupID` int(25) NOT NULL,
  `assignedTo` int(25) NOT NULL,
  `createdBy` int(25) NOT NULL,
  `taskTitle` varchar(150) NOT NULL,
  `taskDetails` text NOT NULL,
  `deadline` datetime DEFAULT NULL,
  `isComplete` tinyint(1) NOT NULL DEFAULT 0,
  `completedAt` datetime DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`taskID`),
  KEY `idxGroupActivityTaskGroupDeadline` (`groupID`,`deadline`),
  KEY `idxGroupActivityTaskAssignee` (`assignedTo`),
  CONSTRAINT `fkGroupActivityTaskMember` FOREIGN KEY (`groupID`,`assignedTo`) REFERENCES `tblgroupactivitymember` (`groupID`,`studentID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fkGroupActivityTaskCreator` FOREIGN KEY (`createdBy`) REFERENCES `tblstudent` (`studentID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @groupActivityCompleteExists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tblgroupactivitytask'
    AND COLUMN_NAME = 'isComplete'
);
SET @groupActivityCompleteSql = IF(
  @groupActivityCompleteExists > 0,
  'SELECT 1',
  'ALTER TABLE `tblgroupactivitytask` ADD COLUMN `isComplete` tinyint(1) NOT NULL DEFAULT 0'
);
PREPARE groupActivityCompleteStmt FROM @groupActivityCompleteSql;
EXECUTE groupActivityCompleteStmt;
DEALLOCATE PREPARE groupActivityCompleteStmt;

SET @groupActivityCompletedAtExists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tblgroupactivitytask'
    AND COLUMN_NAME = 'completedAt'
);
SET @groupActivityCompletedAtSql = IF(
  @groupActivityCompletedAtExists > 0,
  'SELECT 1',
  'ALTER TABLE `tblgroupactivitytask` ADD COLUMN `completedAt` datetime DEFAULT NULL'
);
PREPARE groupActivityCompletedAtStmt FROM @groupActivityCompletedAtSql;
EXECUTE groupActivityCompletedAtStmt;
DEALLOCATE PREPARE groupActivityCompletedAtStmt;
