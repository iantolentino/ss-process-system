# QUICK-REF — TOKEN-EFFICIENCY LAYER

> The single biggest lever for keeping AI sessions cheap: give the AI a condensed answer
> instead of letting it re-derive one by scanning source files.

---

## How It Works

- `commands.md` — every command run in this project (dev, test, build, migrate, deploy) in one table.
- `snippets.md` — canonical code patterns specific to this project (e.g. "how we write an API route
  here", "how we structure a test here") so the AI matches existing style instead of inventing a new one.

## Rule
- During EXECUTION_MODE, check `quick-ref/` before grepping the codebase for "how do we normally do X."
- Update `commands.md` and `snippets.md` whenever a new recurring pattern or command is introduced —
  this is a running cache, not a one-time doc.
