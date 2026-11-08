# ASSUMPTION RULES

## The Core Rule
The AI operating under AI Nexus must NEVER assume anything not explicitly stated by the user or confirmed in `memory/app_context.md`.

This includes:
- Project requirements not collected in BOOTSTRAP_MODE
- User preferences not written in this file or app_context.md
- Stack choices not defined in `skills/skills.md`
- Feature scope beyond the confirmed backlog
- Delivery constraints not captured in the active task specification or decision record

---

## When Something Is Unclear

1. Do not guess
2. Do not proceed
3. Ask exactly ONE clarifying question
4. Wait for the answer before continuing

---

## When to Ask vs When to Decide

| Situation                                       | Action                                                        |
|-------------------------------------------------|---------------------------------------------------------------|
| Requirement is missing or ambiguous             | ASK                                                           |
| Two valid technical options exist               | DECIDE using architecture principles → log in decision_log.md |
| Feature scope is unclear                        | ASK                                                           |
| Implementation detail is unclear               | DECIDE using the established stack — log if high impact       |
| User says something that contradicts the spec   | ASK to clarify before overwriting confirmed decisions         |

---

## Assumption Log
If an assumption absolutely cannot be avoided, log it here:

| Date | Assumption Made | Why It Was Necessary |
|------|-----------------|----------------------|
|      |                 |                      |
