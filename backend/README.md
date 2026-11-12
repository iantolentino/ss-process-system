# backend/

The logic behind the pages.

- `bootstrap.php` loaded first by everything: `.env` reader, database handle, log files, `audit()`, email (`notify()` / `smtp_send()`), `error_page()`.
- `services/` business rules per area: `users`, `documents`, `processes`, `onboarding`. Pages call these; they validate input and write the audit trail.
- `cron/` scripts run by the host's scheduler: `backup.php` (daily), `clean_logs.php` (daily), `review_notices.php` (weekly). See `docs/DEPLOY.md`.

Rules of thumb: every create / edit / new version calls `audit()`; user mistakes are thrown as `UserError` (shown to the user), anything else is logged and shown as "Something went wrong".
