#!/usr/bin/env bash
# Smoke test for auth against a LOCAL server. Usage: tests/auth_smoke.sh http://localhost/process admin@example.test 'Password'
B=${1:?base url}; EMAIL=${2:?email}; PW=${3:?password}
J=$(mktemp); fail=0
tok(){ curl -s -c "$J" -b "$J" "$B/$1" | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
check(){ [ "$2" = "$3" ] && echo "ok   $1" || { echo "FAIL $1 (got '$2', want '$3')"; fail=1; }; }

check "POST without csrf rejected" "$(curl -s -o /dev/null -w '%{http_code}' -d x=1 "$B/login")" 400
check "anonymous dashboard redirects" "$(curl -s -o /dev/null -w '%{http_code}' "$B/dashboard")" 302
check ".env blocked" "$(curl -s -o /dev/null -w '%{http_code}' "$B/.env")" 403
check "backend/ blocked" "$(curl -s -o /dev/null -w '%{http_code}' "$B/backend/bootstrap.php")" 403
check "bad password rejected" "$(curl -s -c "$J" -b "$J" -d "csrf=$(tok login)&email=$EMAIL&password=nope-nope" "$B/login" | grep -c 'Invalid email or password')" 1
check "good login redirects" "$(curl -s -c "$J" -b "$J" -o /dev/null -w '%{http_code}' -d "csrf=$(tok login)&email=$EMAIL&password=$PW" "$B/login")" 302
check "dashboard after login" "$(curl -s -c "$J" -b "$J" -o /dev/null -w '%{http_code}' "$B/dashboard")" 200
check "logout via GET ignored" "$(curl -s -c "$J" -b "$J" -o /dev/null "$B/logout"; curl -s -c "$J" -b "$J" -o /dev/null -w '%{http_code}' "$B/dashboard")" 200
curl -s -c "$J" -b "$J" -o /dev/null -d "csrf=$(tok dashboard)" "$B/logout"
check "dashboard after logout" "$(curl -s -c "$J" -b "$J" -o /dev/null -w '%{http_code}' "$B/dashboard")" 302
rm -f "$J"; exit $fail
