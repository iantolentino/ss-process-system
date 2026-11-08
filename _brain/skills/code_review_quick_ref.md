# CODE REVIEW QUICK REFERENCE (ONE PAGE)

**Read this before every code review. Print it. Reference it.**

---

## QUICK GATE CHECKLIST

Every code change must pass ALL:

### 🔴 BUGS (Does it break?)
- [ ] No logic errors (conditions inverted? loops infinite? null unchecked?)
- [ ] Error handling complete (no try/catch swallowing errors silently)
- [ ] Resource cleanup (files closed, connections returned, memory freed?)
- [ ] Race conditions possible? (shared state accessed safely?)

### 🔐 SECURITY (Can it be hacked?)
- [ ] No user input reaches DB without sanitization (SQL injection?)
- [ ] No user input reaches HTML without escaping (XSS?)
- [ ] Auth checked on every protected endpoint?
- [ ] User can only access their own data (not other users')?
- [ ] No secrets in code, logs, or error messages?

### ⚡ PERFORMANCE (Does it scale?)
- [ ] N+1 query problem? (batch queries instead)
- [ ] Unbounded memory (cache with eviction? queue with size limit?)
- [ ] Expensive operations in loops? (move outside loop)
- [ ] Blocks event loop? (defer heavy work to background)

### 🏗️ MAINTAINABILITY (Will engineers hate this?)
- [ ] Code is readable (not clever/obfuscated)?
- [ ] Variable names are descriptive (not `x`, `temp`, `data`)?
- [ ] Function does ONE thing (not 5 things)?
- [ ] Duplication eliminated? (extract to function)
- [ ] Dead code removed? (not just commented out)

### 📈 SCALABILITY (10x users? 100x data?)
- [ ] No hard-coded limits (10, 100, 1000)?
- [ ] Algorithm efficient enough (O(n) not O(n²))?
- [ ] Stateless or externally stored state? (can scale horizontally)
- [ ] Database query indexes planned?

---

## REPORTING FRAMEWORK

**Only report if:**

| Severity | Report? | Example |
|----------|---------|---------|
| **CRITICAL** | ✅ YES | Infinite loop, SQL injection, race condition, auth bypass |
| **HIGH** | ✅ YES | Missing error handling, N+1 query, memory leak |
| **MEDIUM** | ✅ YES | Code complexity, duplication, minor perf issue |
| **LOW** | ❌ SKIP | Style, linting, nice-to-have optimization |

---

## FILE SKIP PATTERNS

**Always skip** (unless explicitly reviewing):
```
node_modules/, dist/, build/, .git/, .env.*
**/*.lock, **/*.log, coverage/
**/*.test.ts, **/*.spec.ts, __tests__/, tests/
.github/workflows/, .circleci/, jest.config.*, .eslintrc*
README.md, *.md, docs/, LICENSE
```

**Result:** 30-40% fewer tokens

---

## DECISION RULES

### ✅ Report when:
- Breaks functionality, logic error, crash
- Security vulnerability (any OWASP top 10)
- Data loss/corruption risk
- Performance degrades at scale
- Code unreadable/unmaintainable

### ❌ Skip when:
- Already logged in `fixes/fix_log.md` (same issue)
- Style/linting issue (should be auto-checked)
- Contradicts locked architectural decision
- Requires only documentation, not code

### ⏸️ Defer when:
- Not urgent
- Requires larger refactoring
- Future optimization, not current blocker

---

## QUICK MEMORY CHECKS

Before reporting, check:

1. **Is this already fixed?** → Read `_brain/fixes/fix_log.md`
2. **Is this already decided?** → Read `_brain/decisions/decision_log.md`
3. **What's the context?** → Read `_brain/memory/app_context.md`
4. **Did we reject this?** → Read `_brain/decisions/rejected_options.md`

If found → don't re-report. Move on.

---

## OUTPUT FORMAT

```
## [SEVERITY] Finding Title

**File:** path/to/file.ts:42
**Category:** [BUGS | SECURITY | PERFORMANCE | MAINTAINABILITY | SCALABILITY]

**What:** 1 sentence describing the issue

**Why it matters:** Impact (1-2 sentences)

**Fix:** Recommendation or code snippet
```

---

## DONE CHECKLIST

Before stopping:

- [ ] All non-skipped files reviewed?
- [ ] Findings organized by severity (CRITICAL first)?
- [ ] Checked memory (fix_log.md, decision_log.md)?
- [ ] Every finding has file + line + specific issue?
- [ ] No praise, only improvements?
- [ ] One sentence on overall quality?

If all ✅ → Report and stop. If not → continue review.

---

## LANGUAGE-SPECIFIC ADJUSTMENTS

**Python/JS:** Type errors = HIGH (no compile-time check)
**Go:** Unhandled errors = CRITICAL
**Rust:** If compiles, most memory/concurrency safe
**Java/.NET:** Null checks more critical

---

## COMMON ANTI-PATTERNS TO FLAG

```
❌ SELECT * FROM users WHERE id = userId  → SQL injection risk
❌ innerHTML = userInput                   → XSS risk
❌ for (const x of list) { db.query() }   → N+1 query
❌ const cache = {}                        → Unbounded memory
❌ function doEverything() { ... }         → One function does too much
❌ x = x + 1  (in hot loop)                → Performance issue
❌ // TODO: fix this later                 → Incomplete code
❌ const x;  (declared but unused)         → Dead code
```

---

## IF UNSURE

Consult:
- `governance/code_review_rules.md` — Full rules & baselines
- `memory/app_context.md` — Project domain/constraints
- `decisions/decision_log.md` — Architecture decisions
- `interaction/assumptions.md` — What not to assume

Don't guess. Ask or defer.

