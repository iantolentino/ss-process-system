# AI Nexus System Summary

AI Nexus is a provider-neutral context-management framework for AI-assisted development.

| Capability | Source | Purpose |
| --- | --- | --- |
| Universal controller | `AI_BRAIN.md` | Defines minimal-context behavior for every supported AI tool |
| Knowledge map | `BRAIN_INDEX.md` | Maps a question to its authoritative source |
| Current continuity | `CURRENT_STATE.md`, `sessions/LATEST_HANDOFF.md` | Resumes work without reading history |
| Context selector | `tools/select-context.ps1` | Selects and audits the minimum task context |
| Durable knowledge | `architecture/`, `decisions/` | Stores lasting facts and ADRs with evidence |
| Daily history | `daily/`, `archive/` | Preserves detail without making it active context |
| Provider adapters | Root `AGENTS.md`, `CLAUDE.md`, Cursor/Windsurf/Copilot rules | Direct each tool to the same controller |
| Maintenance | Brain Doctor and Context Metrics | Detects stale/broken active knowledge and measures size |

Legacy project folders remain on-demand supporting knowledge. They are never default context for provider-neutral AI Nexus sessions.
