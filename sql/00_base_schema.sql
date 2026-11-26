-- ComfSci base schema (reconstructed from the application code).
-- Run this FIRST, then the migrations in pages/sql/ in the order listed in README.md.
CREATE DATABASE IF NOT EXISTS `db_comfysci` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `db_comfysci`;

CREATE TABLE IF NOT EXISTS `tblcourse` (
  `courseID` int(25) NOT NULL AUTO_INCREMENT,
  `courseName` varchar(150) NOT NULL,
  `curriculumName` varchar(150) NOT NULL DEFAULT '',
  PRIMARY KEY (`courseID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tblfaculty` (
  `facultyID` int(25) NOT NULL AUTO_INCREMENT,
  `facultyLastName` varchar(80) NOT NULL,
  `facultyFirstName` varchar(80) NOT NULL,
  `facultyMiddleName` varchar(80) DEFAULT NULL,
  `facultyExtensionName` varchar(20) DEFAULT NULL,
  `facultyEmail` varchar(150) NOT NULL,
  `facultyPassword` varchar(255) NOT NULL,
  PRIMARY KEY (`facultyID`),
  UNIQUE KEY `uqFacultyEmail` (`facultyEmail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tbladmin` (
  `adminID` int(25) NOT NULL AUTO_INCREMENT,
  `adminLastName` varchar(80) NOT NULL,
  `adminFirstName` varchar(80) NOT NULL,
  `adminMiddleName` varchar(80) DEFAULT NULL,
  `adminExtensionName` varchar(20) DEFAULT NULL,
  `adminEmail` varchar(150) NOT NULL,
  `adminPassword` varchar(255) NOT NULL,
  PRIMARY KEY (`adminID`),
  UNIQUE KEY `uqAdminEmail` (`adminEmail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tblblock` (
  `blockID` int(20) NOT NULL AUTO_INCREMENT,
  `classBlock` varchar(50) NOT NULL,
  `courseID` int(25) NOT NULL,
  `facultyID` int(25) DEFAULT NULL,
  PRIMARY KEY (`blockID`),
  CONSTRAINT `fkBlockCourse` FOREIGN KEY (`courseID`) REFERENCES `tblcourse` (`courseID`) ON UPDATE CASCADE,
  CONSTRAINT `fkBlockFaculty` FOREIGN KEY (`facultyID`) REFERENCES `tblfaculty` (`facultyID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tblstudent` (
  `studentID` int(25) NOT NULL AUTO_INCREMENT,
  `studentLastName` varchar(80) NOT NULL,
  `studentFirstName` varchar(80) NOT NULL,
  `studentMiddleName` varchar(80) DEFAULT NULL,
  `studentExtensionName` varchar(20) DEFAULT NULL,
  `studentEmail` varchar(150) NOT NULL,
  `studentPassword` varchar(255) NOT NULL,
  `isClassRep` tinyint(1) NOT NULL DEFAULT 0,
  `isGroupLeader` tinyint(1) NOT NULL DEFAULT 0,
  `blockID` int(20) NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`studentID`),
  UNIQUE KEY `uqStudentEmail` (`studentEmail`),
  KEY `idxStudentBlock` (`blockID`),
  CONSTRAINT `fkStudentBlock` FOREIGN KEY (`blockID`) REFERENCES `tblblock` (`blockID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tblannouncement` (
  `announcementID` int(25) NOT NULL AUTO_INCREMENT,
  `adminID` int(25) NOT NULL,
  `announcementTitle` varchar(200) NOT NULL,
  `announcementContent` text NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`announcementID`),
  CONSTRAINT `fkAnnouncementAdmin` FOREIGN KEY (`adminID`) REFERENCES `tbladmin` (`adminID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tblassignment` (
  `assignmentID` int(25) NOT NULL AUTO_INCREMENT,
  `blockID` int(20) NOT NULL,
  `assignmentTitle` varchar(200) NOT NULL,
  `assignmentSubject` varchar(120) NOT NULL DEFAULT '',
  `assignmentDescription` text DEFAULT NULL,
  `assignmentFilePath` varchar(255) DEFAULT NULL,
  `assignmentStatus` varchar(30) NOT NULL DEFAULT 'Active',
  `assignmentDeadline` datetime NOT NULL,
  `isEmailSent` tinyint(1) NOT NULL DEFAULT 0,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`assignmentID`),
  CONSTRAINT `fkAssignmentBlock` FOREIGN KEY (`blockID`) REFERENCES `tblblock` (`blockID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tblevent` (
  `eventID` int(25) NOT NULL AUTO_INCREMENT,
  `adminID` int(25) NOT NULL,
  `eventTitle` varchar(200) NOT NULL,
  `eventDescription` text DEFAULT NULL,
  `eventDate` date NOT NULL,
  `eventStartTime` time NOT NULL,
  `eventEndTime` time DEFAULT NULL,
  `eventLocation` varchar(200) NOT NULL DEFAULT '',
  `participationFee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `eventStatus` varchar(20) NOT NULL DEFAULT 'Upcoming',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`eventID`),
  CONSTRAINT `fkEventAdmin` FOREIGN KEY (`adminID`) REFERENCES `tbladmin` (`adminID`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tbleventregistration` (
  `registrationID` int(25) NOT NULL AUTO_INCREMENT,
  `eventID` int(25) NOT NULL,
  `studentID` int(25) NOT NULL,
  `registrationStatus` varchar(30) NOT NULL DEFAULT 'Registered',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`registrationID`),
  UNIQUE KEY `uqEventStudent` (`eventID`,`studentID`),
  CONSTRAINT `fkRegEvent` FOREIGN KEY (`eventID`) REFERENCES `tblevent` (`eventID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fkRegStudent` FOREIGN KEY (`studentID`) REFERENCES `tblstudent` (`studentID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tbleventpayment` (
  `paymentID` int(25) NOT NULL AUTO_INCREMENT,
  `registrationID` int(25) NOT NULL,
  `amountDue` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amountPaid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `paymentStatus` varchar(30) NOT NULL DEFAULT 'Unpaid',
  `paymentRemarks` varchar(255) DEFAULT NULL,
  `paymentDate` datetime DEFAULT NULL,
  `receivedByAdminID` int(25) DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`paymentID`),
  UNIQUE KEY `uqPaymentRegistration` (`registrationID`),
  CONSTRAINT `fkPaymentRegistration` FOREIGN KEY (`registrationID`) REFERENCES `tbleventregistration` (`registrationID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fkPaymentReceivedBy` FOREIGN KEY (`receivedByAdminID`) REFERENCES `tbladmin` (`adminID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tbleventattendance` (
  `attendanceID` int(25) NOT NULL AUTO_INCREMENT,
  `registrationID` int(25) NOT NULL,
  `attendanceStatus` varchar(30) NOT NULL DEFAULT 'absent',
  `timeIn` datetime DEFAULT NULL,
  `timeOut` datetime DEFAULT NULL,
  `markedByAdminID` int(25) DEFAULT NULL,
  `markedAt` datetime DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attendanceID`),
  UNIQUE KEY `uqAttendanceRegistration` (`registrationID`),
  CONSTRAINT `fkAttendanceRegistration` FOREIGN KEY (`registrationID`) REFERENCES `tbleventregistration` (`registrationID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fkAttendanceMarkerAdmin` FOREIGN KEY (`markedByAdminID`) REFERENCES `tbladmin` (`adminID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tblmessage` (
  `messageID` int(25) NOT NULL AUTO_INCREMENT,
  `blockID` int(20) NOT NULL,
  `studentID` int(25) NOT NULL,
  `messageContent` text NOT NULL,
  `attachmentPath` varchar(255) DEFAULT NULL,
  `messageType` varchar(20) NOT NULL DEFAULT 'message',
  `ticketCategory` varchar(60) DEFAULT NULL,
  `createdAt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`messageID`),
  CONSTRAINT `fkMessageBlock` FOREIGN KEY (`blockID`) REFERENCES `tblblock` (`blockID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fkMessageStudent` FOREIGN KEY (`studentID`) REFERENCES `tblstudent` (`studentID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
