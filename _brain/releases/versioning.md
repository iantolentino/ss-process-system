# VERSIONING RULES

## Versioning Scheme
This project uses [Semantic Versioning](https://semver.org/): `MAJOR.MINOR.PATCH`

| Part  | Increment when                                       |
|-------|------------------------------------------------------|
| MAJOR | A breaking change or a major product milestone       |
| MINOR | A new feature is added (non-breaking)                |
| PATCH | A bug fix, hotfix, or minor non-breaking improvement |

## Pre-Release Stages
- `0.x.x` — MVP / unstable / internal only
- `1.0.0` — First stable public release
- `1.x.x` — Ongoing stable releases

## Release Checklist
Before tagging a release:

- [ ] Current objective and release readiness are verified in `CURRENT_STATE.md`
- [ ] `releases/changelog.md` is updated with all changes
- [ ] Version number is bumped in the project config file
- [ ] Deployment to staging verified and tested
- [ ] Deployment to production complete
- [ ] Health checks pass in production

## Tagging Convention
```
git tag -a v1.0.0 -m "Release v1.0.0"
git push origin v1.0.0
```
