USE `db_comfysci`;

ALTER TABLE `tblstudent`
  ADD COLUMN IF NOT EXISTS `studentNumber` varchar(40) DEFAULT NULL AFTER `studentID`;
ALTER TABLE `tblstudent`
  ADD UNIQUE KEY IF NOT EXISTS `uqStudentNumber` (`studentNumber`);

ALTER TABLE `tblblock`
  ADD COLUMN IF NOT EXISTS `yearLevel` varchar(20) NOT NULL DEFAULT '1st Year' AFTER `courseID`;

CREATE TABLE IF NOT EXISTS `tblsubject` (
  `subjectID`   int(25) NOT NULL AUTO_INCREMENT,
  `yearLevel`   varchar(20)  NOT NULL,
  `program`     varchar(20)  DEFAULT NULL,
  `subjectName` varchar(100) NOT NULL,
  `subjectCode` varchar(30)  NOT NULL,
  `instructor`  varchar(100) NOT NULL,
  `schedule`    varchar(100) DEFAULT NULL,
  `room`        varchar(60)  DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `createdAt`   timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`subjectID`),
  KEY `idxSubjectYear` (`yearLevel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `tblcourse` (`courseName`, `curriculumName`)
  SELECT 'BSCS', 'BS Computer Science' FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM `tblcourse` WHERE `courseName` = 'BSCS');
INSERT INTO `tblcourse` (`courseName`, `curriculumName`)
  SELECT 'BSIT', 'BS Information Technology' FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM `tblcourse` WHERE `courseName` = 'BSIT');
INSERT INTO `tblcourse` (`courseName`, `curriculumName`)
  SELECT 'BSEMC-GD', 'BS Entertainment and Multimedia Computing - Game Development' FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM `tblcourse` WHERE `courseName` = 'BSEMC-GD');
INSERT INTO `tblcourse` (`courseName`, `curriculumName`)
  SELECT 'BSEMC-DAT', 'BS Entertainment and Multimedia Computing - Digital Animation Technology' FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM `tblcourse` WHERE `courseName` = 'BSEMC-DAT');

ALTER TABLE `tblstudent`
  ADD COLUMN IF NOT EXISTS `isArchived` tinyint(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `archivedAt` datetime   DEFAULT NULL;

ALTER TABLE `tblsubject`
  ADD COLUMN IF NOT EXISTS `program` varchar(20) DEFAULT NULL AFTER `yearLevel`;
