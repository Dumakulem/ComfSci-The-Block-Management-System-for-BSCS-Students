-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 12, 2026 at 08:47 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_comfysci`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbladmin`
--

CREATE TABLE `tbladmin` (
  `adminID` int(25) NOT NULL,
  `adminLastName` varchar(25) NOT NULL,
  `adminFirstName` varchar(25) NOT NULL,
  `adminMiddleName` varchar(25) DEFAULT NULL,
  `adminExtensionName` varchar(10) DEFAULT NULL,
  `adminEmail` varchar(100) NOT NULL,
  `adminPassword` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbladmin`
--

INSERT INTO `tbladmin` (`adminID`, `adminLastName`, `adminFirstName`, `adminMiddleName`, `adminExtensionName`, `adminEmail`, `adminPassword`) VALUES
(4, 'Perez', 'Emilio James', NULL, NULL, 'miliojames21@gmail.com', '$2y$10$OMuQiHiVS3noKlYFCMGGqe9zIEmQ3rRlEiVddKUr30R2mJ/seoGee');

-- --------------------------------------------------------

--
-- Table structure for table `tblannouncement`
--

CREATE TABLE `tblannouncement` (
  `announcementID` int(25) NOT NULL,
  `adminID` int(25) NOT NULL,
  `announcementTitle` varchar(150) NOT NULL,
  `announcementContent` text NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblanswer`
--

CREATE TABLE `tblanswer` (
  `answerID` int(25) NOT NULL,
  `questionID` int(25) NOT NULL,
  `answerText` varchar(255) NOT NULL,
  `isCorrect` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblassignment`
--

CREATE TABLE `tblassignment` (
  `assignmentID` int(25) NOT NULL,
  `blockID` int(20) NOT NULL,
  `assignmentTitle` varchar(150) NOT NULL,
  `assignmentSubject` varchar(150) NOT NULL,
  `assignmentDescription` text DEFAULT NULL,
  `assignmentFilePath` varchar(255) DEFAULT NULL,
  `assignmentStatus` varchar(25) NOT NULL,
  `assignmentDeadline` datetime NOT NULL,
  `isEmailSent` tinyint(1) NOT NULL DEFAULT 0,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `editedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblblock`
--

CREATE TABLE `tblblock` (
  `blockID` int(20) NOT NULL,
  `classBlock` varchar(30) NOT NULL,
  `courseID` int(25) NOT NULL,
  `facultyID` int(25) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblcourse`
--

CREATE TABLE `tblcourse` (
  `courseID` int(25) NOT NULL,
  `courseName` varchar(125) NOT NULL,
  `curriculumName` varchar(125) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblevent`
--

CREATE TABLE `tblevent` (
  `eventID` int(25) NOT NULL,
  `adminID` int(25) NOT NULL,
  `eventTitle` varchar(150) NOT NULL,
  `eventDescription` text DEFAULT NULL,
  `eventDate` date NOT NULL,
  `eventStartTime` time NOT NULL,
  `eventEndTime` time DEFAULT NULL,
  `eventLocation` varchar(150) NOT NULL,
  `participationFee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `eventStatus` varchar(25) NOT NULL DEFAULT 'Upcoming',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbleventattendance`
--

CREATE TABLE `tbleventattendance` (
  `attendanceID` int(25) NOT NULL,
  `registrationID` int(25) NOT NULL,
  `markedByAdminID` int(25) DEFAULT NULL,
  `attendanceStatus` varchar(20) NOT NULL DEFAULT 'absent',
  `timeIn` datetime DEFAULT NULL,
  `timeOut` datetime DEFAULT NULL,
  `markedAt` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbleventpayment`
--

CREATE TABLE `tbleventpayment` (
  `paymentID` int(25) NOT NULL,
  `registrationID` int(25) NOT NULL,
  `receivedByAdminID` int(25) DEFAULT NULL,
  `amountDue` decimal(10,2) NOT NULL,
  `amountPaid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `paymentStatus` varchar(20) NOT NULL DEFAULT 'Unpaid',
  `paymentDate` datetime DEFAULT NULL,
  `paymentRemarks` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbleventregistration`
--

CREATE TABLE `tbleventregistration` (
  `registrationID` int(25) NOT NULL,
  `eventID` int(25) NOT NULL,
  `studentID` int(25) NOT NULL,
  `registeredAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `registrationStatus` varchar(25) NOT NULL DEFAULT 'Registered'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblfaculty`
--

CREATE TABLE `tblfaculty` (
  `facultyID` int(25) NOT NULL,
  `facultyLastName` varchar(25) NOT NULL,
  `facultyFirstName` varchar(25) NOT NULL,
  `facultyMiddleName` varchar(25) DEFAULT NULL,
  `facultyExtensionName` varchar(10) DEFAULT NULL,
  `facultyEmail` varchar(100) NOT NULL,
  `facultyPassword` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblgamequiz`
--

CREATE TABLE `tblgamequiz` (
  `gameID` int(25) NOT NULL,
  `studentID` int(25) NOT NULL,
  `quizID` int(25) NOT NULL,
  `score` int(11) NOT NULL DEFAULT 0,
  `totalPoints` int(11) NOT NULL DEFAULT 0,
  `startedAt` datetime DEFAULT NULL,
  `endedAt` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblgroup`
--

CREATE TABLE `tblgroup` (
  `groupID` int(25) NOT NULL,
  `blockID` int(20) NOT NULL,
  `groupLeaderID` int(25) NOT NULL,
  `assignmentID` int(25) NOT NULL,
  `groupTitle` varchar(100) NOT NULL,
  `groupDescription` text DEFAULT NULL,
  `groupStatus` varchar(50) NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `editedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblmessage`
--

CREATE TABLE `tblmessage` (
  `messageID` int(25) NOT NULL,
  `blockID` int(20) NOT NULL,
  `studentID` int(25) NOT NULL,
  `messageContent` varchar(2000) NOT NULL,
  `attachmentPath` varchar(255) DEFAULT NULL,
  `messageType` enum('message','note','ticket') NOT NULL DEFAULT 'message',
  `ticketCategory` enum('emergency','illness','complaint') DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `tblquestion`
--

CREATE TABLE `tblquestion` (
  `questionID` int(25) NOT NULL,
  `quizID` int(25) NOT NULL,
  `questionText` text NOT NULL,
  `questionType` varchar(30) NOT NULL,
  `questionPoints` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblquiz`
--

CREATE TABLE `tblquiz` (
  `quizID` int(25) NOT NULL,
  `adminID` int(25) NOT NULL,
  `quizTitle` varchar(150) NOT NULL,
  `quizDescription` text DEFAULT NULL,
  `quizType` varchar(50) NOT NULL,
  `isPublic` tinyint(1) NOT NULL DEFAULT 1,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tblstudent`
--

CREATE TABLE `tblstudent` (
  `studentID` int(25) NOT NULL,
  `studentLastName` varchar(25) NOT NULL,
  `studentFirstName` varchar(25) NOT NULL,
  `studentMiddleName` varchar(25) DEFAULT NULL,
  `studentExtensionName` varchar(10) DEFAULT NULL,
  `studentEmail` varchar(100) NOT NULL,
  `studentPassword` varchar(255) NOT NULL,
  `isClassRep` tinyint(1) NOT NULL DEFAULT 0,
  `isGroupLeader` tinyint(1) NOT NULL DEFAULT 0,
  `blockID` int(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `vwleaderboard`
-- (See below for the actual view)
--
CREATE TABLE `vwleaderboard` (
`quizID` int(25)
,`studentID` int(25)
,`studentName` varchar(51)
,`bestScore` int(11)
,`lastPlayedAt` datetime
,`leaderboardRank` bigint(21)
);

-- --------------------------------------------------------

--
-- Structure for view `vwleaderboard`
--
DROP TABLE IF EXISTS `vwleaderboard`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vwleaderboard`  AS SELECT `g`.`quizID` AS `quizID`, `g`.`studentID` AS `studentID`, concat(`s`.`studentFirstName`,' ',`s`.`studentLastName`) AS `studentName`, max(`g`.`score`) AS `bestScore`, max(`g`.`endedAt`) AS `lastPlayedAt`, rank() over ( partition by `g`.`quizID` order by max(`g`.`score`) desc) AS `leaderboardRank` FROM (`tblgamequiz` `g` join `tblstudent` `s` on(`s`.`studentID` = `g`.`studentID`)) GROUP BY `g`.`quizID`, `g`.`studentID`, `s`.`studentFirstName`, `s`.`studentLastName` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbladmin`
--
ALTER TABLE `tbladmin`
  ADD PRIMARY KEY (`adminID`),
  ADD UNIQUE KEY `adminEmail` (`adminEmail`);

--
-- Indexes for table `tblannouncement`
--
ALTER TABLE `tblannouncement`
  ADD PRIMARY KEY (`announcementID`),
  ADD KEY `idxAnnouncementAdmin` (`adminID`);

--
-- Indexes for table `tblanswer`
--
ALTER TABLE `tblanswer`
  ADD PRIMARY KEY (`answerID`),
  ADD KEY `idxAnswerQuestion` (`questionID`);

--
-- Indexes for table `tblassignment`
--
ALTER TABLE `tblassignment`
  ADD PRIMARY KEY (`assignmentID`),
  ADD KEY `idxAssignmentBlock` (`blockID`,`assignmentDeadline`);

--
-- Indexes for table `tblblock`
--
ALTER TABLE `tblblock`
  ADD PRIMARY KEY (`blockID`),
  ADD KEY `courseID` (`courseID`),
  ADD KEY `idxBlockFaculty` (`facultyID`);

--
-- Indexes for table `tblcourse`
--
ALTER TABLE `tblcourse`
  ADD PRIMARY KEY (`courseID`);

--
-- Indexes for table `tblevent`
--
ALTER TABLE `tblevent`
  ADD PRIMARY KEY (`eventID`),
  ADD KEY `idxEventAdmin` (`adminID`),
  ADD KEY `idxEventDate` (`eventDate`,`eventStatus`);

--
-- Indexes for table `tbleventattendance`
--
ALTER TABLE `tbleventattendance`
  ADD PRIMARY KEY (`attendanceID`),
  ADD UNIQUE KEY `uqAttendanceRegistration` (`registrationID`),
  ADD KEY `idxAttendanceAdmin` (`markedByAdminID`);

--
-- Indexes for table `tbleventpayment`
--
ALTER TABLE `tbleventpayment`
  ADD PRIMARY KEY (`paymentID`),
  ADD UNIQUE KEY `uqPaymentRegistration` (`registrationID`),
  ADD KEY `idxPaymentAdmin` (`receivedByAdminID`);

--
-- Indexes for table `tbleventregistration`
--
ALTER TABLE `tbleventregistration`
  ADD PRIMARY KEY (`registrationID`),
  ADD UNIQUE KEY `uqEventStudent` (`eventID`,`studentID`),
  ADD KEY `idxRegistrationStudent` (`studentID`);

--
-- Indexes for table `tblfaculty`
--
ALTER TABLE `tblfaculty`
  ADD PRIMARY KEY (`facultyID`),
  ADD UNIQUE KEY `uqFacultyEmail` (`facultyEmail`);

--
-- Indexes for table `tblgamequiz`
--
ALTER TABLE `tblgamequiz`
  ADD PRIMARY KEY (`gameID`),
  ADD KEY `idxGameStudent` (`studentID`),
  ADD KEY `idxGameQuizScore` (`quizID`,`score`);

--
-- Indexes for table `tblgroup`
--
ALTER TABLE `tblgroup`
  ADD PRIMARY KEY (`groupID`),
  ADD KEY `idxGroupBlock` (`blockID`),
  ADD KEY `idxGroupLeader` (`groupLeaderID`),
  ADD KEY `idxGroupAssignment` (`assignmentID`);

--
-- Indexes for table `tblmessage`
--
ALTER TABLE `tblmessage`
  ADD PRIMARY KEY (`messageID`),
  ADD KEY `idxMessageBlock` (`blockID`,`createdAt`),
  ADD KEY `idxMessageStudent` (`studentID`);

--
-- Indexes for table `tblquestion`
--
ALTER TABLE `tblquestion`
  ADD PRIMARY KEY (`questionID`),
  ADD KEY `idxQuestionQuiz` (`quizID`);

--
-- Indexes for table `tblquiz`
--
ALTER TABLE `tblquiz`
  ADD PRIMARY KEY (`quizID`),
  ADD KEY `idxQuizAdmin` (`adminID`);

--
-- Indexes for table `tblstudent`
--
ALTER TABLE `tblstudent`
  ADD PRIMARY KEY (`studentID`),
  ADD UNIQUE KEY `studentEmail` (`studentEmail`),
  ADD KEY `blockID` (`blockID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbladmin`
--
ALTER TABLE `tbladmin`
  MODIFY `adminID` int(25) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tblannouncement`
--
ALTER TABLE `tblannouncement`
  MODIFY `announcementID` int(25) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblanswer`
--
ALTER TABLE `tblanswer`
  MODIFY `answerID` int(25) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblassignment`
--
ALTER TABLE `tblassignment`
  MODIFY `assignmentID` int(25) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblblock`
--
ALTER TABLE `tblblock`
  MODIFY `blockID` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tblcourse`
--
ALTER TABLE `tblcourse`
  MODIFY `courseID` int(25) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tblevent`
--
ALTER TABLE `tblevent`
  MODIFY `eventID` int(25) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbleventattendance`
--
ALTER TABLE `tbleventattendance`
  MODIFY `attendanceID` int(25) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbleventpayment`
--
ALTER TABLE `tbleventpayment`
  MODIFY `paymentID` int(25) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbleventregistration`
--
ALTER TABLE `tbleventregistration`
  MODIFY `registrationID` int(25) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblfaculty`
--
ALTER TABLE `tblfaculty`
  MODIFY `facultyID` int(25) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblgamequiz`
--
ALTER TABLE `tblgamequiz`
  MODIFY `gameID` int(25) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblgroup`
--
ALTER TABLE `tblgroup`
  MODIFY `groupID` int(25) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblmessage`
--
ALTER TABLE `tblmessage`
  MODIFY `messageID` int(25) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblquestion`
--
ALTER TABLE `tblquestion`
  MODIFY `questionID` int(25) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblquiz`
--
ALTER TABLE `tblquiz`
  MODIFY `quizID` int(25) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblstudent`
--
ALTER TABLE `tblstudent`
  MODIFY `studentID` int(25) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tblannouncement`
--
ALTER TABLE `tblannouncement`
  ADD CONSTRAINT `fkAnnouncementAdmin` FOREIGN KEY (`adminID`) REFERENCES `tbladmin` (`adminID`) ON UPDATE CASCADE;

--
-- Constraints for table `tblanswer`
--
ALTER TABLE `tblanswer`
  ADD CONSTRAINT `fkAnswerQuestion` FOREIGN KEY (`questionID`) REFERENCES `tblquestion` (`questionID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tblassignment`
--
ALTER TABLE `tblassignment`
  ADD CONSTRAINT `fkAssignmentBlock` FOREIGN KEY (`blockID`) REFERENCES `tblblock` (`blockID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tblblock`
--
ALTER TABLE `tblblock`
  ADD CONSTRAINT `fkBlockFaculty` FOREIGN KEY (`facultyID`) REFERENCES `tblfaculty` (`facultyID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tblblock_ibfk_1` FOREIGN KEY (`courseID`) REFERENCES `tblcourse` (`courseID`);

--
-- Constraints for table `tblevent`
--
ALTER TABLE `tblevent`
  ADD CONSTRAINT `fkEventAdmin` FOREIGN KEY (`adminID`) REFERENCES `tbladmin` (`adminID`) ON UPDATE CASCADE;

--
-- Constraints for table `tbleventattendance`
--
ALTER TABLE `tbleventattendance`
  ADD CONSTRAINT `fkAttendanceAdmin` FOREIGN KEY (`markedByAdminID`) REFERENCES `tbladmin` (`adminID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fkAttendanceRegistration` FOREIGN KEY (`registrationID`) REFERENCES `tbleventregistration` (`registrationID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbleventpayment`
--
ALTER TABLE `tbleventpayment`
  ADD CONSTRAINT `fkPaymentAdmin` FOREIGN KEY (`receivedByAdminID`) REFERENCES `tbladmin` (`adminID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fkPaymentRegistration` FOREIGN KEY (`registrationID`) REFERENCES `tbleventregistration` (`registrationID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbleventregistration`
--
ALTER TABLE `tbleventregistration`
  ADD CONSTRAINT `fkRegistrationEvent` FOREIGN KEY (`eventID`) REFERENCES `tblevent` (`eventID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fkRegistrationStudent` FOREIGN KEY (`studentID`) REFERENCES `tblstudent` (`studentID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tblgamequiz`
--
ALTER TABLE `tblgamequiz`
  ADD CONSTRAINT `fkGameQuiz` FOREIGN KEY (`quizID`) REFERENCES `tblquiz` (`quizID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fkGameStudent` FOREIGN KEY (`studentID`) REFERENCES `tblstudent` (`studentID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tblgroup`
--
ALTER TABLE `tblgroup`
  ADD CONSTRAINT `fkGroupAssignment` FOREIGN KEY (`assignmentID`) REFERENCES `tblassignment` (`assignmentID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fkGroupBlock` FOREIGN KEY (`blockID`) REFERENCES `tblblock` (`blockID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fkGroupLeader` FOREIGN KEY (`groupLeaderID`) REFERENCES `tblstudent` (`studentID`) ON UPDATE CASCADE;

--
-- Constraints for table `tblmessage`
--
ALTER TABLE `tblmessage`
  ADD CONSTRAINT `fkMessageBlock` FOREIGN KEY (`blockID`) REFERENCES `tblblock` (`blockID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fkMessageStudent` FOREIGN KEY (`studentID`) REFERENCES `tblstudent` (`studentID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tblquestion`
--
ALTER TABLE `tblquestion`
  ADD CONSTRAINT `fkQuestionQuiz` FOREIGN KEY (`quizID`) REFERENCES `tblquiz` (`quizID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tblquiz`
--
ALTER TABLE `tblquiz`
  ADD CONSTRAINT `fkQuizAdmin` FOREIGN KEY (`adminID`) REFERENCES `tbladmin` (`adminID`) ON UPDATE CASCADE;

--
-- Constraints for table `tblstudent`
--
ALTER TABLE `tblstudent`
  ADD CONSTRAINT `tblstudent_ibfk_1` FOREIGN KEY (`blockID`) REFERENCES `tblblock` (`blockID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
