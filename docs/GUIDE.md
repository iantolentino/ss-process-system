# User guide

Sign in with your work email. Forgot your password? Use **Forgot password** on the sign-in page; the link lasts 1 hour. You are signed out after 8 hours without activity.

## Everyone (including new hires)

- **Home**: big buttons for what you most likely want to do (learn a job, find a policy, your checklist), plus documents due for review. The left menu is on every page; on a phone use the Menu button. **How to use this site** in the menu has a short guide for your role.
- **Handbook** ("Start here" on the dashboard): a printable packet for your department: who's who, the org chart, every process, linked policies and forms, and the glossary. Use your browser's Print to save it as PDF.
- **Onboarding**: your department's checklist by week. Items are for everyone or for one position. Tick items off as you finish them.
- **Policy Portal**: Policies, Memos, Manuals, Forms. Search, filter by category, open a document, download any version. Each document shows its current version, effective date and next review date.
- **Processes**: pick a department, then a process. Tabs show the flowchart, the steps (who does what, with which tool, what comes out), the people involved, and the version history. **What changed** shows the differences from the previous version.
- **Found a mistake in a process?** Open the process, go to the **Requests** tab and use **Suggest a change to this process**. The department's process owner is emailed and you get the answer by email.
- **Glossary**: company and department terms.

## Document editors (admin, policy owner)

- **Add a document**: Policies & forms → *Add a document*. Version, effective date and a short "what changed" are required.
- **New version**: open the document → *Upload a new version*. The old version is kept and stays downloadable. Nothing is overwritten.
- **Compare**: on the document page, *Compare with previous* shows the dates, size and your change note. For `.txt` and `.csv` files it also shows the lines added and removed. For Word, Excel and PDF files, download both versions.
- **Archive** instead of delete (Edit details → Status). Restore by setting it back to active.
- **Review date**: set the next review date; the dashboard and a weekly email list what is overdue or due within 14 days.
- Allowed files: pdf, doc, docx, xls, xlsx, ppt, pptx, txt, csv, up to 20 MB.

## Process owners (per department)

- **Add or edit a process**: Processes → department → *New process*. Fill in the steps in order: who (position and name), what they do, tools, the output, and an optional yes/no decision with "No → go to step N". The flowchart is drawn from the steps.
- Every save creates a new version; old versions stay viewable.
- **Positions** (who's who) and who they report to build the org chart. Fill these in: new hires rely on them.
- **Confirm still correct** when you have checked a process is still correct.
- **Use as template** copies any process into your department as a draft (people names cleared).
- **Onboarding items**: add the checklist for your department, linked to the process or document the new hire should read.
- Answer **change requests** (approve, reject, done).

## Admins

- **Users**: add, edit or disable people; roles are `admin`, `policy_owner`, `process_owner`, `employee`. The last admin cannot be disabled or demoted. Disable instead of delete: history keeps the name.
- **Audit log**: every login, edit, upload, new version, download and export, with who, when, and before/after values. Filter by user, record, action or date; *Export CSV* (the export is logged too). Audit entries are never deleted.
- **Backups and restore**: see `DEPLOY.md`. Test a restore on a copy now and then (`tests/restore_drill.sh` locally).
- **Troubleshooting**: "Something went wrong" means check `logs/error-DATE.log`. To see the error on screen temporarily, set `APP_DEBUG=1` in `.env` and set it back to `0` afterwards. Never leave it on in production.
