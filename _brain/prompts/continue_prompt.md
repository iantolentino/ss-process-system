# CONTINUE PROMPT

Use `_brain/CONTINUE_PROMPT.md`. Determine the intent, then run:

```powershell
pwsh -NoProfile -File _brain/tools/select-context.ps1 -Intent <feature-development|bug-fix|refactor|investigation>
```

Read only the selected files. Load a legacy progress, fix, decision, or architecture file only when the selection identifies an exact missing fact. Update today's daily log and `CURRENT_STATE.md` when active state changes; write `sessions/LATEST_HANDOFF.md` before stopping.
