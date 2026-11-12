# database/

- `migrations/` numbered SQL files (`001_init.sql` ...). `php database/migrate.php` applies the new ones once and records them; safe to re-run.
- `seed.php` departments and the first admin. `seed_templates.php` six starter process templates. `seed_samples.php` one sample per part. `seed_demo.php` fictional demo users and data (local only).
- `import_legacy_processes.php` / `import_legacy_users.php` copy data from the old tools (the JSON / `employees.db` stay local and are git-ignored).
- `preflight.php` checks a server before go-live. `test_mail.php` sends one test email.
- `backups/` output of `backend/cron/backup.php` (git-ignored).

Never edit an applied migration; add a new numbered file instead.
