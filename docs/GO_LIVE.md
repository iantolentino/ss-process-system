# Go-live sheet: fill in, then run

Everything is prepared. You only edit the values below and run the commands in order. Detail and troubleshooting are in [DEPLOY.md](DEPLOY.md). Nothing here is tied to a specific domain.

## 1. Values to fill in

| Value | Example | Goes in |
|---|---|---|
| Domain or subdomain | `https://process.yourdomain.com` | `APP_URL` in `.env` |
| cPanel username | `cpuser` | `.cpanel.yml` (`USER`), database names |
| Folder the site is served from | `/home/cpuser/process.yourdomain.com/` | `.cpanel.yml` (`DEPLOYPATH`) |
| Database name, user, password | `cpuser_process`, `cpuser_app`, long password | `DB_*` in `.env` |
| PHP path | `/usr/local/bin/php` (check with `which php`) | `.cpanel.yml`, cron lines |
| Sender address | `Strata Process <no-reply@yourdomain.com>` | `MAIL_FROM` in `.env` |
| SMTP host, port, security, user, password | `mail.yourdomain.com`, `587`, `tls` | `SMTP_*` in `.env` |
| First admin email and password | | `seed.php` command below |

## 2. Steps

1. **cPanel → MySQL Databases:** create the database and user (all privileges). Note the password.
2. **cPanel → Domains:** point the domain or subdomain at the folder above. Turn on **AutoSSL**.
3. **Edit `.cpanel.yml`** in the repo: replace `USER` and the folder, then commit and push. (On GitHub, or run `git push`.)
4. **cPanel → Git Version Control:** *Create* from `https://github.com/iantolentino/ss-process-system.git`, then *Deploy HEAD Commit*.
5. **File Manager → copy `.env.example` to `.env`** in the deployed folder and fill in the values from the table. Set `APP_ENV=production`, `APP_DEBUG=0`. Permission **600**.
6. **cPanel → MultiPHP Manager / INI Editor:** PHP 8.0+, `upload_max_filesize = 25M`, `post_max_size = 30M`, `date.timezone` set to yours.
7. **Terminal** in the deployed folder:
   ```bash
   php database/migrate.php
   php database/seed.php admin@yourdomain.com 'a-long-password'
   php database/preflight.php          # fix everything it marks FAIL
   php database/test_mail.php you@yourdomain.com   # confirm email arrives (check spam)
   php backend/cron/backup.php         # should print: backup ok
   ```
8. **cPanel → Cron Jobs:** add the three lines in [DEPLOY.md §8](DEPLOY.md) (backup daily, log cleanup daily, review notices weekly).
9. **Uncomment the HTTPS redirect** near the top of `.htaccess`, deploy again, then run the `curl` checks in [DEPLOY.md §10](DEPLOY.md) (the `.env` and `backend/` URLs must be 403).
10. **Sign in** and walk through the checklist at the end of DEPLOY.md §10 (create one user of each role, upload a policy, create a process, try Forgot password).

## 3. After it is live: add the real data

Do this last, once the site is confirmed working:

1. Create the real users (**People & roles**), or import them: `php database/import_legacy_users.php path/to/employees.db`. Update the role mapping in that file first, with what Sir Bones confirms.
2. Load the 46 legacy processes if you want them: [DEPLOY.md §9](DEPLOY.md).
3. Add the real policies, memos, manuals and forms (**Policies & forms → Add a document**).
4. Each department's process owner fills in who does each step, the positions and the onboarding checklist.
5. Delete or disable any test accounts. Never run `tests/*_smoke.sh` or `seed_demo.php` on the live site.
6. Do a restore drill on the server once real data is in ([DEPLOY.md](DEPLOY.md), "Restore from a backup").
