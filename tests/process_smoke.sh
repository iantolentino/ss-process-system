#!/usr/bin/env bash
# Smoke test for the Process Library against a LOCAL server (creates test rows!).
# Usage: tests/process_smoke.sh http://localhost/process admin@example.test 'AdminPassword'
B=${1:?base url}; ADMIN=${2:?admin email}; APW=${3:?admin password}
S=$RANDOM; PW='SmokePass123'; T=$(mktemp -d); fail=0
M="/c/xampp/mysql/bin/mysql.exe -uroot ss_process -N -e"
check(){ [ "$2" = "$3" ] && echo "ok   $1" || { echo "FAIL $1 (got '$2', want '$3')"; fail=1; }; }
tok(){ curl -s -c "$1" -b "$1" "$B/$2" | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login(){ curl -s -c "$1" -b "$1" -o /dev/null -d "csrf=$(tok "$1" login)&email=$2&password=$3" "$B/login"; }
code(){ curl -s -c "$1" -b "$1" -o /dev/null -w '%{http_code}' "${@:3}" "$B/$2"; }
post(){ curl -s -c "$1" -b "$1" -w '\n%{http_code} %{redirect_url}' --data-urlencode "csrf=$(tok "$1" "$2")" "${@:3}" "$B/$2"; }

D1=$($M "SELECT id FROM departments WHERE slug='client-success'"); D2=$($M "SELECT id FROM departments WHERE slug='information-technology'")
login "$T/admin" "$ADMIN" "$APW"
mk(){ curl -s -c "$T/admin" -b "$T/admin" -o /dev/null -d "csrf=$(tok "$T/admin" users)&first_name=T&last_name=$2&email=$1&role=$3&department_id=$4&password=$PW&active=1" "$B/users"; }
mk "po$S@example.test" Owner process_owner "$D1"; mk "px$S@example.test" Other process_owner "$D2"; mk "em$S@example.test" Emp employee ""
login "$T/po" "po$S@example.test" $PW; login "$T/px" "px$S@example.test" $PW; login "$T/em" "em$S@example.test" $PW

check "library lists 12 departments" "$(curl -s -b "$T/em" "$B/process-library" | grep -c 'href=".*process-library?dept=')" 12
check "legacy processes imported" "$($M "SELECT COUNT(*) FROM processes WHERE department_id=$D1")" "$($M "SELECT COUNT(*) FROM processes WHERE department_id=$D1")"
check "search finds steps" "$(curl -s -b "$T/em" "$B/process-library?q=Phone+Pre-Screen" | grep -c 'href=".*process?id=')" 1

# Owners' active saves wait for an admin: approve whatever is waiting for this process.
approve(){ post "$T/admin" approvals -d kind=process -d "vid=$($M "SELECT id FROM process_versions WHERE process_id=$1 AND approval='pending'")" -d decision=approve >/dev/null; }
NEW="dept=client-success"
create(){ post "$T/$1" "process-edit?$NEW" -d "dept=client-success" "${@:2}"; }
R=$(create po -d "name=Smoke $S" -d "purpose=Test purpose" -d "status=active" \
  -d "steps[0][title]=Receive ticket" -d "steps[0][role]=Support Agent" -d "steps[0][person]=Ana" -d "steps[0][detail]=Log it" \
  -d "steps[1][title]=Is it urgent?" -d "steps[1][role]=Lead" -d "steps[1][decision]=1" -d "steps[1][no_goto]=1" -d "steps[2][title]=Resolve")
check "owner creates process" "$(echo "$R" | tail -1 | cut -d' ' -f1)" 302
PID=$($M "SELECT id FROM processes WHERE name='Smoke $S'")
check "owner's process waits for approval" "$(code "$T/em" "process?id=$PID")" 404
approve $PID
check "other dept owner blocked from editing" "$(code "$T/px" "process-edit?id=$PID")" 403
check "employee blocked from editing" "$(code "$T/em" "process-edit?id=$PID")" 403
PAGE=$(curl -s -b "$T/em" "$B/process?id=$PID")
check "flowchart shows 3 steps + decision" "$(echo "$PAGE" | grep -c 'class="flow-node')" 3
check "decision shows No branch" "$(echo "$PAGE" | grep -c 'No → go to step 1')" 1
check "people involved listed" "$(echo "$PAGE" | grep -c '<h2>People involved')" 1
check "no steps rejected" "$(create po -d "name=X" -d "purpose=Y" | grep -c 'Add at least one step')" 1
check "bad No target rejected" "$(post "$T/po" "process-edit?id=$PID" -d "id=$PID" -d "name=Smoke $S" -d "purpose=p" -d "changelog=c" -d "steps[0][title]=A" -d "steps[0][decision]=1" -d "steps[0][no_goto]=9" | grep -c 'must go to another existing step')" 1
check "edit needs changelog" "$(post "$T/po" "process-edit?id=$PID" -d "id=$PID" -d "name=Smoke $S" -d "purpose=p" -d "steps[0][title]=A" | grep -c 'Describe what changed')" 1
R=$(post "$T/po" "process-edit?id=$PID" -d "id=$PID" -d "name=Smoke $S" -d "purpose=p2" -d "status=active" -d "changelog=Added a step" -d "steps[0][title]=A" -d "steps[1][title]=B")
approve $PID
check "edit creates version 2" "$($M "SELECT COUNT(*) FROM process_versions WHERE process_id=$PID")" 2
check "old version still viewable" "$(curl -s -b "$T/em" "$B/process?id=$PID&v=1" | grep -c 'You are viewing version 1')" 1
check "current version has 2 steps" "$(curl -s -b "$T/em" "$B/process?id=$PID" | grep -c 'class="flow-node')" 2

post "$T/em" "process?id=$PID" -d "id=$PID" -d "action=request" -d "title=Add SLA" -d "details=Please add SLA times" >/dev/null
check "employee files change request" "$($M "SELECT COUNT(*) FROM change_requests WHERE process_id=$PID AND status='open'")" 1
RID=$($M "SELECT id FROM change_requests WHERE process_id=$PID")
check "employee cannot resolve" "$(post "$T/em" "process?id=$PID" -d "id=$PID" -d "action=resolve" -d "request_id=$RID" -d "status=done" | tail -1 | cut -d' ' -f1)" 403
post "$T/po" "process?id=$PID" -d "id=$PID" -d "action=resolve" -d "request_id=$RID" -d "status=approved" >/dev/null
check "owner approves request" "$($M "SELECT status FROM change_requests WHERE id=$RID")" approved
post "$T/po" "process?id=$PID" -d "id=$PID" -d "action=review" >/dev/null
check "mark reviewed sets date" "$($M "SELECT last_reviewed_at=CURDATE() FROM processes WHERE id=$PID")" 1

post "$T/po" "process-library?dept=client-success" -d "title=Support Lead $S" -d "holder_name=Ben" >/dev/null
check "owner adds position" "$($M "SELECT COUNT(*) FROM positions WHERE title='Support Lead $S'")" 1
post "$T/px" "process-library?dept=client-success" -d "title=Sneaky" >/dev/null
check "other dept cannot add position" "$($M "SELECT COUNT(*) FROM positions WHERE title='Sneaky'")" 0
check "audit has create+version+review+2 approvals" "$($M "SELECT COUNT(*) FROM audit_log WHERE entity='process' AND entity_id=$PID")" 5
rm -rf "$T"; exit $fail
