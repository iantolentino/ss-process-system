# DEPLOYMENT PLAN

> Define this during SYSTEM_GENERATION. Update when deployment strategy changes.

---

## Target Platform
[Cloud provider or hosting — e.g. AWS / Vercel / Railway / DigitalOcean / VPS]

## Deployment Strategy
[Direct / Blue-Green / Rolling / Canary]

- **Direct** — replace running instance (simple, brief downtime)
- **Blue-Green** — two environments, switch traffic (zero downtime)
- **Rolling** — gradual instance replacement (zero downtime, complex)
- **Canary** — small % of traffic to new version first (safest for large scale)

## CI/CD Pipeline
[GitHub Actions / GitLab CI / Bitbucket Pipelines / manual]

## Deployment Steps
1. Push to main branch (or tag a release)
2. CI runs tests — must pass before deploy
3. Build artifact (Docker image / compiled binary / bundle)
4. Deploy to staging environment
5. Run smoke tests on staging
6. Approve for production (manual gate or auto on success)
7. Deploy to production
8. Verify health checks pass

## Rollback Plan
If production deployment fails:
1. [Revert to previous Docker image / git revert / restore backup]
2. Verify rollback health
3. Log incident in `releases/changelog.md`
4. Fix root cause before re-deploying

## Health Checks
- [ ] App responds at root URL
- [ ] Database connection is healthy
- [ ] Auth flow works end to end
- [ ] Key API endpoints return expected responses
