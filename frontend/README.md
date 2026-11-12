# frontend/

What people see. No build step: plain PHP pages, one stylesheet, two small scripts.

- `pages/` one file per screen (`dashboard.php`, `process.php`, `policies.php`, ...). The router in `index.php` only serves names listed in its `$pages` array.
- `components/` shared pieces: layout (`app_open.php` / `app_close.php`, the sidebar), `flowchart.php`, `orgchart.php`, `pager.php`.
- `css/base.css` the whole look (shadcn-style tokens at the top; change them to restyle the site).
- `js/` `app.js` (accessibility fixes, tabs, mobile menu, mild GSAP fade-in) and `process-edit.js` (step editor).

Debugging: a blank page or "Something went wrong" means a PHP error; see `logs/error-DATE.log` (set `APP_DEBUG=1` in `.env` locally to see it on screen).
