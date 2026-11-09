#!/usr/bin/env bash
# Smoke test for the audit viewer against a LOCAL server (creates test rows!).
# Usage: tests/audit_smoke.sh http://localhost/process admin@example.test 'AdminPassword'
B=${1:?base url}; ADMIN=${2:?admin email}; APW=${3:?admin password}
S=$RANDOM; PW='SmokePass123'; T=$(mktemp -d); fail=0
M="/c/xampp/mysql/bin/mysql.exe -uroot ss_process -N -e"
check(){ [ "$2" = "$3" ] && echo "ok   $1" || { echo "FAIL $1 (got '$2', want '$3')"; fail=1; }; }
tok(){ curl -s -c "$1" -b "$1" "$B/$2" | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login(){ curl -s -c "$1" -b "$1" -o /dev/null -d "csrf=$(tok "$1" login)&email=$2&password=$3" "$B/login"; }

login "$T/admin" "$ADMIN" "$APW"
curl -s -c "$T/admin" -b "$T/admin" -o /dev/null -d "csrf=$(tok "$T/admin" users)&first_name=%3DEVIL$S&last_name=Formula&email=f$S@example.test&role=employee&password=$PW&active=1" "$B/users"
login "$T/emp" "f$S@example.test" "$PW"

check "employee blocked" "$(curl -s -b "$T/emp" -o /dev/null -w '%{http_code}' "$B/audit")" 403
check "admin sees page" "$(curl -s -b "$T/admin" -o /dev/null -w '%{http_code}' "$B/audit")" 200
check "entity filter works" "$(curl -s -b "$T/admin" "$B/audit?entity=user&action=login" | grep -c "<td>=EVIL$S Formula")" 1
check "filter excludes others" "$(curl -s -b "$T/admin" "$B/audit?entity=process&user=$($M "SELECT id FROM users WHERE email='f$S@example.test'")" | grep -c 'No entries match')" 1
check "shows field changes" "$(curl -s -b "$T/admin" "$B/audit?entity=user&action=create" | grep -c 'role: employee' | awk '{print ($1>0)}')" 1
check "future date range empty" "$(curl -s -b "$T/admin" "$B/audit?from=2999-01-01" | grep -c 'No entries match')" 1

CSV=$(curl -s -b "$T/admin" "$B/audit?entity=user&action=login&export=csv")
check "csv has header" "$(echo "$CSV" | head -1)" "id,when,who,action,entity,entity_id,ip,before,after"
check "csv neutralises formulas" "$(echo "$CSV" | grep -c "'=EVIL$S")" 1
check "csv export is audited" "$($M "SELECT COUNT(*)>0 FROM audit_log WHERE action='export' AND entity='audit_log' AND at > NOW() - INTERVAL 1 MINUTE")" 1
check "export not available to employee" "$(curl -s -b "$T/emp" -o /dev/null -w '%{http_code}' "$B/audit?export=csv")" 403
rm -rf "$T"; exit $fail
