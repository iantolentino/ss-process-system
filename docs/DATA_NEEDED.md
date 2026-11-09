# Data needed before go-live

Send this to Sir Bones. Until it arrives, the system runs on the old data (46 imported processes, 3 legacy users) and the role mapping in `database/import_legacy_users.php` is only a guess.

## 1. Users and roles (most important)
One row per person, ideally a spreadsheet (CSV/XLSX):

| Column | Why |
|---|---|
| First name, middle name, last name | Shown in history and audit log |
| Work email | Login + password reset + notices (must be a real inbox) |
| Department | One of the 12 departments |
| Position / job title | Onboarding checklist and "who's who" |
| Reports to (position) | Draws the org chart |
| **Role in the system** | See below |

Roles the system understands (please confirm who gets which):
- **admin** — manages users, sees the audit log, edits everything.
- **policy_owner** — adds and edits policies, memos, manuals, forms.
- **process_owner** — edits the processes of *their own department* and its checklist.
- **employee** — read-only, can suggest changes and tick their own checklist.

Questions: the old app had `admin`, `director`, `manager`. Currently mapped to admin / policy_owner / process_owner. **Is that right?** Who are the process owners per department (one or more)? How many admins (at least 2 recommended)?

## 2. Policies, memos, manuals and forms
For each document: the **file** (PDF/DOC/DOCX/XLS/XLSX/PPT/PPTX/TXT/CSV, max 20 MB), title, type (policy / memo / manual / form), category, owner, **effective date**, **next review date**, and the version history if older versions exist (each old file + its date). For memos also: from, audience, expiry date.

## 3. Process Library, per department
The 46 legacy processes have steps but **no people or positions**. For each department the process owner must provide:
- Positions and who holds them, and who reports to whom.
- For every process: owner position, what starts it, how often, how long, and for each step **who does it** (position and name), tools used and the result.
- Which steps are yes/no decisions, and where "No" goes.
- Tips for new hires (common mistakes, who to ask).
- Which policies/forms each process uses.

## 4. Onboarding checklist, per department
Week-by-week tasks for a new hire (week 0 = ongoing), whether each is for everyone or one position, and the process/policy it links to.

## 5. Glossary
Acronyms and terms new hires hear: term, meaning, company-wide or one department.

## 6. Decisions
- **Domain / subfolder** where it will live (e.g. process.company.com), and a cPanel login or someone to deploy.
- **Email**: a sender address (e.g. no-reply@company.com) so notices and password resets are delivered.
- **Brand**: keep the old look for launch, or provide logo/colors?
- **Sign-in**: email + password now; Microsoft 365 sign-in later?
- **Review cycle**: default months between policy reviews (suggest 12).
- **Backups**: who downloads the weekly backup copy off the server.
