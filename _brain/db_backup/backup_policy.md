# Database Backup Policy

> Optional module. Load only for database, backup, or restore work.

## Backup strategy

[Automated snapshot / logical dump / managed provider backup]

## Schedule

| Environment | Frequency | Retention |
| --- | --- | --- |
| Production | | |
| Staging | | |

## Storage location

[Encrypted storage location]

## Restore procedure

1. [Step]
2. [Step]
3. Verify data integrity.

## Rules

- Never store unencrypted backups in public/shared locations.
- Never commit backup files or credentials.
- Follow `security/secrets_policy.md`.
