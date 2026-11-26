-- Rerunnable resource repository schema for db_comfysci.
USE `db_comfysci`;

CREATE TABLE IF NOT EXISTS `tblresourcecategory` (
  `categoryID` int(25) NOT NULL AUTO_INCREMENT,
  `categoryName` varchar(80) NOT NULL,
  `categoryDescription` varchar(255) NOT NULL DEFAULT '',
  `adminID` int(25) NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`categoryID`),
  UNIQUE KEY `uqResourceCategoryName` (`categoryName`),
  KEY `idxResourceCategoryAdmin` (`adminID`),
  CONSTRAINT `fkResourceCategoryAdmin` FOREIGN KEY (`adminID`) REFERENCES `tbladmin` (`adminID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tblresource` (
  `resourceID` int(25) NOT NULL AUTO_INCREMENT,
  `categoryID` int(25) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` varchar(2000) NOT NULL DEFAULT '',
  `subject` varchar(100) NOT NULL,
  `resourceType` enum('document','video','article','notes','other') NOT NULL DEFAULT 'document',
  `resourceUrl` varchar(2048) NOT NULL,
  `adminID` int(25) NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `updatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`resourceID`),
  KEY `idxResourceCategorySubject` (`categoryID`,`subject`),
  KEY `idxResourceSubject` (`subject`),
  KEY `idxResourceType` (`resourceType`),
  KEY `idxResourceUpdated` (`updatedAt`),
  KEY `idxResourceAdmin` (`adminID`),
  CONSTRAINT `fkResourceCategory` FOREIGN KEY (`categoryID`) REFERENCES `tblresourcecategory` (`categoryID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fkResourceAdmin` FOREIGN KEY (`adminID`) REFERENCES `tbladmin` (`adminID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
