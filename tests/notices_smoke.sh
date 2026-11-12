#!/usr/bin/env bash
# Smoke test for process "what changed" and email notices (LOCAL server, APP_ENV=local so mail goes to logs/app-*.log).
# Usage: tests/notices_smoke.sh http://localhost/process admin@example.test 'AdminPassword'
B=${1:?base url}; ADMIN=${2:?admin email}; APW=${3:?admin password}
S=$RANDOM; PW='SmokePass123'; T=$(mktemp -d); fail=0
M="/c/xampp/mysql/bin/mysql.exe -uroot ss_process -N -e"
LOG="$(dirname "$0")/../logs/app-$(/c/xampp/php/php.exe -r "echo date(\"Y-m-d\");").log"
check(){ [ "$2" = "$3" ] && echo "ok   $1" || { echo "FAIL $1 (got '$2', want '$3')"; fail=1; }; }
tok(){ curl -s -c "$1" -b "$1" "$B/$2" | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login(){ curl -s -c "$1" -b "$1" -o /dev/null -d "csrf=$(tok "$1" login)&email=$2&password=$3" "$B/login"; }
post(){ curl -s -c "$1" -b "$1" -w '\n%{http_code}' --data-urlencode "csrf=$(tok "$1" "$2")" "${@:3}" "$B/$2"; }

D1=$($M "SELECT id FROM departments WHERE slug='client-success'")
login "$T/admin" "$ADMIN" "$APW"
mk(){ post "$T/admin" users -d "first_name=T" -d "last_name=$2" -d "email=$1" -d "role=$3" -d "department_id=$D1" -d "password=$PW" -d "active=1" >/dev/null; }
mk "po$S@example.test" Owner process_owner; mk "em$S@example.test" Emp employee
login "$T/po" "po$S@example.test" $PW; login "$T/em" "em$S@example.test" $PW

# process v1, then v2 with one step added, one removed, one edited
post "$T/po" "process-edit?dept=client-success" -d dept=client-success -d "name=Notice $S" -d "purpose=p" -d status=active -d "steps[0][title]=Alpha $S" -d "steps[0][detail]=one" -d "steps[1][title]=Beta $S" -d "steps[1][detail]=two" >/dev/null
PID=$($M "SELECT id FROM processes WHERE name='Notice $S'")
# Owners' active saves wait for an admin: approve whatever is waiting for this process.
approve(){ post "$T/admin" approvals -d kind=process -d "vid=$($M "SELECT id FROM process_versions WHERE process_id=$PID AND approval='pending'")" -d decision=approve >/dev/null; }
approve
post "$T/po" "process-edit?id=$PID" -d "id=$PID" -d "name=Notice $S" -d "purpose=p" -d status=active -d "changelog=tweak" -d "steps[0][title]=Alpha $S" -d "steps[0][detail]=changed" -d "steps[1][title]=Gamma $S" -d "steps[1][detail]=new" >/dev/null
approve
V2=$(curl -s -b "$T/em" "$B/process?id=$PID&v=2&changes=1")
check "changes shows added step" "$(echo "$V2" | grep -c "+ Added step: Gamma $S")" 1
check "changes shows removed step" "$(echo "$V2" | grep -c "\- Removed step: Beta $S")" 1
check "changes shows edited step" "$(echo "$V2" | grep -c "Step &quot;Alpha $S&quot;: what they do changed")" 1
check "v1 has no changes link target" "$(curl -s -b "$T/em" "$B/process?id=$PID&v=1&changes=1" | grep -c 'What changed in version')" 0
check "history links to changes" "$(curl -s -b "$T/em" "$B/process?id=$PID" | grep -c "v=2&changes=1")" 1

# change request mails the department's process owner; resolving mails the requester
post "$T/em" "process?id=$PID" -d "id=$PID" -d action=request -d "title=Fix $S" -d "details=because" >/dev/null
check "owner notified of request" "$(grep -c "mail to po$S@example.test: Change request" "$LOG")" 1
RID=$($M "SELECT id FROM change_requests WHERE title='Fix $S'")
post "$T/po" "process?id=$PID" -d "id=$PID" -d action=resolve -d "request_id=$RID" -d status=approved >/dev/null
check "requester notified of result" "$(grep -c "mail to em$S@example.test: Your change request was approved" "$LOG")" 1

# review notices cron
$M "INSERT INTO documents (type,title,status,review_due) VALUES ('policy','Due $S','active',CURDATE() - INTERVAL 1 DAY)"
PAT="mail to $ADMIN: .* document(s) due for review"; N0=$(grep -c "$PAT" "$LOG")
OUT=$(/c/xampp/php/php.exe "$(dirname "$0")/../backend/cron/review_notices.php")
check "cron reports documents" "$(echo "$OUT" | grep -c 'document(s)')" 1
check "admin got review mail" "$(( $(grep -c "$PAT" "$LOG") - N0 ))" 1
$M "DELETE FROM documents WHERE title='Due $S'"
rm -rf "$T"; exit $fail
