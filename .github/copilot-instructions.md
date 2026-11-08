# AI Nexus context policy

Read `_brain/AI_BRAIN.md`, the single AI Nexus controller. When shell execution is available, run `_brain/tools/context-diff.ps1` if a valid handoff baseline exists. Determine the task intent, run `_brain/tools/select-context.ps1`, then read only its selected files. Do not scan the repository or read full project history by default.
If the selector reports OVER BUDGET, narrow source files or load them incrementally.

Before stopping, update current state and the daily log if needed, write a compact latest handoff, then run `_brain/tools/handoff-baseline.ps1 -UpdateHandoff`.
For a long handoff, use `_brain/tools/handoff-compact.ps1` to create a reviewable draft; never overwrite it blindly.

About every hour, or before broad context expansion, run `_brain/tools/session-checkpoint.ps1`. On YELLOW or RED, offer: continue, compact context, generate a handoff, or start fresh; do not force a reset.

> Installer note: this file belongs at `.github/copilot-instructions.md` in the target project.
