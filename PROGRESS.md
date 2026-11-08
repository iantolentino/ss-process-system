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
**Phase 2 done (schema + migrations + seed + logging/audit helpers). Next: Phase 3 authentication**

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
