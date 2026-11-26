# Preserving the original Assignment Tracker database

The original Assignment Tracker database dump was supplied and is included as a **one-time archive import**. It preserves the old records, original primary IDs, admin credentials as hashes, and uploaded file binary data. The archive tables use the `legacy_at_` prefix so they do not conflict with ComfSci tables or the active Assignment Tracker tables.

## Import the archived data

1. Back up your ComfSci database first.
2. Install the integrated Assignment Tracker tables using the main ComfSci installer.
3. In phpMyAdmin, select the `db_comfysci` database.
4. Import `sql/legacy_data_archive_import.sql` once. This import is intentionally separate from the normal installer because it contains large file blobs and historical records.
5. Confirm that the `legacy_at_*` tables appear. **Do not run the archive import twice**; it is a one-time import.

## Why archived instead of automatically inserted into active tables?

The legacy data uses class codes `1111`, `2222`, and `3333`, and student IDs that do not match the ComfSci `tblstudent` records in the supplied database. ComfSci's `tblblock` rows also do not contain enough information to confidently map all three old classes. Automatically guessing would associate assignments/submissions with the wrong students or classes.

All legacy records remain preserved in the archive tables, including assignment records, group assignments, group members, student submissions, files, and feedback. The active Assignment Tracker uses ComfSci authentication and its existing student/block records. The archive can be migrated into active tables later after a verified class/student mapping is provided.

The archive intentionally does not import the old database's event scheduler or old admins/students into ComfSci authentication.
