#!/usr/bin/env bash
# Security smoke test (LOCAL server, APP_ENV=local): headers, access control, reset hardening, login throttle.
# Usage: tests/security_smoke.sh http://localhost/process admin@example.test 'AdminPassword'
B=${1:?base url}; ADMIN=${2:?admin email}; APW=${3:?admin password}
S=$RANDOM; PW='SmokePass123'; T=$(mktemp -d); fail=0
M="/c/xampp/mysql/bin/mysql.exe -uroot ss_process -N -e"
LOG="$(dirname "$0")/../logs/app-$(date +%F).log"
check(){ [ "$2" = "$3" ] && echo "ok   $1" || { echo "FAIL $1 (got '$2', want '$3')"; fail=1; }; }
tok(){ curl -s -c "$1" -b "$1" "$B/$2" | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login(){ curl -s -c "$1" -b "$1" -o /dev/null -d "csrf=$(tok "$1" login)&email=$2&password=$3" "$B/login"; }
code(){ curl -s -o /dev/null -w '%{http_code}' "$@"; }

# headers
H=$(curl -sI "$B/login")
check "CSP header" "$(echo "$H" | grep -ci '^content-security-policy:.*frame-ancestors .none')" 1
check "nosniff header" "$(echo "$H" | grep -ci '^x-content-type-options: nosniff')" 1
check "no-referrer header" "$(echo "$H" | grep -ci '^referrer-policy: no-referrer')" 1
check "frame deny" "$(echo "$H" | grep -ci '^x-frame-options: deny')" 1

# nothing private is served
for p in .env .git/config backend/bootstrap.php database/migrations/001_init.sql logs files/uploads docs/DEPLOY.md tests/auth_smoke.sh PROGRESS.md .cpanel.yml authentication/auth.php; do
  check "blocked: $p" "$(code "$B/$p")" 403
done
check "unknown page 404" "$(code "$B/nope")" 404
check "anonymous dashboard redirects" "$(code "$B/dashboard")" 302
check "anonymous download redirects" "$(code "$B/download?v=1")" 302

# role boundaries
D1=$($M "SELECT id FROM departments WHERE slug='client-success'")
login "$T/admin" "$ADMIN" "$APW"
curl -s -c "$T/admin" -b "$T/admin" -o /dev/null -d "csrf=$(tok "$T/admin" 'users?new=1')&first_name=T&last_name=Sec&email=sec$S@example.test&role=employee&department_id=$D1&password=$PW&active=1" "$B/users"
login "$T/emp" "sec$S@example.test" $PW
for p in users audit "audit?export=csv"; do check "employee blocked: $p" "$(code -b "$T/emp" "$B/$p")" 403; done
check "employee cannot post a new document" "$(curl -s -o /dev/null -w '%{http_code}' -b "$T/emp" -c "$T/emp" --data-urlencode "csrf=$(tok "$T/emp" policies)" -d type=policy -d title=x "$B/policies")" 403
check "POST without csrf rejected" "$(code -b "$T/emp" -d action=add "$B/onboarding")" 400
check "GET logout does not sign out" "$(curl -s -b "$T/emp" -o /dev/null "$B/logout"; code -b "$T/emp" "$B/dashboard")" 200

# reset hardening: link must use APP_URL, not the Host header; throttled after 3
# with APP_URL set, a forged Host must not reach the link (runs the real function from the CLI)
APP_URL=https://real.example/process /c/xampp/php/php.exe -r '$_SERVER["HTTP_HOST"]="evil.example"; require "authentication/auth.php"; request_reset("sec'$S'@example.test");'
check "reset link uses APP_URL, not Host" "$(grep "reset link" "$LOG" | tail -1 | grep -c 'https://real.example/process/reset?token=')" 1
for i in 1 2 3 4; do curl -s -o /dev/null -c "$T/r" -b "$T/r" --data-urlencode "csrf=$(tok "$T/r" forgot)" -d "email=sec$S@example.test" "$B/forgot"; done
check "reset throttled to 3/hour (1 CLI + 2 web)" "$($M "SELECT COUNT(*) FROM password_resets WHERE user_id=(SELECT id FROM users WHERE email='sec$S@example.test')")" 3
check "throttled reply is identical" "$(curl -s -c "$T/r" -b "$T/r" --data-urlencode "csrf=$(tok "$T/r" forgot)" -d "email=sec$S@example.test" "$B/forgot" | grep -c 'reset link is on its way')" 1

# new reset invalidates older ones
TK=$(grep "reset link" "$LOG" | tail -1 | grep -o 'token=[a-f0-9]*' | cut -d= -f2)
curl -s -o /dev/null -c "$T/r" -b "$T/r" --data-urlencode "csrf=$(tok "$T/r" "reset?token=$TK")" -d "token=$TK" -d "password=NewSmoke456" "$B/reset"
check "all reset links dead after use" "$($M "SELECT COUNT(*) FROM password_resets WHERE used_at IS NULL AND user_id=(SELECT id FROM users WHERE email='sec$S@example.test')")" 0

# per-IP login throttle: many different emails from one IP get locked
$M "DELETE FROM login_attempts"
for i in $(seq 1 21); do curl -s -o /dev/null -c "$T/l" -b "$T/l" -d "csrf=$(tok "$T/l" login)&email=nobody$i$S@example.test&password=wrong" "$B/login"; done
check "IP locked after 20 failures" "$(curl -s -c "$T/l" -b "$T/l" -d "csrf=$(tok "$T/l" login)&email=other$S@example.test&password=wrong" "$B/login" | grep -c 'Too many failed attempts')" 1
$M "DELETE FROM login_attempts"
rm -rf "$T"; exit $fail
