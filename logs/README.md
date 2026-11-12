# logs/

One file per day per channel: `app-DATE.log` (sign-ins, warnings, local mail), `error-DATE.log` (errors), `audit-DATE.log` (copy of every audited action; never deleted). `backend/cron/clean_logs.php` removes `app-` and `error-` files older than 90 days. Passwords and reset tokens are never written here. Contents are git-ignored.
