# Session Handoff

## Objective
Strata Process System (PHP 8 + MySQL, cPanel): merged Process Library + Policy Portal. Repo: github.com/iantolentino/ss-process-system (public, code only).

## Completed (2026-11-09)
- Onboarding checklist, glossary, user positions, process templates, org chart, process "what changed", email notices (change requests, weekly review-due cron), download audit.
- Scaling: paging (policies/users), migration 004 indexes, docs/SCALING.md, local-only `database/seed_demo.php`.
- Security review (docs/SECURITY.md): reset-link Host poisoning fixed (APP_URL required in prod), reset throttle, per-IP login limit, idle timeout, CSP/headers.
- Restore drill passed (`tests/restore_drill.sh`). `database/import_legacy_users.php`, `.cpanel.yml`, `docs/DATA_NEEDED.md`.
- 9 smoke suites + restore drill pass locally. All pushed.

## Current State
Read `PROGRESS.md` first (resume point), then `TODO.md`. Local dev: XAMPP, DB ss_process, admin@example.test / NewPass456 (test only). Nothing verified on a real cPanel host.

## Decisions
- Roles admin/policy_owner/process_owner/employee; login required; email+password+reset; docs are versioned uploads; retention backups 30d, logs 90d, audit forever.
- Legacy role mapping (admin->admin, director->policy_owner, manager->process_owner) is a GUESS pending Sir Bones.

## Blockers
- Sir Bones: updated legacy roles/mapping + real data (docs/DATA_NEEDED.md). Owner: domain, sender email, brand. cPanel access for first deploy.

## Next
Import real users; first deploy (docs/DEPLOY.md, set APP_URL, run migrate incl. 004); verify mail/cron/mysqldump on host; optional DOCX preview, SSO.

## Git Baseline

- Base commit: 3090e24851997551578b19619aac4a39a373a5b3
- Branch: main
- Working tree at handoff: has uncommitted changes
- Verification: [tests/checks run, or not yet run]

