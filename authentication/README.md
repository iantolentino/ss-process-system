# authentication/

`auth.php`: sessions, CSRF checks, roles, login lockout, password reset.

- Roles: `admin`, `policy_owner`, `process_owner`, `employee` (`ROLES`, `ROLE_LABELS`). Pages call `require_login()` or `require_role(...)`.
- Limits: 5 failed logins per email and 20 per IP in 15 minutes; sessions end after 8 idle hours; reset links last 1 hour and work once.
- Every login, logout and reset is written to `audit_log`.

Debugging a login problem: `logs/app-DATE.log` shows `login failed` / `login locked` lines.
