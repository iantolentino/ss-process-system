# tests/

Smoke tests: bash + curl against a LOCAL server and the local MySQL (they create test rows; never run them on the live site).

    bash tests/auth_smoke.sh http://localhost/process admin@example.test 'AdminPassword'

Same arguments for `portal`, `process`, `audit`, `handbook`, `features`, `notices`, `scale`, `security` and `templates`. `restore_drill.sh` backs up, restores into a scratch database and compares every table and file. `.env` must point at the database named in the scripts (`ss_process`).
