USE `db_comfysci`;

CREATE TABLE IF NOT EXISTS `tblassignmentcompletion` (
  `assignmentID` int(25) NOT NULL,
  `studentID` int(25) NOT NULL,
  `isCompleted` tinyint(1) NOT NULL DEFAULT 0,
  `completedAt` datetime DEFAULT NULL,
  PRIMARY KEY (`assignmentID`,`studentID`),
  KEY `idxAssignmentCompletionStudent` (`studentID`,`isCompleted`),
  CONSTRAINT `fkAssignmentCompletionAssignment` FOREIGN KEY (`assignmentID`) REFERENCES `tblassignment` (`assignmentID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fkAssignmentCompletionStudent` FOREIGN KEY (`studentID`) REFERENCES `tblstudent` (`studentID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
