-- Non-destructive archive fields for admin-managed dashboard records.
USE `db_comfysci`;

ALTER TABLE `tblstudent`
  ADD COLUMN IF NOT EXISTS `archivedAt` datetime DEFAULT NULL;

ALTER TABLE `tblblock`
  ADD COLUMN IF NOT EXISTS `archivedAt` datetime DEFAULT NULL;

ALTER TABLE `tblassignment`
  ADD COLUMN IF NOT EXISTS `archivedAt` datetime DEFAULT NULL;

ALTER TABLE `tblevent`
  ADD COLUMN IF NOT EXISTS `archivedAt` datetime DEFAULT NULL;

ALTER TABLE `tblannouncement`
  ADD COLUMN IF NOT EXISTS `archivedAt` datetime DEFAULT NULL;
