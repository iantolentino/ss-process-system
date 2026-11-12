# Session Handoff

## Objective
Strata Process System (PHP 8 + MySQL, cPanel): merged Process Library + Policy Portal (+ document library). Repo: github.com/iantolentino/ss-process-system (public, code only). Owner works ALONE.

## Completed (to 2026-11-12)
- Feature-complete v1 locally: roles, policy portal (versions, compare), process library with cadence (daily..yearly), templates, Overview tab, Company overview page (/overview), org chart, handbook, onboarding, glossary, change requests + email, audit viewer, backups + restore drill, security review, accessibility pass, shadcn-style UI with sidebar, in-app help.
- Go-live prep: docs/GO_LIVE.md (fill-in sheet), SMTP support (smtp_send, test_mail.php, preflight.php), seed_templates.php (6 starters), seed_samples.php (1 sample per part).
- 10 smoke suites + restore drill pass (security 29/29). Everything is pushed except the local-only items below.
- Release plan written (Claude Doc + deliverables/Merge_Plan_and_Release_Schedule.docx + Teams_message.html; deliverables/ is git-ignored). Dates: MVP demo Fri 2026-12-11, go-live Tue 2027-01-12, final announcement Fri 2027-01-22; owner away 2026-12-19..2027-01-03.

- 2026-11-12 structure review vs paid/open-source tools: structure is fine; ranked gap list is in the newest PROGRESS.md log entry (approval step, acknowledgements, process review dates, reassignable document owner, PDF view). Owner approved all but process review dates.
- 2026-11-12 built (migration 006, NOT committed): approval before publishing (`/approvals`, admins only publish), read-and-confirm, admin can change document owner, PDF view in browser, one search (`/search`), `.github/workflows/php-lint.yml`, empty backend folders removed. New `tests/approval_smoke.sh` (37). All 11 suites + restore drill pass. Details in the newest PROGRESS.md entry.
- Process review dates/reminders: owner confirmed NOT needed; the admin approving department uploads is the check.

## Current State
- Read PROGRESS.md first, then TODO.md.
- Local env: XAMPP. `.env` DB_NAME now = `ss_process_demo` (clean demo data: 46 legacy processes, templates, samples, demo accounts; admin@example.test / DemoPass123; demo.*@example.test / DemoPass123). Original test DB `ss_process` still has all smoke-test rows; original env saved as `.env.before_demo`. To run the smoke tests set DB_NAME=ss_process (tests also hard-code `ss_process` for mysql calls and use admin NewPass456).
- XAMPP MySQL was repaired (Aria logs); full data backup at C:\xampp\mysql\data_backup_20261112 (2.4 GB, delete when sure).
- Owner said (2026-11-12): not showing the system yet, wants rest time; nothing is pending from them right now.

## Decisions
- Roles admin/policy_owner/process_owner/employee; login required; email+password+reset; documents are versioned uploads; retention backups 30d, logs 90d, audit forever.
- No new data from Sir Bones: only the two legacy folders already given; add 1 sample per part as a template guide; real data entered after go-live.
- Schedule with break and final date 22 Jan 2027 accepted by owner.

## Blockers (external)
- cPanel access, domain, SMTP mailbox (owner). Sir Bones: confirm the four roles; review MVP.

## Next
When owner is ready: show MVP to Sir Bones (use demo DB), then test with real staff + phone check, then deploy per docs/GO_LIVE.md and verify mail/cron/mysqldump on the host.

## Git Baseline

- Base commit: 8033d8ff2946b238c9aec4357f9ef3b7b5877d73
- Branch: main
- Working tree at handoff: has uncommitted changes
- Verification: [tests/checks run, or not yet run]
