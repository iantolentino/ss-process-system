# TODO — Strata Staff Process & Policy System

Legend: `[ ]` todo · `[~]` in progress · `[x]` done · `(?)` needs owner answer

## Open questions (owner)
1. [x] Stack: PHP 8 + MySQL
2. [x] Ponytail = DietrichGebert/ponytail
3. [x] Roles confirmed: admin, policy_owner, process_owner, employee
4. [x] Uploaded files, versioned
5. (?) Should old versions stay downloadable forever? Retention for logs (e.g. keep 7 years)?
6. [x] YES review-due dates + dashboard reminder
7. [x] NO acknowledgement UI for now (table kept)
8. [x] Form steps -> auto chart
9. [x] Login required for everything
10. [x] Email/password + reset by SMTP email; SSO later
11. (?) Real employee data: migrate users from `employees.db`? (Never push it to GitHub.)
12. [x] Public repo, code only
13. (?) Domain / cPanel subfolder path for deployment (affects base URL/routes).
14. (?) Brand: keep legacy fonts/colours for now, redesign later — confirm.

## Phase 0 — Setup
- [x] Decide stack: PHP 8 + MySQL; ponytail = DietrichGebert/ponytail
- [x] Run AI Nexus installer into project
- [x] git init, .gitignore, remote set (code only, repo public)
- [ ] Diff the two `SS_Pol_Portal.html` copies; keep newest
- [x] Legacy moved to `_legacy/` (git-ignored)

## Phase 1 — Scaffold (folder layout)
```
process/
├─ frontend/        pages, css, js (GSAP mild), components, assets
├─ backend/         routes/controllers, services, middleware, config
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
- [ ] Create folders + README per folder (purpose, how to debug)
- [ ] Config via `.env` (never committed) + `.env.example`
- [ ] Router: `/login`, `/dashboard`, `/policies`, `/process-library`, `/admin`

## Phase 2 — Database
- [ ] `users` (name, middle, last, email, hash, role, active, last_login)
- [ ] `departments`
- [ ] `positions` (title, dept, reports_to)
- [ ] `document_types` (policy, memo, manual, form) · `categories`
- [ ] `documents` (id, type, title, category, owner, status, review_due, current_version_id)
- [ ] `document_versions` (version no., file path, hash, changelog, effective_date, uploaded_by, uploaded_at) — **never overwritten**
- [ ] `processes` (dept, name, purpose, owner position, status, current_version_id)
- [ ] `process_versions`, `process_steps` (order, title, description, position/role, tools, inputs/outputs, SLA, decision branches)
- [ ] `process_change_requests` (from ticketing page)
- [ ] `acknowledgements` (user, doc version, date)
- [ ] `audit_log` (who, what, entity, before/after JSON, IP, timestamp) — append-only
- [ ] `login_attempts`, `password_resets`
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
- [ ] Compare versions (changelog; text diff for DOCX→HTML if feasible)
- [x] Review-due dates + dashboard "Reviews due" panel
- [ ] Acknowledgement tracking (if Q7 yes)
- [x] Memos: from, audience, expiry (migration 002; expired badge)
- [ ] Forms: downloadable + category
- [ ] Optional protected/no-copy viewer (legacy `no-copy.html`)
- [x] SharePoint link field

## Phase 5 — Process Library (own page + flow)
- [x] Department grid (12) → department page → process list → process detail
- [x] Easy add process: guided form, no code (duplicate-from-template NOT done)
- [x] Add/edit steps: order (move up/down), who (position + name), what, tools, output, yes/no decision with "No → go to step N"
- [~] Auto flowchart (HTML/CSS, printable; "No" branch shown as label not arrow; PNG/PDF export = browser print)
- [~] Positions per department ("Who's who") + "People involved" per process; visual org chart NOT done
- [~] New-hire view: department handbook `/handbook` (who's who, all processes, flowcharts, printable) + dashboard "Start here" card; per-role checklist and glossary NOT done
- [x] Link processes ↔ policies/forms/manuals (pick on process form; shown on process page + handbook; archived ones hidden)
- [x] Version history for processes (every save = new version, old versions viewable)
- [x] Change request workflow (open → approved/rejected/done; no email notification yet)
- [x] "Last reviewed" date + Mark reviewed (per-department owner = process_owner role)
- [x] Migrated 46 legacy processes (279 steps) as v1 via database/import_legacy_processes.php

## Phase 6 — Logging & audit (priority)
- [x] Central logger: log_line() daily files app/audit/error
- [x] audit_log writes on login/logout/reset, users, documents, processes, positions, change requests, exports
- [x] Admin Audit page: filter by user/record type/action/record #/date, paging, field-level changes, CSV export (export itself audited, formula-safe)
- [ ] File logs: `logs/app.log`, `error.log`, `access.log`; daily rotation; never log passwords/tokens
- [x] Timeline on each document (Activity); processes show version history
- [ ] Debug mode switch via `.env` (off in production)

## Phase 7 — Frontend polish
- [ ] Split legacy inline CSS/JS into shared files, design tokens (colours/fonts) for later redesign
- [ ] GSAP mild: page fade-in, card stagger, panel slide — respect `prefers-reduced-motion`
- [ ] Responsive, keyboard accessible, print styles
- [ ] Empty/error/loading states

## Phase 8 — Quality & security
- [~] Upload validation done (ext whitelist, 20MB, random stored name, served only via PHP download); MIME sniffing not done
- [ ] Input validation + output escaping (XSS), prepared statements (SQLi), CSRF
- [ ] Security headers, HTTPS redirect, no CORS `*`
- [ ] Tests: auth, permissions, versioning, audit
- [ ] Accessibility pass

## Phase 9 — Deployment (cPanel)
- [x] `docs/DEPLOY.md`: DB, code (git or zip), .env, PHP limits, migrate/seed, cron, HTTPS, verify checklist, restore, troubleshooting
- [x] `.htaccess`: routing + deny internals, dotfiles (keeps .well-known), config/source file types; HTTPS redirect block ready to uncomment
- [ ] Staging check on subdomain before go-live (owner)
- [x] Rollback + restore steps documented (restore drill on the real host still TODO)

## Phase 10 — Handover
- [ ] Admin guide, editor guide, **new-hire guide**
- [ ] Seed realistic sample data
- [ ] Push to GitHub `iantolentino/ss-process-system` (code only — no db, no uploads, no `.env`)

## Recommendations (extra, not requested)
- Document owners + review reminders so policies don't silently go stale
- "Effective date" separate from "uploaded date" (policies often take effect later)
- Read-receipts/acknowledgements for compliance
- Global search across policies AND processes
- Glossary/acronyms page for new hires
- Per-process "last verified by" stamp
- Export a department's processes as a single PDF onboarding packet
- Weekly automatic DB + files backup, and a restore drill
