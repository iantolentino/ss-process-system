# Built to last: how this grows

- **Lists are paged** (50 per page): policies, users, audit log. Search stays server-side. `tests/scale_smoke.sh` checks it with 120 rows.
- **Indexes** cover the queries that grow: documents by type/status/review date, versions by document, audit by entity/user/date, change requests by process/status, onboarding by department/week (`database/migrations/004_scaling_indexes.sql`).
- **History never overwritten**: versions and the audit log are append-only. The audit table is the one that grows forever; at ~1 million rows consider yearly archiving (export CSV from the Audit page, then move old rows to an archive table).
- **Schema changes are migrations**: add `NNN_name.sql` in `database/migrations/`; `php database/migrate.php` applies each once. Never edit an applied migration.
- **Uploads** live in `files/uploads/<doc id>/`; one folder per document keeps directories small. Backups cover them.
- **New roles / departments / document types** are one-line changes (`ROLES`, `DOC_TYPES`, the `departments` table).
- **Known limits (shortcuts)**: text search uses `LIKE` (fine to tens of thousands of rows; add FULLTEXT after that); PHP `mail()` has no queue; sessions are file-based (move to the database if you ever run two servers).
- **Demo data**: `php database/seed_demo.php` (local only) fills a dev database with fictional people, documents, onboarding, glossary and change requests on top of the imported legacy processes.
