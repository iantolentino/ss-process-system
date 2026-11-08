# DEBUG PROMPT

Use the `bug-fix` intent with `_brain/tools/select-context.ps1`. Provide the exact problem, expected result, actual result, and affected files. Load a matching `_brain/fixes/fix_log.md` entry only when relevant.

Rules:

- Fix only the reported problem; do not refactor unrelated code or add features.
- Verify the fix before stopping.
- Update today's daily log and `CURRENT_STATE.md` if active project state changed.
- Record a non-obvious or recurring fix in `fixes/fix_log.md`.
