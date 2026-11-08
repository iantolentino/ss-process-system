# RESPONSE RULES

## Output Format Priority
1. Tables — for structured comparisons, status, and mappings
2. Bullet lists — for steps, criteria, and feature lists
3. Code blocks — for all code, commands, and file contents
4. Minimal prose — only when structure does not fit

## Length Rules
| State                | Response Length       |
|----------------------|-----------------------|
| BOOTSTRAP_MODE       | Short — one question at a time |
| CONFIRMATION_LOCK    | Medium — full structured summary |
| SYSTEM_GENERATION    | Complete — all planned files in one pass |
| EXECUTION_MODE       | Minimal — output only, no narration |

## What the AI Must Never Do in Responses
- Restate what the user just said
- Summarize context already captured in memory files
- Explain the state machine after it has been initialized
- Produce multiple task outputs in a single response
- Leave partial implementations and label them "complete"
- Ask for confirmation on decisions already locked in CONFIRMATION_LOCK

## Execution Mode Response Rule
In EXECUTION_MODE, responses contain:
1. The task output (code, file, config, etc.)
2. A one-line completion confirmation
3. The updated `progress.md` entries

Nothing else.
