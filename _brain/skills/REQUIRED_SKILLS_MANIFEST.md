# Optional Capability Integrations

AI Nexus works without external skills, plugins, or provider-specific memory products. The core workflow is plain Markdown plus the context selector, so it remains portable across AI tools.

Use an integration only when it is already available and relevant to the task. Never make a provider-specific integration mandatory for every session.

| Optional capability | Examples | When useful |
| --- | --- | --- |
| Output compression | Ponytail or equivalent | A provider supports reusable instruction skills |
| Coding quality checks | Review/lint/test skills | Implementing or reviewing code |
| External memory | Claude-mem or equivalent | A tool supports its own memory layer; AI Nexus files remain authoritative |
| Output-quality guidance | Tasteskill or equivalent | The team has adopted it deliberately |

## Core rules that do not require integrations

- Use `AI_BRAIN.md` as the controller.
- Select minimal context for every task.
- Verify work before claiming completion.
- Record durable decisions and compact handoffs.
- Do not load third-party skills or large prompts unless they are needed.

Provider-specific configuration belongs in that provider's own instruction file or settings, not in the universal AI Nexus brain.
