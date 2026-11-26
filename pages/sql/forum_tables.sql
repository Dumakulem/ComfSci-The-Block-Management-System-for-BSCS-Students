-- Add only the ticket workflow columns and block/forum query indexes to tblmessage.
-- Run this migration once against the application's selected database.

SET @forum_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblmessage' AND COLUMN_NAME = 'ticketStatus') = 0,
    'ALTER TABLE `tblmessage` ADD COLUMN `ticketStatus` VARCHAR(24) NOT NULL DEFAULT ''open''',
    'SELECT 1'
);
PREPARE forum_statement FROM @forum_sql;
EXECUTE forum_statement;
DEALLOCATE PREPARE forum_statement;

SET @forum_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblmessage' AND COLUMN_NAME = 'ticketResponse') = 0,
    'ALTER TABLE `tblmessage` ADD COLUMN `ticketResponse` TEXT NULL',
    'SELECT 1'
);
PREPARE forum_statement FROM @forum_sql;
EXECUTE forum_statement;
DEALLOCATE PREPARE forum_statement;

SET @forum_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblmessage' AND COLUMN_NAME = 'respondedAt') = 0,
    'ALTER TABLE `tblmessage` ADD COLUMN `respondedAt` DATETIME NULL',
    'SELECT 1'
);
PREPARE forum_statement FROM @forum_sql;
EXECUTE forum_statement;
DEALLOCATE PREPARE forum_statement;

SET @forum_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblmessage' AND INDEX_NAME = 'idxForumBlockTypeDate') = 0,
    'ALTER TABLE `tblmessage` ADD INDEX `idxForumBlockTypeDate` (`blockID`, `messageType`, `createdAt`)',
    'SELECT 1'
);
PREPARE forum_statement FROM @forum_sql;
EXECUTE forum_statement;
DEALLOCATE PREPARE forum_statement;

SET @forum_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblmessage' AND INDEX_NAME = 'idxForumStudentTypeDate') = 0,
    'ALTER TABLE `tblmessage` ADD INDEX `idxForumStudentTypeDate` (`studentID`, `messageType`, `createdAt`)',
    'SELECT 1'
);
PREPARE forum_statement FROM @forum_sql;
EXECUTE forum_statement;
DEALLOCATE PREPARE forum_statement;

SET @forum_sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tblmessage' AND INDEX_NAME = 'idxForumTicketQueue') = 0,
    'ALTER TABLE `tblmessage` ADD INDEX `idxForumTicketQueue` (`blockID`, `messageType`, `ticketStatus`, `createdAt`)',
    'SELECT 1'
);
PREPARE forum_statement FROM @forum_sql;
EXECUTE forum_statement;
DEALLOCATE PREPARE forum_statement;
