# ComfSci-The-Block-Management-System-for-BSCS-Students

## Feature module setup

The feature modules use the existing `db_comfysci` schema. After importing the base ComfSci schema, select `db_comfysci` in phpMyAdmin and import these rerunnable migrations:

1. `pages/sql/assignment_tracking.sql`
2. `pages/sql/event_tracking.sql`
3. `pages/sql/forum_tables.sql`
4. `pages/sql/group_activity_tables.sql`
5. `pages/sql/resource_tables.sql`
6. `pages/sql/module_sources.sql`
7. `pages/sql/ai_consultation_tables.sql`

If the Gamified Quiz migration has not already been applied, also import `pages/sql/gamified_quiz_tables.sql`.

The Module Sources page lists links for the signed-in student's block. Administrators and block representatives can add, edit, and delete module links; changes are stored in `moduleSources`.

### AI assistant providers

The original-style floating ComfiBot widget appears on authenticated ComfSci pages. It uses ComfSci authentication, admin access revocation, provider failover, and the 10-question student daily limit. Chat history stays in the current page and is not saved. Page title and path are sent with a message; visible page text is sent only when the user explicitly opts in for that message.

The AI assistant requires cURL and one or more OpenAI-compatible chat-completions providers configured in the environment of the Apache/PHP process. For each provider, set:

- `COMFSCI_AI_PROVIDER_1_BASE_URL`
- `COMFSCI_AI_PROVIDER_1_API_KEY`
- `COMFSCI_AI_PROVIDER_1_MODEL`

Add providers with the next numeric index (for example, `_2_`) to enable failover. Set provider credentials outside the project; do not commit API keys. Restart Apache after changing its environment. Student requests are limited to 10 per day; administrators bypass the limit and can revoke or restore student access.

The grade breakdown is stored in the user's browser and does not assume a college grading scheme. Ticket proof attachments are optional and limited to JPG, PNG, or PDF files.

### Event absence finalizer

To insert absent records automatically after approved events end, schedule `pages/cron/finalize_event_absences.php` with Windows Task Scheduler to run every few minutes. Use `C:\xampp\php\php.exe` as the program and the script's full path as the argument. The event attendance page also reconciles absences when a block representative opens it after an event.