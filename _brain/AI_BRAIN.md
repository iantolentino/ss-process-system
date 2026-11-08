# AI Nexus

AI Nexus is a context-management framework for AI-assisted development. Its goal is controlled context with reliable continuity: load the smallest useful knowledge set, then expand only when a task proves it necessary.

Keep AI Nexus retrieval-focused. Do not add giant permanent prompts, autonomous agent layers, or background context loading. Tools must propose or validate a minimal packet, not cause broad repository reads.

This file is the single universal controller for AI Nexus. Provider-specific files such as `AGENTS.md` and `CLAUDE.md` are only small adapters that direct their AI tool here.

## Authority

- `AI_BRAIN.md` defines the operating policy.
- `BRAIN_INDEX.md` maps questions and intents to authoritative knowledge.
- `CURRENT_STATE.md` is the compact view of the project now.
- `sessions/LATEST_HANDOFF.md` is the continuation snapshot.
- `daily/YYYY-MM-DD.md` records activity for one day.
- `decisions/` and `architecture/` preserve durable knowledge.

Legacy files remain available for existing projects. They are historical/supporting knowledge unless `BRAIN_INDEX.md` explicitly marks one as active.

## Default context

Start every session with `BRAIN_INDEX.md`, `CURRENT_STATE.md`, and `sessions/LATEST_HANDOFF.md`. Read today's daily log when continuing active work. Do not scan the repository or load historical brain files by default.

Determine the intent, use its profile in `intents/`, and load only the named supporting sources. Expand one level at a time and state the exact missing knowledge before doing so.

When a task names a module, load only its profile in `modules/` plus direct dependencies. For a large source file, use `tools/slice-file.ps1` with a bounded line range instead of reading the whole file. `tools/dependency-boundary.ps1` lists direct local dependency candidates without loading them. Use `tools/error-fingerprint.ps1` for a recurring error before searching broad history.

Route specialized instructions with `tools/skill-router.ps1`. Use `tools/workflow-gate.ps1` for DISCOVER → PLAN → IMPLEMENT → TEST → REVIEW → COMPLETE; it blocks a completion claim unless checks, review, and state/handoff update are recorded.

Use `tools/select-context.ps1` to generate an auditable context plan and session manifest when PowerShell is available. Its 30,000-character default budget is a guard, not a hard limit: if it reports OVER BUDGET, narrow the task files or load them incrementally before reading the whole packet.

## Session continuity

At a meaningful milestone, update today's daily log and `CURRENT_STATE.md` if project state changed. Keep the handoff compact; if it has become long, run `tools/handoff-compact.ps1` and review its draft before replacing any handoff content. Before pausing, write a compact, actionable `sessions/LATEST_HANDOFF.md`, then run `tools/handoff-baseline.ps1 -UpdateHandoff`. At continuation, run `tools/context-diff.ps1` when a valid baseline exists, before selecting task context. Archive superseded handoffs in `sessions/archive/`.

About every hour, or before a broad context expansion, run `tools/session-checkpoint.ps1`. If it reports YELLOW or RED, offer a concise choice to continue, compact context, generate a handoff, or start a fresh session. It is a reminder, never a forced rotation.

Run `tools/brain-doctor.ps1` periodically to detect stale, oversized, contradictory, or broken active knowledge.
