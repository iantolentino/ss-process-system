# Provider entry-point templates

The installers copy one of these small files to a project root only when the destination does not already exist.

| Template | Intended tool | Destination |
| --- | --- | --- |
| `AGENTS.md` | Codex | `AGENTS.md` |
| `CLAUDE.md` | Claude Code | `CLAUDE.md` |
| `.cursorrules` | Cursor | `.cursorrules` |
| `.windsurfrules` | Windsurf | `.windsurfrules` |
| `copilot-instructions.md` | GitHub Copilot | `.github/copilot-instructions.md` |

Each template points to `_brain/AI_BRAIN.md`, the one universal controller. It then identifies intent, runs AI Nexus context selection, and reads only selected files.

If a project already has an instruction file, the installer leaves it untouched. Merge the AI Nexus context policy into that file manually.
