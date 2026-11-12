# Current State

> Compact active state only. Do not write a project history here. Every important fact should name its source when one exists.

## Current objective

Feature-complete v1 waiting for the owner's go-live inputs (domain, SMTP mailbox, cPanel access).

## Active work

- Feature/project: Strata Process & Policy System
- Implementation state: verified locally (11 smoke suites + restore drill pass)
- Relevant files/modules: `PROGRESS.md` (read first), `TODO.md`, `docs/GO_LIVE.md`

## Important facts

- Only admins publish: non-admin uploads and active process saves wait on `/approvals`.
  Source: `backend/services/documents.php`, `backend/services/processes.php`, migration 006
- Tests need `.env` DB_NAME=ss_process and admin@example.test.
  Source: `tests/README.md`

## Blockers and bugs

- None. (Process review reminders: owner confirmed not needed; admin approval covers the check.)

## Immediate next action

Owner decides on commit/push of the 2026-11-12 work, then the MVP demo.

## Last significant decision

2026-11-12: stay with the custom build; add approval, read-and-confirm, owner change, PDF view, one search, GitHub PHP check. Source: `PROGRESS.md` log.

Last updated: 2026-11-12
