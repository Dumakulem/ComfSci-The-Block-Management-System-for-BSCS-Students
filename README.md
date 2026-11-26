# ComfSci-The-Block-Management-System-for-BSCS-Students

## Quick start (XAMPP / any PHP 8+ with MySQL or MariaDB)

1. Copy `.env.example` to `.env` and fill in `GROQ_API_KEY` (optional; only needed for the AI assistant).
2. From the project folder run the installer. It creates `db_comfysci`, the base tables, and every migration in the correct order, and can create your first administrator:

   ```
   C:\xampp\php\php.exe install.php admin@gordoncollege.edu.ph YourPassword "First" "Last"
   ```

   The installer is safe to re-run.
3. Serve the folder with Apache (or `php -S 127.0.0.1:8080`) and open `/auth/login.php`.

Manual alternative: import `sql/00_base_schema.sql`, then the files in `pages/sql/` in the order listed in `install.php`. `admin_archive_columns.sql` is required (login filters on `archivedAt`) and must run before the other migrations.

## Feature notes

The Attendance Tracker lives under `pages/attendance/` and uses ComfSci login, roles, and the shared database. Representatives request administrator approval before recording attendance; students and administrators see the same resulting records.

The Module Sources page lists links for the signed-in student's block. Administrators and block representatives can add, edit, and delete module links; changes are stored in `moduleSources`.

### AI assistant providers

The original-style floating ComfiBot widget appears on authenticated ComfSci pages. It uses ComfSci authentication, admin access revocation, provider failover, and the 10-question student daily limit. Chat history stays in the current page and is not saved. Page title and path are sent with a message; visible page text is sent only when the user explicitly opts in for that message.

Set the primary provider with `GROQ_API_KEY` (and optionally `GROQ_API_URL`, `GROQ_MODEL`) in `.env`. `config.php` contains no secrets; `.env` is excluded from Git. Additional providers can still be configured through Apache/PHP environment variables; matching provider 1 variables override the file values:

- `COMFSCI_AI_PROVIDER_1_BASE_URL`
- `COMFSCI_AI_PROVIDER_1_API_KEY`
- `COMFSCI_AI_PROVIDER_1_MODEL`

Add providers with the next numeric index (for example, `_2_`) to enable failover. The AI assistant requires cURL. Student requests are limited to 10 per day; administrators bypass the limit and can revoke or restore student access.

The grade breakdown is stored in the user's browser and does not assume a college grading scheme. Ticket proof attachments are optional and limited to JPG, PNG, or PDF files.

### Event absence finalizer

To insert absent records automatically after approved events end, schedule `pages/cron/finalize_event_absences.php` with Windows Task Scheduler to run every few minutes. Use `C:\xampp\php\php.exe` as the program and the script's full path as the argument. The event attendance page also reconciles absences when a block representative opens it after an event.
### Assignment Tracker module

Assignment Tracker is integrated under `pages/assignmenttracker/` and uses the existing ComfSci authentication session and `db()` PDO connection. Student blocks are derived from `tblstudent.blockID`; administrators select an existing block from `tblblock`. The module does not create or maintain separate student, administrator, or class records, and its old login, account-creation, and email-verification routes redirect to ComfSci.


The main installer also creates `pages/assignmenttracker/sql/assignment_tracker_tables.sql` tables automatically. Assignment Tracker has its own tables prefixed with `at_`, while students, blocks, and administrators remain in the main ComfSci tables.


Email reminders are designed to send one reminder within 24 hours before the deadline and one overdue notice after the deadline to students who have not submitted. Configure the `COMFSCI_SMTP_*` variables in `.env` before enabling the scheduled job. Run `pages/assignmenttracker/cron/send_assignment_notifications.php` hourly using Windows Task Scheduler or cron. If SMTP values are not configured, the script logs the issue and does not record a notification as sent.
## Preserving data from the original Assignment Tracker

The original Assignment Tracker database data is preserved in a separate one-time archive import: `pages/assignmenttracker/sql/legacy_data_archive_import.sql`. Follow `pages/assignmenttracker/MIGRATION_DATA.md` before importing it. The legacy class/student IDs do not directly match the supplied ComfSci database, so the historical records are archived without guessed mappings into active tables.
