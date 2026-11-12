# TODO — Strata Staff Process & Policy System

Legend: `[-]` deferred on purpose · `[ ]` todo · `[~]` in progress · `[x]` done · `(?)` needs owner answer

## Open questions (owner)
1. [x] Stack: PHP 8 + MySQL
2. [x] Ponytail = DietrichGebert/ponytail
3. [x] Roles confirmed: admin, policy_owner, process_owner, employee
4. [x] Uploaded files, versioned
5. [x] Old versions stay downloadable forever; retention: backups 30d, app/error logs 90d, audit forever (confirmed)
6. [x] YES review-due dates + dashboard reminder
7. [x] NO acknowledgement UI for now (table kept)
8. [x] Form steps -> auto chart
9. [x] Login required for everything
10. [x] Email/password + reset by SMTP email; SSO later
11. [x] No new data is coming: users are added in the app (People & roles); `database/import_legacy_users.php` is optional. Employee data is never pushed to GitHub
12. [x] Public repo, code only
13. [x] Domain: owner will use a different domain than stratastaff.com (set `APP_URL`; nothing is hard-coded)
14. [x] Brand: redesigned to a shadcn-style look (tokens at the top of `frontend/css/base.css`)

## Phase 0 — Setup
- [x] Decide stack: PHP 8 + MySQL; ponytail = DietrichGebert/ponytail
- [x] Run AI Nexus installer into project
- [x] git init, .gitignore, remote set (code only, repo public)
- [x] Diff the two `SS_Pol_Portal.html` copies: the `PL Project` one is newer (see docs/LEGACY_NOTES.md)
- [x] Legacy moved to `_legacy/` (git-ignored)

## Phase 1 — Scaffold (folder layout)
```
process/
├─ frontend/        pages, css, js (GSAP mild), components, assets
├─ backend/         bootstrap, services, cron
├─ database/        schema, migrations, seeds, backups/
├─ authentication/  login, sessions, roles/permissions, reset password
├─ files/           uploads/ (policies, forms, manuals), exports/
├─ logs/            app, audit, error, access (rotated)
├─ docs/            architecture, deployment (cPanel), user guide, new-hire guide
├─ tests/
├─ _legacy/         original user files
├─ _brain/          AI Nexus
├─ PROGRESS.md  TODO.md  README.md
```
- [x] Create folders + README per folder (purpose, how to debug)
- [x] Config via `.env` (never committed) + `.env.example`
- [x] Router: front controller `index.php` with a page whitelist

## Phase 2 — Database
- [x] `users` (name, middle, last, email, hash, role, active, last_login, department, position)
- [x] `departments`
- [x] `positions` (title, dept, reports_to)
- [x] Document types (policy, memo, manual, form) are fixed in code; categories are free text on the document
- [x] `documents` (type, title, category, owner, status, review_due, current_version_id, memo fields)
- [x] `document_versions` (version no., file path, hash, changelog, effective_date, uploaded_by): never overwritten
- [x] `processes` (dept, name, purpose, owner position, cadence, kind, status, current_version_id)
- [x] `process_versions` hold the whole process (steps, who, tools, decisions) as JSON, one row per version
- [x] `change_requests` (from the ticketing page)
- [x] `acknowledgements` table kept; no screen for it (owner said no for now)
- [x] `audit_log` (who, what, entity, before/after JSON, IP, timestamp), append-only
- [x] `login_attempts`, `password_resets`
- [x] Migration runner + seed (`database/migrate.php`, `seed.php`)
- [x] Backup script `backend/cron/backup.php` (DB + uploads, 30-day prune, non-zero exit on failure); log cleanup `backend/cron/clean_logs.php`

## Phase 3 — Authentication
- [x] Login page (legacy design reused)
- [x] Secure sessions (httpOnly, SameSite, CSRF)
- [x] Password hashing + lockout (5 fails / 15 min)
- [x] Forgot/reset password (1h token, single use; local dev logs the link, production uses mail())
- [x] Role helpers: require_login(), require_role() (apply per route as pages are built)
- [x] Admin: user management (add/edit/disable; last-admin + self-demotion guards)
- [x] Log: login, logout, reset (audit_log + logs/)

## Phase 4 — Policy Portal (own page)
- [x] Landing with 4 tabs: Policies · Memos · Manuals · Forms
- [x] Browse: search, category filter, status, shows version + effective + review due
- [x] Document detail: version history table + download any version + activity timeline
- [x] Upload new document / new version (version no., effective date, change summary required)
- [x] Edit metadata (logged with before/after)
- [x] Archive instead of delete (restore = set active)
- [x] Compare versions (dates, size, change note; line diff for .txt/.csv; other types: download both)
- [x] Review-due dates + dashboard "Reviews due" panel
- [x] Read and confirm (acknowledgements): per-document switch, button for staff, "who has confirmed" for editors, list on Home (2026-11-12)
- [x] Approval before publishing: non-admin uploads and active process saves wait for an admin (`/approvals`, migration 006)
- [x] Document owner changeable by admins; PDF view in the browser; one search box (`/search`)
- [x] Memos: from, audience, expiry (migration 002; expired badge)
- [x] Forms: downloadable + category (same as other types)
- [-] Optional protected/no-copy viewer: deferred (not needed for launch)
- [x] SharePoint link field

## Phase 5 — Process Library (own page + flow)
- [x] Department grid (12) → department page → process list → process detail
- [x] Easy add process: guided form, no code (duplicate-from-template NOT done)
- [x] Add/edit steps: order (move up/down), who (position + name), what, tools, output, yes/no decision with "No → go to step N"
- [x] Auto flowchart (HTML/CSS, printable; "No" branch shown as a label; PNG/PDF = browser print)
- [x] Positions per department ("Who's who") + "People involved" per process + hierarchy chart
- [x] New-hire view: `/handbook` (printable packet incl. glossary) + `/onboarding` per-department/per-position checklist with progress + manager team view + dashboard cards
- [x] Link processes ↔ policies/forms/manuals (pick on process form; shown on process page + handbook; archived ones hidden)
- [x] Version history for processes (every save = new version, old versions viewable)
- [x] Change request workflow (open → approved/rejected/done; no email notification yet)
- [x] "Last reviewed" date + Mark reviewed (per-department owner = process_owner role)
- [x] Migrated 46 legacy processes (279 steps) as v1 via database/import_legacy_processes.php

## Phase 6 — Logging & audit (priority)
- [x] Central logger: log_line() daily files app/audit/error
- [x] audit_log writes on login/logout/reset, users, documents, processes, positions, change requests, exports
- [x] Admin Audit page: filter by user/record type/action/record #/date, paging, field-level changes, CSV export (export itself audited, formula-safe)
- [x] File logs: daily `app-`, `error-`, `audit-` files, never passwords or tokens (web access log is the host's own)
- [x] Timeline on each document (Activity); processes show version history
- [x] Debug mode switch via `.env` APP_DEBUG (documented in DEPLOY.md)

## Phase 7 — Frontend polish
- [x] Shared CSS/JS with design tokens; restyled to a shadcn/ui look (plain CSS, zinc theme) with a left sidebar layout (2026-11-11)
- [x] GSAP mild: page fade-in, card stagger, panel slide — respect `prefers-reduced-motion`
- [x] Keyboard accessible (skip link, focus outline, current page marked), print styles; responsive layout exists but is not re-checked on a phone
- [x] Empty and error states (friendly 400/403/404 pages, "nothing here yet" messages)

## Phase 8 — Quality & security
- [x] Upload validation (extension allow-list, 20 MB, content-type check, random stored name, served only through the PHP download page)
- [x] Input validation + output escaping (XSS), prepared statements (SQLi), CSRF
- [x] Security headers, HTTPS redirect block ready in `.htaccess`, no CORS `*`
- [x] Tests: auth, permissions, versioning, audit, approval (11 smoke suites + restore drill); GitHub checks PHP syntax on every push (`.github/workflows/php-lint.yml`)
- [-] Process review dates and reminders: not needed. Owner confirmed (2026-11-12) the check is the admin approving what a department uploads, which the approval step already does
- [x] Accessibility pass (axe-core: 0 violations on 10 signed-in pages; not tested with a real screen reader or the public sign-in pages)

## Phase 9 — Deployment (cPanel)
- [x] `docs/DEPLOY.md`: DB, code (git or zip), .env, PHP limits, migrate/seed, cron, HTTPS, verify checklist, restore, troubleshooting
- [x] `.htaccess`: routing + deny internals, dotfiles (keeps .well-known), config/source file types; HTTPS redirect block ready to uncomment
- [~] Staging check on subdomain before go-live (owner)
- [x] Rollback + restore steps documented (restore drill on the real host still TODO)

## Phase 10 — Handover
- [x] Admin guide, editor guide, new-hire guide (docs/GUIDE.md)
- [x] Seed realistic sample data (`seed_demo.php`, `seed_templates.php`, `seed_samples.php`)
- [x] Push to GitHub `iantolentino/ss-process-system` (code only: no db, no uploads, no `.env`)

## Recommendations (extra, not requested)
- Document owners + review reminders so policies don't silently go stale
- "Effective date" separate from "uploaded date" (policies often take effect later)
- Read-receipts/acknowledgements for compliance
- Global search across policies AND processes
- Glossary/acronyms page for new hires
- Per-process "last verified by" stamp
- Export a department's processes as a single PDF onboarding packet
- Weekly automatic DB + files backup, and a restore drill
