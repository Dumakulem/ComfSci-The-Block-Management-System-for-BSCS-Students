CREATE TABLE IF NOT EXISTS `moduleSources` (
  `moduleId` int(25) NOT NULL AUTO_INCREMENT,
  `course` varchar(125) DEFAULT NULL,
  `subject` varchar(100) NOT NULL,
  `title` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `resourceUrl` varchar(500) NOT NULL,
  `blockId` int(20) DEFAULT NULL,
  `blockName` varchar(30) NOT NULL,
  `addedBy` int(25) NOT NULL,
  `addedByType` varchar(20) NOT NULL DEFAULT 'student',
  `dateAndTimeAdded` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`moduleId`),
  KEY `idxModuleBlockId` (`blockId`),
  KEY `idxModuleBlockName` (`blockName`),
  KEY `idxModuleSubject` (`subject`),
  KEY `idxModuleAddedBy` (`addedBy`),
  CONSTRAINT `fkModuleSourceBlock` FOREIGN KEY (`blockId`) REFERENCES `tblblock` (`blockID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
