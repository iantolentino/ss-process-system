# PROGRESS — read this first when resuming

> Single source of truth for "where am I". Update after every meaningful step.
> Newest entry on top of the Log. Task detail lives in `TODO.md`.

## Project
Merge 3 legacy tools into ONE system for Strata Staff, deployed on cPanel:
1. **Process Library** — per-department processes (flowchart, people, positions, steps) for new-hire handoff
2. **Policy Portal** — policies, memos, manuals, forms (own page, versioned, fully logged)
3. **Library of Policies, Memos, Manuals, Forms** — folded into the Policy Portal as the document library

Rules from the owner: reuse the original users' flow/ideas first, improve, redesign later. Folders: `frontend/ backend/ database/ authentication/ files/ logs/` (+ more if needed). GSAP = mild animation only. Logs matter (policies change every months/year; every edit/upload/new version is dated).

## Current phase
**Feature-complete v1 + hardening done (2026-11-09).** Built: users/roles, policy portal, process library (flowchart, versions, "what changed"), handbook, onboarding, glossary, org chart, change requests + email notices, audit viewer, backups + restore drill, security review, paging/indexes, local demo seed.
**Waiting on:** Sir Bones (updated legacy roles/mapping + data, see `docs/DATA_NEEDED.md`); owner (domain, sender email, brand Q13/Q14); cPanel access for the first real deploy (set `APP_URL`!).
**Next when unblocked:** import real users (update mapping in `database/import_legacy_users.php`) -> first deploy per `docs/DEPLOY.md` -> verify mail/cron/mysqldump on the host. Optional: DOCX preview, acknowledgements UI, Microsoft 365 SSO.
Tests: `bash tests/X_smoke.sh http://localhost/process admin@example.test NewPass456` for auth portal process audit handbook features notices scale security, plus `bash tests/restore_drill.sh`.

## Decisions made
- Flowcharts: form steps → auto-generated chart. Documents: uploaded files, versioned, all versions kept.
- Ponytail = github.com/DietrichGebert/ponytail (lazy-senior-dev: minimal code). Rules recorded in `CLAUDE.md`.
- Stack: **PHP 8 + MySQL** (confirmed). cPanel "Git Version Control" can pull from GitHub to deploy.

## Status
| Item | State |
|---|---|
| Legacy files reviewed | done → moved to `_legacy/` |
| AI Nexus `_brain/` installed | done |
| Folder scaffold | done |
| `git init` + remote `origin` | done, first push below |
| GitHub repo is PUBLIC | owner chose: code only (no _legacy, db, uploads, logs, .env) |
| Stack | PHP 8 + MySQL confirmed |

## Legacy inventory (what exists today)
| File | What it is | Becomes |
|---|---|---|
| `PL Project/app.py` | Flask + SQLite; `employee_info` table, login, user CRUD, admin-only | `backend/` + `authentication/` (rewritten) |
| `PL Project/employees.db` | SQLite with real password hashes | **never commit**; migrate users then delete |
| `Strata Staff Process Library Sign In.html` | login + reset-password UI | `frontend/pages/login` |
| `Strata Staff-Process-Library v1/v2.html` | process library; 11 departments; create/edit/delete | `frontend/pages/process-library` (v2 = newer, base on it) |
| `process-library-ticketing.html` | change-request system for processes | process change-request workflow |
| `SS_Pol_Portal.html` (PL & PP copies differ) | policy storage: upload, mammoth (docx→html), user mgmt, SharePoint links | `frontend/pages/policy-portal` |
| `policy-library.html` | policy browse: categories (HR, Finance, IT, Ops, Compliance), ~25 policies, search | policy-portal public browse view |
| `no-copy.html` | copy-protection demo | optional "protected view" feature |
| `Git-Bash.JPG`, `__pycache__` | noise | drop |

Departments found in v2: Accounting & Finance, Building & Facilities, Client Success, Executive Services, HR – People & Culture, Information Technology, Legal & Business Compliance, Marketing & WebDev, Onboarding & Implementation, Process & Operations, Sales & Development, Training & Development.

## Known issues in legacy code (fix during rebuild)
- Admin "auth" = `X-Employee-Email` header → trivially spoofable. Needs real sessions/tokens.
- CORS `*` and `debug=True` in production.
- Everything is a single 75–115 KB HTML file with inline CSS/JS → split into frontend/css, js, pages.
- Two diverging copies of `SS_Pol_Portal.html`; need to diff and keep the newest.
- No audit log, no version history, no login-attempt limiting, no password-reset backend.

## Log
- 2026-11-09 — Installed `_brain/`, archived legacy to `_legacy/`, scaffolded folders, `.gitignore`, `git init`. Next: owner confirms PHP/MySQL + repo visibility → Phase 2 schema + migration runner.
- 2026-11-09 — Reviewed all legacy files, cloned ai-nexus, wrote `TODO.md` + this file. Waiting for answers to open questions (see TODO.md §Open questions).
- 2026-11-09 — Stack = PHP 8 + MySQL (owner OK), repo stays public, code only. Added `backend/bootstrap.php` (env, db, daily log files, `audit()`), `database/migrations/001_init.sql` (13 tables), `migrate.php`, `seed.php`. Verified locally: migration idempotent, 12 departments, audit row + `logs/audit-*.log` written. Local DB `ss_process` has a TEST admin (admin@example.test) — not for production. Next: Phase 3 auth (sessions, CSRF, lockout, login page from legacy sign-in).
- 2026-11-09 — Owner answered questions (roles as proposed; review-due dates yes, acknowledgements no for now; login required everywhere; email reset). Built Phase 3: `authentication/auth.php`, front controller `index.php` + `.htaccess` (blocks backend/, logs/, .env etc.), pages login/logout/forgot/reset/dashboard, `frontend/css/base.css` (tokens from legacy), GSAP 3.12.5 mild fade-in. Found+fixed CSRF bug (empty session token passed). `tests/auth_smoke.sh` 9/9 pass. Local test admin password is now changed (reset test) — local only.
  Still open: Q5 retention, Q11 migrate old users, Q13 domain/subfolder, Q14 brand. TODO: admin user-management page (Phase 3 leftover), SMTP check on the real host.
- 2026-11-09 — Built admin Users page (`backend/services/users.php`, `/users`) and Policy Portal v1 (`backend/services/documents.php`; `/policies`, `/document`, `/download`). Shared layout `components/app_open|app_close.php`, helpers `h()`, `flash()`, `UserError` in bootstrap. Files stored in `files/uploads/<docId>/<random>.<ext>`, never overwritten. `tests/portal_smoke.sh` 15/15 pass; `tests/auth_smoke.sh` 9/9. Not done in Portal: memo fields (from/audience/expiry), version text-compare, DOCX->HTML preview (mammoth), no-copy viewer, import of the ~25 legacy policies. Set php.ini upload_max_filesize/post_max_size >= 20M on cPanel (MultiPHP INI Editor).
- 2026-11-09 — Built Process Library: `backend/services/processes.php`, pages `process-library` (departments, search, positions), `process` (flowchart, step table, people involved, version history, change requests, mark reviewed, print), `process-edit` (form + JS step editor `frontend/js/process-edit.js`). Steps live in `process_versions.snapshot` JSON. Imported 46 legacy processes (279 steps) from local-only `database/seeds/legacy_processes.json` (git-ignored; re-runnable importer committed). Legacy steps have NO person/position — each department's process_owner must fill these in (handoff quality depends on it). `tests/process_smoke.sh` 22/22; all 3 suites green. Local DB contains smoke-test users/rows.
  Next: Phase 6 audit viewer page (admin: filter by user/date/entity, CSV), log retention, then DEPLOY.md, templates for new departments, memo fields, glossary/new-hire "Start here".
- 2026-11-09 — Built `/audit` (admin only; nav link "Audit log"): filters, 50/page, "field: old → new" summary + full before/after JSON, CSV export (max 50k rows, audited, spreadsheet-formula-safe). `tests/audit_smoke.sh` 10/10; all 4 suites green (auth 9, portal 15, process 22, audit 10).
  Remaining logging TODO: debug switch docs, log-file retention/cleanup cron, per-process activity timeline link, "who viewed/downloaded" (downloads only in logs/app-*.log, not audit_log).
- 2026-11-09 — Wrote `docs/DEPLOY.md` (cPanel). Added `backend/cron/backup.php` (mysqldump + PharData tar of uploads, password via MYSQL_PWD, exit 1 on failure — tested incl. wrong password) and `backend/cron/clean_logs.php` (app/error logs >90d; audit files + audit_log never deleted). Hardened `.htaccess` (dotfiles, .sh/.yml/.json etc. now 403; .well-known stays reachable). `.env.example` now quotes values (special-char passwords parse correctly — tested). All 4 suites green.
  Retention defaults I chose (CONFIRM, TODO Q5): backups 30 days, app/error logs 90 days, audit forever. NOT verified on a real cPanel host: PHP path, mysqldump availability, mail(), Git deploy. `.cpanel.yml` is in the doc, not committed (needs real server path).
- 2026-11-09 — Retention defaults confirmed by owner (backups 30d, app/error logs 90d, audit forever). Built: memo fields (`database/migrations/002_memo_fields.sql`, service + forms), process↔document links (`snapshot.docs`, `related_documents()`), `/handbook` (printable department packet; defaults to the user's department; dashboard "New here? Start here" card). `tests/handbook_smoke.sh` 19/19; all 5 suites green (auth 9, portal 15, process 22, audit 10, handbook 19). ON DEPLOY run `php database/migrate.php` (002 is new).
  Not done: per-role onboarding checklist, glossary, duplicate-process-from-template, visual org chart, DOCX preview, version compare, acknowledgements UI, SSO, migrating users from employees.db (TODO Q11), brand/domain (Q13/Q14).
- 2026-11-09 — Built (migration 003): users.position_id (set on Users page; must be in the user's department), `/onboarding` (department checklist, items for everyone or one position, week/ongoing, links to a process + a document, tick-off progress, manager team progress + manage items; position removal blocked while items use it), `/glossary` (company-wide or per-department terms; editors = admin/policy_owner/process_owner; audited; shown in handbook), process "Use as template" (copy from any active process into a department as a DRAFT, people names cleared). `tests/features_smoke.sh` 29/29; all 6 suites green (auth 9, portal 15, process 22, audit 10, handbook 19, features 29 = 104). ON DEPLOY run `php database/migrate.php` (003 is new). Smoke tests leave test rows in the local DB; checklist tests are rerunnable.
  Not done: visual org chart, DOCX preview, version compare, acknowledgements UI, SSO, email notices (change requests, review-due), migrate users from employees.db (Q11), domain/brand (Q13/Q14), real-host deploy check.
- 2026-11-09 — Added process "What changed" (version vs previous; `diff_snapshots`, steps matched by title) and email notices: `notify()` (mail(); logs locally), change request → dept process owners, resolution → requester, weekly `backend/cron/review_notices.php` for documents due ≤14 days. New `.env` keys APP_URL, MAIL_FROM. Tests: `tests/notices_smoke.sh` (9) — all 7 suites pass. Deploy: set APP_URL and add the weekly cron (docs/DEPLOY.md). Not done: visual org chart, DOCX preview, acknowledgements UI, SSO, Q11/Q13/Q14, real-host check.
- 2026-11-09 — Added org chart (nested reporting lines from positions) on department page and handbook: `frontend/components/orgchart.php`. 2 checks added to features_smoke.sh; all suites pass. Not done: DOCX preview, acknowledgements UI, SSO, Q11/Q13/Q14, real-host check.
- 2026-11-09 — Downloads now go to `audit_log` (action `download`, version id) and show in the document activity timeline. Added `database/import_legacy_users.php` (Q11: name/email/access only, no passwords; admin→admin, director→policy_owner, manager→process_owner; users use Forgot password; tested on the 3 legacy users then removed from the local DB) and a real `.cpanel.yml` (edit USER/path first). All 7 suites pass. Not done: DOCX preview, acknowledgements UI, SSO, Q13/Q14, real-host check.
- 2026-11-09 — Owner asked Sir Bones for updated legacy roles/mappings and data. Wrote `docs/DATA_NEEDED.md` (the list to send). WAITING: role mapping in import_legacy_users.php is unconfirmed; update it when the answer arrives.
- 2026-11-09 — Scaling pass: paging (50/page) on policies + users (with search), migration 004 indexes, docs/SCALING.md, tests/scale_smoke.sh (8 suites pass). Added local-only `database/seed_demo.php` (fictional users demo.*@example.test / DemoPass123, positions, 5 documents, onboarding, glossary, 3 change-request tickets on legacy processes). Run `php database/migrate.php` on deploy (004).
- 2026-11-09 — Security review done: see docs/SECURITY.md. Fixed reset-link Host poisoning (APP_URL now REQUIRED in production), reset throttle, token cleanup, per-IP login limit, 8h idle timeout + strict sessions, security headers/CSP, mail header injection. tests/security_smoke.sh (29). All 9 suites pass; CSP checked in browser (GSAP + fonts load).
- 2026-11-10 — Closed TODO gaps: document version compare (dates/size/change note; line diff for .txt/.csv, `diff_lines()` in documents service, CSS `pre.diff`), user/admin/new-hire guide `docs/GUIDE.md`; Forms category and debug switch were already covered (TODO ticked). portal_smoke +2 checks. Tests that read today's log now use PHP's date: local PHP timezone is Europe/Berlin, so around midnight log file dates differ from the shell date. ON DEPLOY set the PHP timezone in cPanel MultiPHP INI Editor (date.timezone) so logs, audit rows and MySQL agree. All 9 suites pass.
- 2026-11-09 — Restore drill passed: tests/restore_drill.sh backs up, restores into a scratch DB, 16 tables identical (CHECKSUM TABLE), 59/59 uploaded files match recorded sha256; scratch DB dropped. Gap not covered: mail(), cron and mysqldump on the real host.
