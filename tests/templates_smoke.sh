#!/usr/bin/env bash
# Smoke test for cadence grouping, starter templates, detailed fields and archive/restore (LOCAL server; creates test rows).
# Needs: php database/seed_templates.php run once.
# Usage: tests/templates_smoke.sh http://localhost/process admin@example.test 'AdminPassword'
B=${1:?base url}; ADMIN=${2:?admin email}; APW=${3:?admin password}
S=$RANDOM; PW='SmokePass123'; T=$(mktemp -d); fail=0
M="/c/xampp/mysql/bin/mysql.exe -uroot ss_process -N -e"
check(){ [ "$2" = "$3" ] && echo "ok   $1" || { echo "FAIL $1 (got '$2', want '$3')"; fail=1; }; }
tok(){ curl -s -c "$1" -b "$1" "$B/$2" | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login(){ curl -s -c "$1" -b "$1" -o /dev/null -d "csrf=$(tok "$1" login)&email=$2&password=$3" "$B/login"; }
post(){ curl -s -c "$1" -b "$1" -w '\n%{http_code}' --data-urlencode "csrf=$(tok "$1" "$2")" "${@:3}" "$B/$2"; }
status(){ tail -1; }

D2=$($M "SELECT id FROM departments WHERE slug='information-technology'" | tr -d '\r')
login "$T/admin" "$ADMIN" "$APW"
post "$T/admin" users -d "first_name=T" -d "last_name=Emp" -d "email=em$S@example.test" -d "role=employee" -d "department_id=$D2" -d "password=$PW" -d "active=1" >/dev/null
login "$T/em" "em$S@example.test" $PW

# --- gallery and prefill
GAL=$(curl -s -b "$T/admin" "$B/process-edit?dept=information-technology")
check "gallery lists the six starter templates" "$(echo "$GAL" | grep -c 'Daily routine\|Weekly routine\|Monthly routine (month-end)\|Quarterly review\|Yearly cycle\|As-needed request')" 6
TPL=$($M "SELECT id FROM processes WHERE kind='template' AND name='Weekly routine'" | tr -d '\r')
FORM=$(curl -s -b "$T/admin" "$B/process-edit?dept=information-technology&copy=$TPL")
check "template prefills steps and cadence" "$(echo "$FORM" | grep -c 'Send the weekly report\|<option value="weekly" selected>')" 2
check "copy starts as draft process" "$(echo "$FORM" | grep -c '<option selected>draft')" 1

# --- create a live process with the detailed fields
R=$(post "$T/admin" "process-edit?dept=information-technology" -d "dept=information-technology" -d "name=Backups $S" -d "purpose=Keep data safe" -d "cadence=daily" -d "kind=process" -d "status=active" \
  -d "inputs=Server access $S" -d "kpi=All jobs green $S" -d "exceptions=Disk full $S" -d "escalation=IT Manager $S" \
  -d "steps[0][title]=Check jobs" -d "steps[0][time]=15 minutes" -d "steps[1][title]=Fix failures")
PID=$($M "SELECT id FROM processes WHERE name='Backups $S'" | tr -d '\r')
check "process created with cadence daily" "$($M "SELECT cadence FROM processes WHERE id=$PID" | tr -d '\r')" daily
check "invalid cadence rejected" "$(post "$T/admin" "process-edit?dept=information-technology" -d "dept=information-technology" -d "name=Bad $S" -d "purpose=x" -d "cadence=hourly" -d "steps[0][title]=a" | grep -c 'Pick how often')" 1
PAGE=$(curl -s -b "$T/em" "$B/process?id=$PID")
check "overview shows the detailed fields" "$(echo "$PAGE" | grep -oE "Server access $S|All jobs green $S|Disk full $S|IT Manager $S" | wc -l | tr -d " ")" 4
check "step time shown" "$(echo "$PAGE" | grep -c '15 minutes')" 1
LIB=$(curl -s -b "$T/em" "$B/process-library?dept=information-technology")
check "department page groups by cadence" "$(echo "$LIB" | grep -c 'id="c-daily"')" 1
check "process listed under it" "$(echo "$LIB" | grep -c "Backups $S")" 1
check "templates are not listed as live processes" "$(echo "$LIB" | grep -c 'Weekly routine')" 0
check "templates not in search" "$(curl -s -b "$T/em" "$B/process-library?q=Weekly+routine" | grep -c 'href="[^"]*process?id=')" 0
check "templates not counted in onboarding picks" "$(curl -s -b "$T/admin" "$B/onboarding?dept=information-technology" | grep -c 'Weekly routine')" 0
check "handbook has the cadence heading" "$(curl -s -b "$T/em" "$B/handbook" | grep -c 'Daily processes')" 1

# --- archive / restore
check "employee cannot archive" "$(post "$T/em" "process?id=$PID" -d "id=$PID" -d action=archive | status)" 403
post "$T/admin" "process?id=$PID" -d "id=$PID" -d action=archive >/dev/null
check "archived is hidden from staff" "$(curl -s -b "$T/em" -o /dev/null -w '%{http_code}' "$B/process?id=$PID")" 404
check "editors see it under Removed" "$(curl -s -b "$T/admin" "$B/process-library?dept=information-technology" | grep -c "Removed (")" 1
post "$T/admin" "process?id=$PID" -d "id=$PID" -d action=restore >/dev/null
check "restored is visible again" "$(curl -s -b "$T/em" -o /dev/null -w '%{http_code}' "$B/process?id=$PID")" 200
check "archive and restore are audited" "$($M "SELECT COUNT(*) FROM audit_log WHERE entity='process' AND entity_id=$PID AND action IN ('archive','restore')" | tr -d '\r')" 2
check "versions are kept (still 1)" "$($M "SELECT COUNT(*) FROM process_versions WHERE process_id=$PID" | tr -d '\r')" 1

# --- template made by an editor
post "$T/admin" "process-edit?dept=information-technology" -d "dept=information-technology" -d "name=Tpl $S" -d "purpose=reusable" -d "cadence=monthly" -d "kind=template" -d "status=active" -d "steps[0][title]=one" >/dev/null
check "saved as template" "$($M "SELECT kind FROM processes WHERE name='Tpl $S'" | tr -d '\r')" template
check "new template appears in the gallery" "$(curl -s -b "$T/admin" "$B/process-edit?dept=information-technology" | grep -c "Tpl $S")" 1
# --- company overview (all departments)
OV=$(curl -s -b "$T/em" "$B/overview")
check "overview lists the new process" "$(echo "$OV" | grep -c "Backups $S")" 1
check "overview matrix has a daily column" "$(echo "$OV" | grep -c "overview?c=daily")" 2
check "cadence filter keeps only that cadence" "$(curl -s -b "$T/em" "$B/overview?c=weekly" | grep -c "Backups $S")" 0
check "templates are not in the overview" "$(echo "$OV" | grep -c "Weekly routine")" 0
check "overview needs login" "$(curl -s -o /dev/null -w '%{http_code}' "$B/overview")" 302
rm -rf "$T"; exit $fail
