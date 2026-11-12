# Security review (2026-11-09)

Reviewed: routing, access rules, every POST handler's role check, SQL (all user input is bound; the few interpolated values are int-cast), output escaping, uploads/downloads, CSV export, login/reset flow, session handling, mail, headers. Checked by `tests/security_smoke.sh` (29 checks).

## Fixed in this review
| Risk | Fix |
|---|---|
| **Reset-link poisoning**: link was built from the request's Host header, so an attacker could request a reset for a victim and steer the email link to their own site | Link now comes from `APP_URL`. **Set `APP_URL` in production `.env`.** |
| Reset emails could be flooded at one person | Max 3 reset emails per account per hour; reply stays identical |
| Reset token could leak through Referer to the font host | `Referrer-Policy: no-referrer` |
| Old reset links stayed valid after a new one was used | Using one link kills all of that user's others |
| Login lockout was per-email only (one IP could try unlimited emails) | Added per-IP limit: 20 failures / 15 min |
| Sessions never expired; session ids could be forced | 8-hour idle timeout; `use_strict_mode`; cookie `Secure` always in production |
| No browser hardening | CSP (self + GSAP + fonts only, no framing, forms to self only), nosniff, X-Frame-Options, HSTS over HTTPS |
| Header injection through newlines in email subjects/addresses | Newlines stripped in `notify()` |

## Checked and fine
Draft/archived content hidden from people who can't edit; process owners limited to their department; employees blocked from Users and Audit; CSRF on every POST; logout is POST-only; uploads use an allow-list, random stored names, live outside public access and download as attachments with `nosniff`; CSV export neutralises formulas; SharePoint links must be http(s); internals/dotfiles/docs/tests/logs are blocked by `.htaccess`; passwords are bcrypt/argon via `password_hash`; login timing doesn't reveal which emails exist.

## Added 2026-11-12
Approval before publishing: only an admin can make new content visible to staff (a policy owner's upload and a process owner's active save wait as pending; pending and rejected versions are hidden from staff on the page, in downloads and in search). The approvals page is admin-only. PDF view (`download?inline=1`) is PDF-only, sends `application/pdf` with `nosniff`, and drops the page CSP for that one response because the browser's PDF viewer needs it. Read confirmations are one row per person per version and are audited. Checked by `tests/approval_smoke.sh` (37 checks).

## Known limits (accepted, documented)
- Lockout is per email, so someone can deliberately lock a colleague out for 15 minutes (the cost of stopping password guessing).
- CSP still allows inline scripts (a few small ones); moving them to files lets you drop `'unsafe-inline'`.
- No two-factor login yet (Microsoft 365 sign-in is planned and would bring this).
- Passwords need 8+ characters only; no breach-list check.
- Uploaded files are not virus-scanned. Only trusted editors can upload.
- Sessions are server files; "sign out everywhere" after a password change is not implemented.

## Before go-live
`APP_URL` set · `APP_ENV=production`, `APP_DEBUG=0` · HTTPS on and the redirect in `.htaccess` uncommented · the first admin password changed from any test value · `.env` not world-readable.
