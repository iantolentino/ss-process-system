# Deploying to cPanel

Stack: PHP 8 + MySQL, no build step. Recommended home: a **subdomain** (e.g. `process.yourcompany.com`) whose document root is the app folder. A subfolder (`yourcompany.com/process/`) also works because all links are relative to where `index.php` lives.

## 0. Requirements (check in cPanel)

| Need | Where | Notes |
|---|---|---|
| PHP 8.0+ | *MultiPHP Manager* | Extensions: `pdo_mysql`, `mbstring`, `json` (on by default) |
| MySQL 5.7+ or MariaDB 10.2+ | *MySQL Databases* | The `JSON` column type and `JSON_LENGTH()` are required |
| Terminal or SSH | *Terminal* | Needed for migrations and cron setup. No terminal? See "No terminal" at the end |
| `mod_rewrite` / `.htaccess` | default on | The app's routing and file protection depend on it |
| Email sending | *Email Deliverability* | Password-reset emails use PHP `mail()`. Make SPF/DKIM show "Valid" |

## 1. Create the database

1. *MySQL® Databases* → create a database, e.g. `cpuser_process`.
2. Create a database user with a **long generated password** and save it.
3. Add the user to the database with **ALL PRIVILEGES** (migrations need CREATE).
   Optional hardening after step 5: edit the user's privileges to drop `DROP`, and on `audit_log` nothing can be restricted per-table in cPanel, so keep the user private to this app.

## 2. Get the code onto the server

**Option A — cPanel Git Version Control (recommended; updates become one click)**

1. *Git™ Version Control* → *Create* → clone URL `https://github.com/iantolentino/ss-process-system.git` → repository path e.g. `/home/USER/repos/ss-process`.
2. Add a `.cpanel.yml` to the repo root (commit it) so *Deploy HEAD Commit* copies the code without touching your data:

   ```yaml
   ---
   deployment:
     tasks:
       - export DEPLOYPATH=/home/USER/process.yourcompany.com/
       - /bin/rsync -a --exclude='.git' --exclude='.env' --exclude='files/uploads' --exclude='logs' --exclude='database/backups' ./ $DEPLOYPATH
       - /usr/local/bin/php $DEPLOYPATH/database/migrate.php
   ```

   Replace `USER` and the folder. The PHP path differs per host (`/usr/local/bin/php`, or `/opt/cpanel/ea-php82/root/usr/bin/php`); run `which php` in Terminal to find it.
3. *Manage* → *Deploy HEAD Commit*.

**Option B — zip upload:** download the repo as a ZIP from GitHub, upload it with *File Manager* into the document root, extract. For updates, upload over the old files but **never** overwrite `.env`, `files/uploads/`, `logs/` or `database/backups/`.

## 3. Configure `.env`

In the deployed folder, copy `.env.example` to `.env` and fill it in (File Manager → Edit):

```ini
APP_ENV=production
APP_DEBUG=0
DB_HOST=localhost
DB_NAME="cpuser_process"
DB_USER="cpuser_app"
DB_PASS="the-long-password"
```

- Put values containing symbols in **double quotes**.
- `APP_ENV=production` makes password-reset links go out by email. (`local` writes them to a log file instead.)
- `APP_DEBUG=0` hides error details from visitors. Errors are still written to `logs/error-DATE.log`.
- Set the file permission of `.env` to **600**.

## 4. PHP settings

*MultiPHP INI Editor* (or `.user.ini`) for this domain:

```ini
upload_max_filesize = 25M
post_max_size = 30M
```

The app accepts files up to 20 MB, so these must be larger. Select PHP **8.0 or newer** for the domain in *MultiPHP Manager*.

## 5. Create the tables and the first admin

In *Terminal*, from the deployed folder:

```bash
php database/migrate.php                      # creates all tables (safe to re-run)
php database/seed.php admin@yourcompany.com 'A-long-password-here'   # 12 departments + first admin
```

The seed only creates an admin when no users exist. Sign in, then add real users under **Users** and set their roles:

| Role | Can do |
|---|---|
| `admin` | everything, plus Users and Audit log |
| `policy_owner` | add/edit/archive policies, memos, manuals, forms |
| `process_owner` | edit processes of **their own department** (set the department on the user!) |
| `employee` | read, download, suggest process changes |

## 6. Folder permissions

These must be writable by PHP (usually 755 is enough on cPanel; 750 if your PHP runs as your user):
`files/uploads/`, `logs/`, `database/backups/`.

Never make them world-writable (777).

## 7. HTTPS

*SSL/TLS Status* → run *AutoSSL* for the domain. Then force HTTPS by uncommenting the block near the top of `.htaccess`:

```apache
# RewriteCond %{HTTPS} off
# RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

Session cookies are marked `Secure` automatically when the site is served over HTTPS.

## 8. Cron jobs (backups and log cleanup)

*Cron Jobs* → add (use your PHP path and folder):

| When | Command |
|---|---|
| `0 2 * * *` | `/usr/local/bin/php /home/USER/process.yourcompany.com/backend/cron/backup.php > /dev/null` |
| `30 2 * * *` | `/usr/local/bin/php /home/USER/process.yourcompany.com/backend/cron/clean_logs.php > /dev/null` |
| `0 8 * * 1` | `/usr/local/bin/php /home/USER/process.yourcompany.com/backend/cron/review_notices.php > /dev/null` |

- Normal output is discarded; **a failed backup prints to stderr and exits non-zero, so cPanel emails it to the cron email address.** Set that address under *Cron Jobs → Cron Email*.
- `backup.php` writes `database/backups/db-DATE.sql.gz` (full database, including the audit log) and `files-DATE.tar.gz` (all uploaded documents). Keeps 30 days (`BACKUP_KEEP_DAYS`).
- **These backups live on the same server.** Also download them regularly, or rely on cPanel/host backups, so a server loss does not take the copies with it.
- `clean_logs.php` removes `app-*` and `error-*` log files older than 90 days (`LOG_KEEP_DAYS`). It **never** deletes `audit-*` files or anything in the `audit_log` table.
- `review_notices.php` (weekly) emails admins and policy owners the documents overdue or due within 14 days. Emails (also change-request notices and password resets) use PHP `mail()`; set `APP_URL` (links) and optionally `MAIL_FROM` in `.env`. Locally they are written to `logs/app-*.log` instead.
- If `mysqldump` is not found, set `MYSQLDUMP=/usr/bin/mysqldump` (or the path from `which mysqldump`) in `.env`.

Run each once by hand to confirm: `php backend/cron/backup.php` should print `backup ok`.

## 9. Import the original processes (once)

The 46 processes from the original Process Library are kept out of GitHub because the repo is public. To load them:

1. Upload `database/seeds/legacy_processes.json` (from your PC) to the server's `database/seeds/`.
2. `php database/import_legacy_processes.php` → `imported 46`.
3. **Delete the JSON file from the server.** Re-running the import is safe (it skips existing ones).

Afterwards each department's process owner should fill in **who (position/person)** for each step; the original data had none.

## 10. Verify the deployment

From any computer (replace the domain):

```bash
curl -sI https://process.yourcompany.com/login        | head -1   # 200
curl -sI https://process.yourcompany.com/.env          | head -1   # 403
curl -sI https://process.yourcompany.com/backend/bootstrap.php | head -1   # 403
curl -sI https://process.yourcompany.com/database/backups/    | head -1   # 403
curl -sI https://process.yourcompany.com/files/uploads/       | head -1   # 403
curl -sI https://process.yourcompany.com/dashboard    | head -1   # 302 → /login
```

Then by hand:
- [ ] Sign in as admin; create a `policy_owner`, a `process_owner` (with department) and an `employee`.
- [ ] As policy owner: upload a policy, then upload a new version. Check the history shows both with dates, and Audit log shows both events.
- [ ] As employee: can read and download, cannot see edit forms.
- [ ] As process owner: create a process with a yes/no step; the flowchart draws; edit it and see version 2.
- [ ] Forgot password: the email arrives (check spam; fix SPF/DKIM if not).
- [ ] Upload a ~15 MB file (confirms the PHP limits).
- [ ] Run the backup command and confirm files appear in `database/backups/`.

## Updating later

1. Push to GitHub → cPanel *Git Version Control* → *Update from Remote* → *Deploy HEAD Commit* (migrations run automatically with the `.cpanel.yml` above). With Option B, upload changed files and run `php database/migrate.php`.
2. Take a manual backup first: `php backend/cron/backup.php`.

**Rollback:** redeploy the previous commit, and if a migration changed the database, restore the DB backup (below).

## Restore from a backup

```bash
gunzip -c database/backups/db-2026-11-09.sql.gz | mysql -u cpuser_app -p cpuser_process
tar -xzf database/backups/files-2026-11-09.tar.gz -C files/uploads/
```

## When something breaks

| Symptom | Look at |
|---|---|
| "Something went wrong." | `logs/error-DATE.log` (latest lines). Temporarily set `APP_DEBUG=1` to see the error on screen; set back to `0` after |
| Cannot sign in | `logs/app-DATE.log` shows `login failed` / `login locked` (5 failures = 15 min lock) |
| Who changed this policy/process? | **Audit log** page, or `logs/audit-DATE.log` |
| Reset email not arriving | cPanel *Track Delivery*; SPF/DKIM under *Email Deliverability* |
| 500 on every page | PHP version below 8.0, or `.env` missing/unreadable (check permissions) |
| 404 on `/login` but `index.php` works | `.htaccess` not applied: ask the host to enable `AllowOverride All` |
| Upload fails | PHP limits (section 4) and writable `files/uploads/` |

## Security notes

- Never commit `.env`, database files, uploads or logs (already in `.gitignore`).
- The GitHub repo is **public**: it must never contain internal policy text, user data or the legacy files.
- Delete test/smoke accounts. Use `tests/*_smoke.sh` only on a local copy; they create test rows.
- Disable accounts instead of deleting them, so history stays attached to a real person.

## No terminal on this host?

Ask the host to enable Terminal/SSH, or run the migration from your PC against the remote database (cPanel *Remote MySQL* → allow your IP, set `DB_HOST` to the server name in a **local** `.env`, run `php database/migrate.php` and `seed.php`, then restore your local `.env`). Cron jobs can still be set up in the *Cron Jobs* screen.
