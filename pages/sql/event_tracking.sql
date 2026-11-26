USE `db_comfysci`;

CREATE TABLE IF NOT EXISTS `tbleventattendanceapproval` (
  `approvalID` int(25) NOT NULL AUTO_INCREMENT,
  `eventID` int(25) NOT NULL,
  `blockID` int(20) NOT NULL,
  `requestedByStudentID` int(25) NOT NULL,
  `approvalStatus` enum('Pending','Approved','Denied') NOT NULL DEFAULT 'Pending',
  `requestedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewedByAdminID` int(25) DEFAULT NULL,
  `reviewedAt` datetime DEFAULT NULL,
  PRIMARY KEY (`approvalID`),
  UNIQUE KEY `uqEventAttendanceApproval` (`eventID`,`blockID`),
  KEY `idxEventApprovalStatus` (`approvalStatus`,`requestedAt`),
  CONSTRAINT `fkEventApprovalEvent` FOREIGN KEY (`eventID`) REFERENCES `tblevent` (`eventID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fkEventApprovalBlock` FOREIGN KEY (`blockID`) REFERENCES `tblblock` (`blockID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fkEventApprovalRep` FOREIGN KEY (`requestedByStudentID`) REFERENCES `tblstudent` (`studentID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fkEventApprovalAdmin` FOREIGN KEY (`reviewedByAdminID`) REFERENCES `tbladmin` (`adminID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tbleventpaymentreceipt` (
  `receiptID` int(25) NOT NULL AUTO_INCREMENT,
  `paymentID` int(25) NOT NULL,
  `receivedByAdminID` int(25) NOT NULL,
  `amountReceived` decimal(10,2) NOT NULL,
  `paymentRemarks` varchar(255) DEFAULT NULL,
  `receivedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`receiptID`),
  KEY `idxEventPaymentReceiptPayment` (`paymentID`,`receivedAt`),
  CONSTRAINT `fkEventPaymentReceiptPayment` FOREIGN KEY (`paymentID`) REFERENCES `tbleventpayment` (`paymentID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fkEventPaymentReceiptAdmin` FOREIGN KEY (`receivedByAdminID`) REFERENCES `tbladmin` (`adminID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `tbleventattendance`
  ADD COLUMN IF NOT EXISTS `markedByStudentID` int(25) DEFAULT NULL;

SET @eventAttendanceMarkerFkExists = (
  SELECT COUNT(*)
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tbleventattendance'
    AND COLUMN_NAME = 'markedByStudentID'
    AND REFERENCED_TABLE_NAME = 'tblstudent'
    AND REFERENCED_COLUMN_NAME = 'studentID'
);
SET @eventAttendanceMarkerFkSql = IF(
  @eventAttendanceMarkerFkExists > 0,
  'SELECT 1',
  'ALTER TABLE `tbleventattendance` ADD CONSTRAINT `fkEventAttendanceMarkerStudent` FOREIGN KEY (`markedByStudentID`) REFERENCES `tblstudent` (`studentID`) ON DELETE SET NULL ON UPDATE CASCADE'
);
PREPARE eventAttendanceMarkerFkStmt FROM @eventAttendanceMarkerFkSql;
EXECUTE eventAttendanceMarkerFkStmt;
DEALLOCATE PREPARE eventAttendanceMarkerFkStmt;
