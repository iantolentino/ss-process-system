# TODO — Strata Staff Process & Policy System

Legend: `[ ]` todo · `[~]` in progress · `[x]` done · `(?)` needs owner answer

## Open questions (owner)
1. (?) **Stack for cPanel**: PHP 8 + MySQL (works on any cPanel, no special setup) vs keep Python/Flask (needs "Setup Python App"/Passenger; not on every host) vs Node.
2. (?) **"Ponytail skills"** — what is it? (a skill/plugin, a coding style, a library?) No skill by that name exists in this environment.
3. (?) Who can do what? Proposed roles: `admin`, `policy_owner` (edits policies), `process_owner` (edits processes of own dept), `employee` (read only). OK?
4. (?) Do policies get **authored in the system** (rich text) or **uploaded files** (PDF/DOCX) with a version number? Legacy converts DOCX→HTML via mammoth, and links SharePoint. Keep both?
5. (?) Should old versions stay downloadable forever? Retention for logs (e.g. keep 7 years)?
6. (?) Policy review cycle: want "review due" dates + email/dashboard reminders (policy updates yearly/monthly)?
7. (?) Acknowledgement: must employees click "I have read this" on new policy versions?
8. (?) Flowcharts: draw in-app (drag boxes) or write steps and auto-generate the chart? Proposal: steps entered as a form → auto-rendered flowchart (Mermaid) — simplest for non-technical users.
9. (?) Can the site be public/guest-viewable, or login-required for everything? (Legacy: login-required.)
10. (?) Email available for password reset (SMTP on cPanel)? Is SSO (Microsoft 365, since SharePoint is used) wanted later?
11. (?) Real employee data: migrate users from `employees.db`? (Never push it to GitHub.)
12. (?) Is the GitHub repo public or private? (Legacy HTML contains internal policy text.)
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
- [ ] Scheduled DB backup script (cron on cPanel) → `database/backups/`

## Phase 3 — Authentication
- [ ] Login page (reuse legacy design/flow)
- [ ] Secure sessions (httpOnly, SameSite, CSRF token); replace email-header trick
- [ ] Password hashing (argon2/bcrypt), min length, lockout after N failures
- [ ] Forgot/reset password (token, expiry)
- [ ] Role middleware on every route
- [ ] Admin: user management (add/edit/disable, not hard-delete)
- [ ] Log: login success/fail, logout, role changes

## Phase 4 — Policy Portal (own page)
- [ ] Landing with 4 tabs: **Policies · Memos · Manuals · Forms**
- [ ] Browse: search, category filter, status (active/superseded/draft), sort by updated
- [ ] Document detail: current version, metadata, **version history table** (version, date, who, what changed), download any version
- [ ] Upload new document / **upload new version** (required: version no., effective date, change summary)
- [ ] Edit metadata (logged with before/after)
- [ ] Archive instead of delete (soft delete, restorable, logged)
- [ ] Compare versions (changelog; text diff for DOCX→HTML if feasible)
- [ ] Review-due dates + dashboard widget "due in 30 days"
- [ ] Acknowledgement tracking (if Q7 yes)
- [ ] Memos: date, from, audience, expiry
- [ ] Forms: downloadable + category
- [ ] Optional protected/no-copy viewer (legacy `no-copy.html`)
- [ ] SharePoint link field (legacy feature)

## Phase 5 — Process Library (own page + flow)
- [ ] Department grid (12 from legacy) → department page → process list → process detail
- [ ] **Easy add process**: guided form (name, purpose, owner, trigger, steps…), duplicate-from-template, no code needed
- [ ] **Add/edit steps**: order, who (name + position), what they do, tools/systems, inputs, outputs, time, decision (yes/no branch)
- [ ] **Auto flowchart** from steps (Mermaid or similar), printable/exportable PNG/PDF
- [ ] People & positions: org chart per department, "who to ask" contact
- [ ] New-hire view: "Start here" checklist per role, glossary, links to related policies/forms
- [ ] Link processes ↔ policies/forms (cross-reference)
- [ ] Version history for processes (same as policies)
- [ ] Change request workflow (from `process-library-ticketing.html`): submit → review → approve → published
- [ ] Per-department process owner + "last reviewed" date
- [ ] Migrate content from legacy v2 (existing processes)

## Phase 6 — Logging & audit (priority)
- [ ] Central logger in backend (levels, request id)
- [ ] `audit_log` writes on every create/edit/upload/version/archive/login/role change
- [ ] Admin Audit page: filter by user/date/entity, export CSV
- [ ] File logs: `logs/app.log`, `error.log`, `access.log`; daily rotation; never log passwords/tokens
- [ ] Timeline widget on each document/process ("Mar 3 2027 – v3 uploaded by J. Cruz")
- [ ] Debug mode switch via `.env` (off in production)

## Phase 7 — Frontend polish
- [ ] Split legacy inline CSS/JS into shared files, design tokens (colours/fonts) for later redesign
- [ ] GSAP mild: page fade-in, card stagger, panel slide — respect `prefers-reduced-motion`
- [ ] Responsive, keyboard accessible, print styles
- [ ] Empty/error/loading states

## Phase 8 — Quality & security
- [ ] Upload validation (type, size, MIME, rename, outside web root or `.htaccess` deny)
- [ ] Input validation + output escaping (XSS), prepared statements (SQLi), CSRF
- [ ] Security headers, HTTPS redirect, no CORS `*`
- [ ] Tests: auth, permissions, versioning, audit
- [ ] Accessibility pass

## Phase 9 — Deployment (cPanel)
- [ ] `docs/DEPLOY.md`: create DB/user in cPanel, upload, `.env`, run migrations, cron backups
- [ ] `.htaccess` (routing, deny `logs/ database/ authentication/ .env`)
- [ ] Staging check on subdomain before go-live
- [ ] Rollback plan + backup/restore test

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
