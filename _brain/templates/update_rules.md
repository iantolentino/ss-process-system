# Update Rules

When an installer updates an existing `_brain/`, it refreshes framework files but preserves project knowledge.

## Safe to overwrite

- `AI_BRAIN.md`, `BRAIN_INDEX.md`, `BRAIN_CHANGELOG.md`, `CONTINUE_PROMPT.md`
- `prompts/*`, `governance/*`, `interaction/*`, `intents/*`, `overview/*`, `templates/*`
- `tools/brain-doctor.ps1`, `tools/context-metrics.ps1`, `tools/select-context.ps1`, `tools/context-diff.ps1`, `tools/handoff-baseline.ps1`, `tools/handoff-compact.ps1`, `tools/session-checkpoint.ps1`, `tools/slice-file.ps1`, `tools/dependency-boundary.ps1`, `tools/error-fingerprint.ps1`, `tools/record-session-cost.ps1`, `tools/skill-router.ps1`, `tools/workflow-gate.ps1`, `tools/context-decay.ps1`, `tools/knowledge-audit.ps1`, `tools/token-estimate.ps1`, `tools/nexus.ps1`, `tools/module-graph.ps1`
- `daily/TEMPLATE.md`, `sessions/CHECKPOINT.md`, `decisions/ADR-TEMPLATE.md`
- Framework documentation and provider entry-point templates

## Never overwrite

- `CURRENT_STATE.md`, `CONTEXT_DIFF.md`, `daily/YYYY-MM-DD.md`
- `sessions/LATEST_HANDOFF.md`, `sessions/manifests/*`, `sessions/archive/*`
- `architecture/*`, project ADRs, project-specific `memory/*`
- `fixes/fix_log.md` and fix detail files
- `security/*`, `deployment/*`, `releases/*`, `skills/*`, `db_backup/*`
- Project-specific quick references, tool inventory, and improvement log

## Update process

1. Copy only safe framework paths.
2. Create new framework folders only when absent.
3. Never delete or overwrite project knowledge during an update.
4. Record a material framework update in the project's release notes when applicable.
