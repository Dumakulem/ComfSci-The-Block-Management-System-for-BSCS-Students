-- Add file-upload support to Module Sources (PDFs and documents).
-- Safe to re-run.

USE `db_comfysci`;

ALTER TABLE `moduleSources`
  MODIFY `resourceUrl` varchar(500) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `fileName` varchar(255) DEFAULT NULL AFTER `resourceUrl`,
  ADD COLUMN IF NOT EXISTS `originalName` varchar(255) DEFAULT NULL AFTER `fileName`,
  ADD COLUMN IF NOT EXISTS `mimeType` varchar(120) DEFAULT NULL AFTER `originalName`,
  ADD COLUMN IF NOT EXISTS `fileSize` int(11) unsigned DEFAULT NULL AFTER `mimeType`;

-- MySQL < 8.0.12 does not support IF NOT EXISTS on ADD COLUMN.
-- If the above fails, run the individual statements below instead:

-- ALTER TABLE `moduleSources` MODIFY `resourceUrl` varchar(500) DEFAULT NULL;
-- ALTER TABLE `moduleSources` ADD COLUMN `fileName` varchar(255) DEFAULT NULL AFTER `resourceUrl`;
-- ALTER TABLE `moduleSources` ADD COLUMN `originalName` varchar(255) DEFAULT NULL AFTER `fileName`;
-- ALTER TABLE `moduleSources` ADD COLUMN `mimeType` varchar(120) DEFAULT NULL AFTER `originalName`;
-- ALTER TABLE `moduleSources` ADD COLUMN `fileSize` int(11) unsigned DEFAULT NULL AFTER `mimeType`;
