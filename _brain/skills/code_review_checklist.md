# SENIOR ENGINEER CODE REVIEW CHECKLIST

This checklist is used during `CODE_REVIEW` mode. Reference it when evaluating code changes.

**Principle:** Think like a principal engineer with 15+ years of production experience. Challenge every decision. Protect the project from bugs, security issues, technical debt, and poor architecture.

---

## 🔴 SECTION 1: BUGS & CORRECTNESS

### Logic Errors
- [ ] Does each conditional handle all paths (no fall-through bugs)?
- [ ] Are boolean conditions correct (not inverted)?
- [ ] Do loops terminate (no infinite loops)?
- [ ] Off-by-one errors in array/string operations?
- [ ] State transitions valid (no impossible states)?

### Null/Undefined Safety
- [ ] All object property accesses guarded against null?
- [ ] Array access bounds checked?
- [ ] Function return values checked before use?
- [ ] Optional chaining used where appropriate?
- [ ] `null` vs `undefined` handled consistently?

### Error Handling
- [ ] Try/catch blocks catch specific errors, not everything?
- [ ] Errors are logged with context (not silent failures)?
- [ ] Happy path and error path both return/complete?
- [ ] Network timeouts handled (not just connection errors)?
- [ ] Resource cleanup on error (file handles, connections, locks)?

### Edge Cases
- [ ] Empty input handled (empty array, empty string, null)?
- [ ] Boundary values tested (max int, min value, zero)?
- [ ] Special characters in strings handled (quotes, newlines, unicode)?
- [ ] Time-based edge cases (midnight, year boundary, DST)?
- [ ] Race condition possible (async + state mutation)?

### Resource Management
- [ ] Files/streams closed after use?
- [ ] Database connections returned to pool?
- [ ] Memory allocated is freed (no leaks)?
- [ ] Event listeners removed (no phantom listeners)?
- [ ] Timers cleared (setInterval, setTimeout)?

### Concurrency
- [ ] Shared state accessed safely (locks, atomics)?
- [ ] Race conditions possible between operations?
- [ ] Deadlock possible (circular waits)?
- [ ] Promise rejections handled?
- [ ] Async/await doesn't create unbounded concurrency?

---

## 🔐 SECTION 2: SECURITY

### Input Validation
- [ ] User input validated before use?
- [ ] String length limits enforced?
- [ ] Type validation performed (string vs number vs object)?
- [ ] File uploads validated (size, type, content)?
- [ ] URL parameters escaped/validated?

### SQL Injection
- [ ] Parameterized queries used (no string concatenation)?
- [ ] Dynamic table names sanitized?
- [ ] User input never reaches raw SQL?
- [ ] ORM properly configured (not bypassed)?

### XSS (Cross-Site Scripting)
- [ ] User content escaped before rendering?
- [ ] HTML entities encoded?
- [ ] `innerHTML` avoided (use `textContent` for text)?
- [ ] No `eval()` of user input?
- [ ] Template injection prevented?

### Authentication & Authorization
- [ ] Auth token/session validated on every protected endpoint?
- [ ] Password hashed (not stored plain)?
- [ ] Rate limiting on login attempts?
- [ ] User can only access their own data (not other users')?
- [ ] Admin actions require admin role check?
- [ ] Token expiration enforced?

### Secrets & Credentials
- [ ] No API keys in code or comments?
- [ ] No passwords in config files?
- [ ] Secrets loaded from environment, not files?
- [ ] `.env` file in `.gitignore`?
- [ ] Secrets not logged or exposed in error messages?

### CSRF (Cross-Site Request Forgery)
- [ ] State-changing operations (POST/PUT/DELETE) require CSRF token?
- [ ] Token validated on server?
- [ ] Token scope limited (per-user, per-session)?

### Dependencies
- [ ] No unvetted third-party code executed?
- [ ] Dependencies pinned to known-good versions?
- [ ] Security updates not ignored (CVEs?)?
- [ ] Dependency tree reviewed for conflicts?

### Data Protection
- [ ] Sensitive data encrypted at rest?
- [ ] HTTPS enforced (no plaintext auth)?
- [ ] Sensitive data not logged?
- [ ] Database backup encrypted?
- [ ] PII handled according to policy (GDPR, CCPA)?

---

## ⚡ SECTION 3: PERFORMANCE

### Queries & I/O
- [ ] N+1 query problem avoided (batch queries)?
- [ ] Database queries indexed properly?
- [ ] Pagination implemented for large datasets?
- [ ] Query results cached where appropriate?
- [ ] Connection pooling used for databases?

### Computation
- [ ] Algorithms efficient for expected scale (not O(n²) where O(n) exists)?
- [ ] Heavy computation doesn't block event loop?
- [ ] Memoization used for expensive repeated calculations?
- [ ] String concatenation in loop optimized?
- [ ] Recursive depth won't cause stack overflow?

### Memory
- [ ] Large objects released when no longer needed?
- [ ] Circular references won't prevent garbage collection?
- [ ] Unbounded collections (cache, queue) have eviction policy?
- [ ] Memory usage constant relative to input size?

### Rendering (if applicable)
- [ ] Re-renders minimized (memoization, shouldUpdate checks)?
- [ ] Large lists virtualized (not all rendered)?
- [ ] Images lazy-loaded or appropriately sized?
- [ ] Bundle size considered (tree-shaking applied)?
- [ ] CSS not causing layout thrashing?

### Network
- [ ] Requests batched (not N requests for N items)?
- [ ] Response payload minimal (only needed fields)?
- [ ] Compression enabled (gzip)?
- [ ] Caching headers set appropriately?
- [ ] Timeouts prevent hanging requests?

---

## 🏗️ SECTION 4: MAINTAINABILITY

### Code Clarity
- [ ] Variable names descriptive (not `x`, `temp`, `data`)?
- [ ] Function names describe what they do (verb + noun)?
- [ ] Function length reasonable (fits on screen)?
- [ ] Cyclomatic complexity not excessive (fewer branches)?
- [ ] Comments explain WHY, not WHAT?

### DRY (Don't Repeat Yourself)
- [ ] Similar code blocks extracted to function?
- [ ] Duplicate logic consolidated?
- [ ] Magic numbers extracted to named constants?
- [ ] Repeated patterns detected and refactored?

### SOLID Principles
- [ ] Single Responsibility: Does each class/function do ONE thing?
- [ ] Open/Closed: Easy to extend without modifying existing code?
- [ ] Liskov Substitution: Subclasses properly substitute parent?
- [ ] Interface Segregation: Interfaces not too broad?
- [ ] Dependency Inversion: Depends on abstractions, not implementations?

### Dead Code
- [ ] Unused imports removed?
- [ ] Dead code branches deleted (not just commented)?
- [ ] Unused variables removed?
- [ ] Unreachable code removed?

### Testing & Mockability
- [ ] Code testable (not deeply coupled to implementation)?
- [ ] Dependencies injectable (for testing)?
- [ ] Side effects isolated (easier to test)?
- [ ] Public API clear (not overly internal)?

### Documentation
- [ ] Public API documented (parameters, return, exceptions)?
- [ ] Non-obvious logic commented?
- [ ] Type definitions clear (not `any`)?
- [ ] README updated if behavior changed?

---

## 📈 SECTION 5: SCALABILITY

### Architecture
- [ ] Will this work with 10x more users? (No per-user storage limits hit?)
- [ ] Will this work with 100x more data? (No full-scan assumptions?)
- [ ] Will this work with 2x more features? (Modular enough to extend?)
- [ ] Can components be separated if needed (microservices, distributed)?

### Database
- [ ] Schema supports growth (not hard-coded sizes)?
- [ ] Indexes planned for expected queries?
- [ ] Sharding strategy exists (if needed)?
- [ ] Backup/recovery viable at scale?

### Concurrency
- [ ] Can handle multiple simultaneous requests?
- [ ] State shared safely (not memory-corrupted)?
- [ ] Connection pools sized for expected load?
- [ ] Timeouts prevent resource exhaustion?

### Caching Strategy
- [ ] What gets cached (hot data)?
- [ ] When does cache invalidate?
- [ ] Cache size bounded (not memory leak)?
- [ ] Fallback if cache fails?

### Horizontal Scaling
- [ ] Stateless (can run on multiple servers)?
- [ ] Session stored externally (not local memory)?
- [ ] Shared resources (DB, cache) accessible from any instance?
- [ ] Load balancer compatible?

### Rate Limiting / Throttling
- [ ] Expensive operations rate-limited?
- [ ] Query complexity bounded?
- [ ] Unbounded uploads prevented?
- [ ] Resource exhaustion prevented?

---

## FINAL DECISION RULES

### Report as BUG if:
- Breaks existing functionality
- Logic error causes wrong output
- Memory/resource leak
- Race condition
- Any security vulnerability
- Silent data corruption

### Report as PERFORMANCE issue if:
- N+1 problem or similar query inefficiency
- Algorithm complexity inappropriate for expected scale
- Memory grows unbounded
- Blocks event loop unnecessarily
- Degrades at 10x scale

### Report as SECURITY issue if:
- Any OWASP top 10 vulnerability class
- Secrets exposed
- User isolation broken
- Authentication/authorization bypassed
- Input not validated before use

### Report as MAINTAINABILITY issue if:
- Code unreadable or overly complex
- Duplicate code everywhere
- Dead code cluttering logic
- SOLID principles violated
- Wrong abstraction chosen

### Report as SCALABILITY issue if:
- Hard-coded limits
- O(n²) where O(n) exists
- Unbounded state accumulation
- Not horizontally scalable
- Single point of failure

### SKIP if:
- Already logged in `fixes/fix_log.md` (same issue)
- Style preference only
- Requires only documentation
- Future optimization, not current blocker
- Disagrees with locked architectural decision
