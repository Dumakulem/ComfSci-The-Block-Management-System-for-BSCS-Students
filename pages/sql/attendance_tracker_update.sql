USE `db_comfysci`;

ALTER TABLE `tblevent`
  ADD COLUMN IF NOT EXISTS `archivedAt` datetime DEFAULT NULL AFTER `updatedAt`,
  ADD COLUMN IF NOT EXISTS `courseID` int(25) DEFAULT NULL AFTER `adminID`,
  ADD COLUMN IF NOT EXISTS `paymentStartDate` date DEFAULT NULL AFTER `participationFee`,
  ADD COLUMN IF NOT EXISTS `paymentDeadline` date DEFAULT NULL AFTER `paymentStartDate`;

SET @eventCourseIndexExists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tblevent'
    AND COLUMN_NAME = 'courseID'
    AND SEQ_IN_INDEX = 1
);
SET @eventCourseIndexSql = IF(
  @eventCourseIndexExists > 0,
  'SELECT 1',
  'ALTER TABLE `tblevent` ADD KEY `idxEventCourse` (`courseID`)'
);
PREPARE eventCourseIndexStmt FROM @eventCourseIndexSql;
EXECUTE eventCourseIndexStmt;
DEALLOCATE PREPARE eventCourseIndexStmt;

SET @eventCourseFkExists = (
  SELECT COUNT(*)
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tblevent'
    AND COLUMN_NAME = 'courseID'
    AND REFERENCED_TABLE_NAME = 'tblcourse'
    AND REFERENCED_COLUMN_NAME = 'courseID'
);
SET @eventCourseFkSql = IF(
  @eventCourseFkExists > 0,
  'SELECT 1',
  'ALTER TABLE `tblevent` ADD CONSTRAINT `fkEventCourse` FOREIGN KEY (`courseID`) REFERENCES `tblcourse` (`courseID`) ON DELETE SET NULL ON UPDATE CASCADE'
);
PREPARE eventCourseFkStmt FROM @eventCourseFkSql;
EXECUTE eventCourseFkStmt;
DEALLOCATE PREPARE eventCourseFkStmt;

ALTER TABLE `tblannouncement`
  ADD COLUMN IF NOT EXISTS `eventID` int(25) DEFAULT NULL AFTER `adminID`;

SET @announcementEventIndexExists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tblannouncement'
    AND COLUMN_NAME = 'eventID'
    AND SEQ_IN_INDEX = 1
);
SET @announcementEventIndexSql = IF(
  @announcementEventIndexExists > 0,
  'SELECT 1',
  'ALTER TABLE `tblannouncement` ADD KEY `idxAnnouncementEvent` (`eventID`)'
);
PREPARE announcementEventIndexStmt FROM @announcementEventIndexSql;
EXECUTE announcementEventIndexStmt;
DEALLOCATE PREPARE announcementEventIndexStmt;

SET @announcementEventFkExists = (
  SELECT COUNT(*)
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tblannouncement'
    AND COLUMN_NAME = 'eventID'
    AND REFERENCED_TABLE_NAME = 'tblevent'
    AND REFERENCED_COLUMN_NAME = 'eventID'
);
SET @announcementEventFkSql = IF(
  @announcementEventFkExists > 0,
  'SELECT 1',
  'ALTER TABLE `tblannouncement` ADD CONSTRAINT `fkAnnouncementEvent` FOREIGN KEY (`eventID`) REFERENCES `tblevent` (`eventID`) ON DELETE CASCADE ON UPDATE CASCADE'
);
PREPARE announcementEventFkStmt FROM @announcementEventFkSql;
EXECUTE announcementEventFkStmt;
DEALLOCATE PREPARE announcementEventFkStmt;

ALTER TABLE `tbleventpayment`
  MODIFY COLUMN `paymentStatus` varchar(30) NOT NULL DEFAULT 'Unpaid';

ALTER TABLE `tbleventpayment`
  ADD COLUMN IF NOT EXISTS `processedByAdminID` int(25) DEFAULT NULL AFTER `receivedByAdminID`;

ALTER TABLE `tbleventpayment`
  ADD COLUMN IF NOT EXISTS `processedByStudentID` int(25) DEFAULT NULL AFTER `processedByAdminID`;

SET @paymentProcessorAdminIndexExists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tbleventpayment'
    AND COLUMN_NAME = 'processedByAdminID'
    AND SEQ_IN_INDEX = 1
);
SET @paymentProcessorAdminIndexSql = IF(
  @paymentProcessorAdminIndexExists > 0,
  'SELECT 1',
  'ALTER TABLE `tbleventpayment` ADD KEY `idxEventPaymentProcessorAdmin` (`processedByAdminID`)'
);
PREPARE paymentProcessorAdminIndexStmt FROM @paymentProcessorAdminIndexSql;
EXECUTE paymentProcessorAdminIndexStmt;
DEALLOCATE PREPARE paymentProcessorAdminIndexStmt;

SET @paymentProcessorStudentIndexExists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tbleventpayment'
    AND COLUMN_NAME = 'processedByStudentID'
    AND SEQ_IN_INDEX = 1
);
SET @paymentProcessorStudentIndexSql = IF(
  @paymentProcessorStudentIndexExists > 0,
  'SELECT 1',
  'ALTER TABLE `tbleventpayment` ADD KEY `idxEventPaymentProcessorStudent` (`processedByStudentID`)'
);
PREPARE paymentProcessorStudentIndexStmt FROM @paymentProcessorStudentIndexSql;
EXECUTE paymentProcessorStudentIndexStmt;
DEALLOCATE PREPARE paymentProcessorStudentIndexStmt;

SET @paymentProcessorAdminFkExists = (
  SELECT COUNT(*)
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tbleventpayment'
    AND COLUMN_NAME = 'processedByAdminID'
    AND REFERENCED_TABLE_NAME = 'tbladmin'
    AND REFERENCED_COLUMN_NAME = 'adminID'
);
SET @paymentProcessorAdminFkSql = IF(
  @paymentProcessorAdminFkExists > 0,
  'SELECT 1',
  'ALTER TABLE `tbleventpayment` ADD CONSTRAINT `fkEventPaymentProcessorAdmin` FOREIGN KEY (`processedByAdminID`) REFERENCES `tbladmin` (`adminID`) ON DELETE SET NULL ON UPDATE CASCADE'
);
PREPARE paymentProcessorAdminFkStmt FROM @paymentProcessorAdminFkSql;
EXECUTE paymentProcessorAdminFkStmt;
DEALLOCATE PREPARE paymentProcessorAdminFkStmt;

SET @paymentProcessorStudentFkExists = (
  SELECT COUNT(*)
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tbleventpayment'
    AND COLUMN_NAME = 'processedByStudentID'
    AND REFERENCED_TABLE_NAME = 'tblstudent'
    AND REFERENCED_COLUMN_NAME = 'studentID'
);
SET @paymentProcessorStudentFkSql = IF(
  @paymentProcessorStudentFkExists > 0,
  'SELECT 1',
  'ALTER TABLE `tbleventpayment` ADD CONSTRAINT `fkEventPaymentProcessorStudent` FOREIGN KEY (`processedByStudentID`) REFERENCES `tblstudent` (`studentID`) ON DELETE SET NULL ON UPDATE CASCADE'
);
PREPARE paymentProcessorStudentFkStmt FROM @paymentProcessorStudentFkSql;
EXECUTE paymentProcessorStudentFkStmt;
DEALLOCATE PREPARE paymentProcessorStudentFkStmt;
