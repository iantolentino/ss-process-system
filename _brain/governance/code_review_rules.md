# CODE REVIEW GOVERNANCE RULES

## Authority

Code review findings override design purity but not locked decisions.

- If finding conflicts with `decisions/decision_log.md`, mention it but defer to the decision
- If finding conflicts with security or correctness, report it regardless
- Bug fixes from code review may be logged to `fixes/fix_log.md`
- Architectural improvements should be logged to `decisions/decision_log.md` or `improvements/improvement_log.md`

---

## File Skip Patterns

To reduce token usage, **automatically skip** these file types unless explicitly reviewed:

### Always Skip (build/generated/external)
```
node_modules/
dist/
build/
out/
.next/
coverage/
.parcel-cache/
__pycache__/
*.egg-info/
venv/
env/
*.lock (package-lock.json, yarn.lock, etc)
*.log
.env.* (secrets)
.git/
```

### Skip Unless Testing Explicitly Requested
```
**/*.test.ts
**/*.test.js
**/*.spec.ts
**/*.spec.js
**/__tests__/**
**/tests/**
jest.config.* 
vitest.config.*
karma.conf.*
mocha.opts
cypress/
e2e/
playwright/
```

### Skip Unless Configuration Review Explicitly Requested
```
.github/workflows/
.gitlab-ci.yml
.circleci/
.travis.yml
.editorconfig
.eslintrc*
.prettierrc*
.babelrc*
tsconfig.json
webpack.config.*
rollup.config.*
vite.config.*
```

### Skip Unless Documentation Review Explicitly Requested
```
README.md
*.md (all markdown)
docs/
./**/*.md
CHANGELOG.md
LICENSE
```

### Conditional Skip (depends on project context)
```
**/*.d.ts       → Skip unless type definition review requested
types/          → Skip unless type system review requested
migrations/     → Review if touching database, otherwise skip
scripts/        → Review if touching automation, otherwise skip
stubs/          → Skip unless explicitly included
mocks/          → Skip unless testing setup is under review
fixtures/       → Skip unless data fixtures are under review
```

---

## File Review Priority

When reviewing production code, prioritize in this order:

1. **Core business logic** (highest risk)
   - Domain models
   - API handlers
   - Database queries
   - Authentication/authorization logic
   - Payment/transaction processing
   - Critical algorithms

2. **Supporting infrastructure** (medium risk)
   - Utilities
   - Helpers
   - Middleware
   - Interceptors
   - Hooks (React/Vue/etc)
   - Custom adapters

3. **UI/Display layer** (lower risk, but security-sensitive)
   - Components
   - Templates
   - Presentational logic
   - Styling

4. **Configuration** (review only if explicitly requested)
   - Config files
   - Environment setup
   - CI/CD pipeline definitions

---

## Severity Ranking

Report findings in this order:

### 🔴 CRITICAL (Report immediately)
- Breaks existing functionality (logic error, infinite loop, crash)
- Security vulnerability (any OWASP top 10)
- Data loss or corruption risk
- Race condition causing data inconsistency
- Unhandled exception in production path

### 🟠 HIGH (Report with priority)
- Missing error handling in critical path
- Performance degradation at scale (N+1 queries, memory leak)
- Violates established security policy
- Hard-coded limits that block scaling
- Dead code in critical path (confuses maintenance)

### 🟡 MEDIUM (Report but can defer)
- Code quality issues (complexity, readability, naming)
- Maintainability debt (duplication, unclear intent)
- Minor performance inefficiency
- Test coverage gaps in important functions
- Minor API inconsistency

### 🔵 LOW (Optional, can suggest)
- Style/formatting (if not auto-checked)
- Nice-to-have optimizations
- Future-proofing suggestions
- Documentation improvements

---

## What NOT to Report

- **Style/linting issues** → These should be caught by prettier/eslint, not human review
- **Bikeshedding** → Variable names, method ordering, file organization (unless unreadable)
- **Premature optimization** → Unless it's actual production blocker
- **Design philosophy differences** → Defer to decision_log.md
- **Missing features** → That's requirements, not code review
- **Test philosophy** → Unless tests are actively failing

---

## What ALWAYS Report

- Anything that breaks in production
- Any OWASP vulnerability (SQLi, XSS, CSRF, auth bypass, secrets exposure)
- Race conditions or concurrency bugs
- Null reference without guard
- Infinite loops or unbounded recursion
- Resource leaks (file handles, connections, memory)
- SQL injection vectors (even if "unlikely")

---

## Finding Documentation

When logging findings to memory:

**For bugs found via code review:**
```
_brain/fixes/fix_log.md entry:
| Issue ID | Title | Root Cause | Fix | Date |
| ... | Code review finding | description | action | date |
```

**For architectural improvements:**
```
_brain/improvements/improvement_log.md entry:
| Idea | Category | Reason | Effort | Priority |
| ... | Code review suggestion | why this matters | estimated work | low/medium/high |
```

---

## Multi-language Considerations

Adjust severity based on language/runtime:

- **Python/JS:** Type errors are HIGH (no compile-time check)
- **Go:** Unhandled errors are CRITICAL
- **Rust:** If it compiles, most memory/concurrency issues are caught
- **Java/.NET:** Null checks are more critical
- **C/C++:** Memory safety is CRITICAL always

---

## Performance Baseline

Raise HIGH/CRITICAL for performance if:

- Database query runs N times per request (N+1 problem)
- Loop fetches data inside inner loop
- No pagination on large datasets
- Memory grows unbounded (cache without eviction)
- Synchronous operation blocks async flow
- Parsing/serialization in hot path

Defer if:
- Optimization premature (no profiling data)
- Requires refactoring vs. one-line fix
- Performance acceptable for current scale

---

## Security Baseline

Raise CRITICAL for any:

- User input reaches database without sanitization (SQLi)
- User input reaches HTML without escaping (XSS)
- Authentication bypass possible
- Authorization not enforced (user can access other user's data)
- Secrets in code or logs
- CORS/CSRF not protected (if cross-origin actions possible)
- Insecure deserialization
- Weak crypto or missing encryption

---

## Scalability Baseline

Raise HIGH if code:

- Cannot handle 10x current users (hard-coded limits, O(n²) algorithms, unbounded queues)
- Cannot handle 100x current data (no pagination, loads all records, no indexing)
- Requires significant refactoring to scale (not just config change)
- Stores unbounded state per user/entity
- Uses global mutable state that can't be partitioned

Defer if:
- Current scale doesn't need it
- Architectural design is already prepared for scaling
- Hook/abstraction exists for future refactoring

