# files/

`uploads/<document id>/` holds uploaded policies, memos, manuals and forms under random names. Old versions are never overwritten. The folder is blocked from the web by `.htaccess`; files are only served through the download page, which checks login and records the download. `exports/` is reserved for generated exports.

Contents are git-ignored (company documents). Back them up with `backend/cron/backup.php`.
