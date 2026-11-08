# AUTH BOUNDARIES

> Define during SYSTEM_GENERATION. Update only when auth model changes.

---

## Authentication Method
[JWT / Session cookies / OAuth 2.0 / API Key / Magic Link / other]

## Authorization Model
[RBAC — Role-Based Access Control / ABAC — Attribute-Based / Simple ownership check]

---

## Roles

| Role    | Description                        | Permissions                     |
|---------|------------------------------------|---------------------------------|
| Admin   | Full system access                 | Create, read, update, delete all |
| User    | Standard account holder            | CRUD on own data only           |
| Guest   | Unauthenticated visitor            | Read public data only           |

Add project-specific roles below.

---

## Protected Routes

| Route pattern   | Required Role | Notes                  |
|-----------------|---------------|------------------------|
| `/admin/*`      | Admin         |                        |
| `/api/user/*`   | User          | Own data only          |
| `/api/public/*` | Guest         | No auth required       |

---

## Session Rules
- Token type: [JWT / session ID]
- Token expiry: [e.g. 15 min access / 7 day refresh]
- Refresh strategy: [rotate refresh token on each use / extend expiry]
- Logout behavior: [invalidate server-side token / client-side clear only]

---

## Auth Rules
- Never expose user IDs or internal references in public API responses
- Always validate ownership before allowing data access (not just authentication)
- Rate-limit auth endpoints to prevent brute force
