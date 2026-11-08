# Continue Session Policy

Continue the current task using only the provided active context. Do not scan or read the full repository automatically.

Start with:

1. `AI_BRAIN.md`
2. `BRAIN_INDEX.md`
3. `CURRENT_STATE.md`
4. `sessions/LATEST_HANDOFF.md`
5. Today's daily progress log, when relevant

If `LATEST_HANDOFF.md` includes a Git baseline, run `tools/context-diff.ps1` before selecting supporting context. Read the resulting `CONTEXT_DIFF.md` only when it contains a current diff.

Identify the intent and use its profile in `intents/`. Load only the minimum supporting files required to complete the task correctly. If the selector reports OVER BUDGET, narrow the source files or load them incrementally. If information is missing, identify the exact missing fact and load the mapped source at the next context level; do not load unrelated files.

At meaningful milestones, update today's daily log and `CURRENT_STATE.md` when state changes. Before stopping, write a compact handoff; if the existing handoff is long, run `tools/handoff-compact.ps1` and review its draft before replacing content. Then run `tools/handoff-baseline.ps1 -UpdateHandoff` to record its Git baseline. Never claim an exact token budget unless the provider reliably exposes it.

About every hour, or before broad context expansion, run `tools/session-checkpoint.ps1`. If it reports YELLOW or RED, offer to continue, compact context, generate a handoff, or start a fresh session; never force rotation.
