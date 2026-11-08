# FIXES — BUG FIX MEMORY

> This folder is permanent cross-session memory for every bug an AI has already solved in this repo.
> Its purpose: stop any AI (Claude, ChatGPT, Copilot, etc.) from re-diagnosing a bug that was already root-caused.

---

## Why This Exists

Without this folder, every new AI session re-investigates bugs from scratch — burning tokens and
sometimes landing on a worse fix than the one already found. `fixes/` makes fixes reusable.

---

## How It Works

1. `fix_log.md` is the index — one row per fix, always read first (cheap, ~1 line per entry).
2. Trivial fixes (typo, one-line config, obvious cause) are logged **inline in `fix_log.md` only** —
   no detail file. Keep it cheap.
3. Non-obvious fixes (root cause took investigation, or the fix pattern will recur) get a
   **detail file** — copy `_template.md`, name it `F###-short-slug.md`, and link it from `fix_log.md`.
4. Detail files are only read when `fix_log.md` flags one as relevant — this keeps token usage low
   (index-first, lazy-load the detail).

---

## Rule (enforced by AI Nexus bug-fix context)

- Before starting a bug fix, scan `fixes/fix_log.md` only when the bug-fix context selection or the reported symptom indicates a matching prior issue may exist.
- After completing any bug-fix task, add a row to `fix_log.md`. Add a detail file if the fix is
  non-obvious or likely to recur.
- Never delete a fix entry — mark it `SUPERSEDED` and link the new one if it's later replaced.
