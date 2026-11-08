# Governance Rules

## Authority

1. `AI_BRAIN.md` — universal context and session policy
2. `CURRENT_STATE.md` — current project facts
3. Relevant ADRs, architecture records, contracts, and task specifications
4. `BRAIN_INDEX.md` — maps questions to authoritative sources
5. Legacy files — supporting or historical knowledge only

## Universal constraints

- Do not scan the repository or full brain by default.
- Expand context only after identifying the exact missing knowledge.
- Preserve confirmed decisions; record significant changes with evidence.
- For a bug fix, check `fixes/fix_log.md` only when the bug-fix context selection indicates it is relevant.
- Update active state and handoff at meaningful milestones.
