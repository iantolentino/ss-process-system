# SECRETS POLICY

## Absolute Rules
- Never hardcode secrets, passwords, or API keys in source code
- Never commit `.env` files or secret files to version control
- Never log secrets, tokens, or passwords — not even partially
- Never expose secrets in API responses, error messages, or client-side code

---

## Secret Storage by Environment

| Environment | Storage Method                                      |
|-------------|-----------------------------------------------------|
| Local       | `.env` file — gitignored, never committed           |
| Staging     | CI/CD environment variables (GitHub Actions secrets, etc.) |
| Production  | Secret manager (AWS Secrets Manager / Doppler / Vault / Railway env vars) |

---

## .gitignore Requirements
Always include:

```
.env
.env.*
*.key
*.pem
secrets/
```

---

## Rotation Policy
| Secret Type    | Rotation Trigger                  | Minimum Frequency |
|----------------|-----------------------------------|-------------------|
| App secrets    | Team member departure             | As needed         |
| API keys       | Compromise suspected or confirmed | Every 90 days     |
| DB credentials | Team member departure             | Every 180 days    |
| Auth tokens    | Automatic (based on session rules)| Per session rules |

---

## If a Secret Is Leaked
1. Revoke the compromised secret immediately
2. Rotate all related secrets (same environment, same scope)
3. Audit access logs for unauthorized use
4. Notify affected parties if user data was exposed
5. Log the incident with date and scope
6. Update this file if policy change is needed
