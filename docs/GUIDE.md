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
- **Search everything**: the box at the top of the left menu looks in policies, memos, manuals, forms, processes (including their steps) and the glossary. It does not look inside uploaded files.
- **Read a PDF without downloading**: on a document page, *View* next to a PDF opens it in the browser.
- **Please read and confirm**: when a document asks for it, it is listed on Home. Open it, read the current version, then press *I have read and understood this*. A new version asks again.

## Document editors (admin, policy owner)

- **Add a document**: Policies & forms → *Add a document*. Version, effective date and a short "what changed" are required.
- **New version**: open the document → *Upload a new version*. The old version is kept and stays downloadable. Nothing is overwritten.
- **Compare**: on the document page, *Compare with previous* shows the dates, size and your change note. For `.txt` and `.csv` files it also shows the lines added and removed. For Word, Excel and PDF files, download both versions.
- **Archive** instead of delete (Edit details → Status). Restore by setting it back to active.
- **Review date**: set the next review date; the dashboard and a weekly email list what is overdue or due within 14 days.
- **Approval**: what a policy owner uploads (a new document or a new version) waits until an admin approves it. Staff keep seeing the current version until then. If it is rejected you get the reason by email; upload a corrected file as a new version. Only one version of a document can wait at a time. An admin's own upload is published at once.
- **Read and confirm**: tick *Ask everyone to confirm they have read this document* (Add a document → More options, or Edit details). The document page then shows how many people have confirmed the current version and who has not yet.
- Allowed files: pdf, doc, docx, xls, xlsx, ppt, pptx, txt, csv, up to 20 MB.

## Process owners (per department)

- **Add a process**: Processes → department → *New process*. Start from a **template** (Daily, Weekly, Monthly, Quarterly, Yearly or As-needed layouts are ready) and change what does not fit, or start blank. Say **how often** it happens: the department page groups processes under Daily, Weekly, Monthly and so on, which is the view executives read.
- **Fill in the details**: for each step say who does it, what they do, tools, result and how long. A yes/no decision can say "No → go to step N". The *Details that help readers* section (what is needed first, how we know it went well, what can go wrong, who to ask when stuck) fills the **Overview** tab, the first thing a reader sees.
- **Edit** with *Edit this process*; every save is a new version.
- **Remove** hides a process or template from staff. Nothing is deleted: it stays with all versions under *Removed* on the department page, and *Restore* brings it back.
- **Make your own template**: save a process as *A reusable template*. It shows in the template gallery for every department and is not shown to staff as a live process.
- Every save creates a new version; old versions stay viewable.
- **Approval**: when a process owner saves a process as *active*, the change waits until an admin approves it; staff keep seeing the approved version. Saving as *draft* needs no approval (staff do not see drafts). Only one change per process can wait at a time. *Restore* by a process owner brings a process back as a draft.
- **Positions** (who's who) and who they report to build the org chart. Fill these in: new hires rely on them.
- **Confirm still correct** when you have checked a process is still correct.
- **Use as template** copies any process into your department as a draft (people names cleared).
- **Onboarding items**: add the checklist for your department, linked to the process or document the new hire should read.
- Answer **change requests** (approve, reject, done).

## Admins

- **Approvals** (menu, with the number waiting; also shown on Home): everything policy owners and process owners sent. Open the file or *see what would change*, then **Approve** (staff see it at once) or **Reject** with a reason (the person is emailed). Each decision is in the activity history. Admins are emailed when something new is waiting.
- **Change a document's owner**: open the document → *Edit details* → *Owner*. Use it when the owner leaves or changes role. Only admins can do this.
- **People & roles**: add, edit or turn off people; roles are `admin`, `policy_owner`, `process_owner`, `employee`. The last admin cannot be disabled or demoted. Disable instead of delete: history keeps the name.
- **Activity history**: every login, edit, upload, new version, download and export, with who, when, and before/after values. Filter by user, record, action or date; *Export CSV* (the export is logged too). Audit entries are never deleted.
- **Backups and restore**: see `DEPLOY.md`. Test a restore on a copy now and then (`tests/restore_drill.sh` locally).
- **Troubleshooting**: "Something went wrong" means check `logs/error-DATE.log`. To see the error on screen temporarily, set `APP_DEBUG=1` in `.env` and set it back to `0` afterwards. Never leave it on in production.
