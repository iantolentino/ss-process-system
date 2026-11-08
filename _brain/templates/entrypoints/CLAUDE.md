# AI Nexus context policy

`_brain/AI_BRAIN.md` is the single AI Nexus controller. For every new task, read it. When shell execution is available, run `_brain/tools/context-diff.ps1` if the latest handoff has a valid Git baseline, then identify the task intent and run `_brain/tools/select-context.ps1`.
Read only the files in its Context Selection. Do not scan the repository, read the full brain, or load unrelated history unless the selected context identifies an exact missing fact.
If the selector reports OVER BUDGET, narrow source files or load them incrementally before reading the packet.

At meaningful milestones, update `_brain/CURRENT_STATE.md` and today's daily log. Before stopping,
write a compact `_brain/sessions/LATEST_HANDOFF.md`, then run `_brain/tools/handoff-baseline.ps1 -UpdateHandoff` to record the continuation baseline.
Use `_brain/tools/handoff-compact.ps1` to create a reviewable draft if the handoff has grown long; never overwrite it blindly.

About every hour, or before loading broad additional context, run `_brain/tools/session-checkpoint.ps1`. If it reports YELLOW or RED, briefly offer the user: continue, compact context, generate a handoff, or start a fresh session. Do not force a reset.
