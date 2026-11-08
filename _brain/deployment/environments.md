# ENVIRONMENTS

---

## Environment Summary

| Environment | Purpose                    | Access        |
|-------------|----------------------------|---------------|
| Local       | Individual development     | Developer only |
| Staging     | Pre-release testing        | Team only      |
| Production  | Live system for real users | Public         |

---

## Environment Variables

Each environment must define the following variables.
Store secrets using the method defined in `security/secrets_policy.md`.

| Variable       | Local         | Staging           | Production        |
|----------------|---------------|-------------------|-------------------|
| `APP_ENV`      | `local`       | `staging`         | `production`      |
| `APP_URL`      | `localhost`   | `[staging URL]`   | `[prod URL]`      |
| `DATABASE_URL` | local DB      | staging DB        | production DB     |
| `APP_SECRET`   | any string    | from secret store | from secret store |
| `DEBUG`        | `true`        | `false`           | `false`           |

Add project-specific variables below:

| Variable | Local | Staging | Production |
|----------|-------|---------|------------|
|          |       |         |            |

---

## Environment Rules
- Never use production credentials in local or staging
- Never commit `.env` files to version control
- Staging must mirror production configuration as closely as possible
- All environment variable changes must be applied to all environments before deployment
