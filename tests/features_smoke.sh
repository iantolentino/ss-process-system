#!/usr/bin/env bash
# Smoke test for onboarding checklists, glossary, user positions and process templates (LOCAL server; creates test rows).
# Usage: tests/features_smoke.sh http://localhost/process admin@example.test 'AdminPassword'
B=${1:?base url}; ADMIN=${2:?admin email}; APW=${3:?admin password}
S=$RANDOM; PW='SmokePass123'; T=$(mktemp -d); fail=0
M="/c/xampp/mysql/bin/mysql.exe -uroot ss_process -N -e"
check(){ [ "$2" = "$3" ] && echo "ok   $1" || { echo "FAIL $1 (got '$2', want '$3')"; fail=1; }; }
tok(){ curl -s -c "$1" -b "$1" "$B/$2" | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login(){ curl -s -c "$1" -b "$1" -o /dev/null -d "csrf=$(tok "$1" login)&email=$2&password=$3" "$B/login"; }
post(){ curl -s -c "$1" -b "$1" -w '\n%{http_code}' --data-urlencode "csrf=$(tok "$1" "$2")" "${@:3}" "$B/$2"; }
status(){ tail -1; }

D1=$($M "SELECT id FROM departments WHERE slug='client-success'"); D2=$($M "SELECT id FROM departments WHERE slug='information-technology'")
login "$T/admin" "$ADMIN" "$APW"
post "$T/admin" "process-library?dept=client-success" -d "title=Agent $S" -d "holder_name=Ana" >/dev/null
post "$T/admin" "process-library?dept=client-success" -d "title=Lead $S" -d "holder_name=Ben" >/dev/null
post "$T/admin" "process-library?dept=information-technology" -d "title=Admin $S" >/dev/null
PA=$($M "SELECT id FROM positions WHERE title='Agent $S'"); PL=$($M "SELECT id FROM positions WHERE title='Lead $S'"); PIT=$($M "SELECT id FROM positions WHERE title='Admin $S'")
mk(){ post "$T/admin" users -d "first_name=T" -d "last_name=$2" -d "email=$1" -d "role=$3" -d "department_id=$4" -d "position_id=$5" -d "password=$PW" -d "active=1"; }
mk "ag$S@example.test" Agent employee "$D1" "$PA" >/dev/null
mk "ld$S@example.test" Lead employee "$D1" "$PL" >/dev/null
mk "po$S@example.test" Owner process_owner "$D1" "" >/dev/null
mk "it$S@example.test" It employee "$D2" "" >/dev/null
check "position from another department rejected" "$(mk "bad$S@example.test" Bad employee "$D1" "$PIT" | grep -c 'must belong to the user')" 1
check "position stored on user" "$($M "SELECT position_id FROM users WHERE email='ag$S@example.test'")" "$PA"
for u in ag ld po it; do login "$T/$u" "$u$S@example.test" $PW; done

# --- onboarding
add(){ post "$T/$1" "onboarding?dept=client-success" -d action=add "${@:2}"; }
add po -d "title=Welcome for all $S" -d week_no=1 >/dev/null
add po -d "title=Agent only $S" -d week_no=2 -d "position_id=$PA" >/dev/null
add po -d "title=Lead only $S" -d week_no=2 -d "position_id=$PL" >/dev/null
add po -d "title=Ongoing $S" -d week_no=0 >/dev/null
check "owner cannot add to another dept" "$(post "$T/po" "onboarding?dept=information-technology" -d action=add -d "title=x $S" | status)" 403
check "employee cannot add items" "$(add ag -d "title=nope $S" | status)" 403
check "position from wrong dept rejected" "$(add po -d "title=w $S" -d "position_id=$PIT" | grep -c 'not in this department')" 1
AG=$(curl -s -b "$T/ag" "$B/onboarding"); LD=$(curl -s -b "$T/ld" "$B/onboarding")
check "agent sees everyone + own item" "$(echo "$AG" | grep -c "Welcome for all $S\|Agent only $S")" 2
check "agent does not see lead item" "$(echo "$AG" | grep -c "Lead only $S")" 0
check "lead sees lead item, not agent item" "$(echo "$LD" | grep -c "Lead only $S")$(echo "$LD" | grep -c "Agent only $S")" 10
check "ongoing shown last" "$(echo "$AG" | grep -n 'Ongoing</h2>\|Week 1</h2>' | tail -1 | grep -c Ongoing)" 1
I1=$($M "SELECT id FROM onboarding_items WHERE title='Welcome for all $S'"); IL=$($M "SELECT id FROM onboarding_items WHERE title='Lead only $S'")
post "$T/ag" onboarding -d action=toggle -d "item_id=$I1" -d done=1 >/dev/null
check "ticking saves progress" "$($M "SELECT COUNT(*) FROM onboarding_progress WHERE item_id=$I1")" 1
NA=$($M "SELECT COUNT(*) FROM onboarding_items WHERE department_id=$D1 AND (position_id IS NULL OR position_id=$PA)")   # earlier runs leave items behind
check "progress bar updates" "$(curl -s -b "$T/ag" "$B/onboarding" | grep -c "1 of $NA done")" 1
check "cannot tick another position's item" "$(post "$T/ag" onboarding -d action=toggle -d "item_id=$IL" -d done=1 | grep -c 'not on your checklist')" 1
check "other-dept user cannot tick" "$(post "$T/it" onboarding -d action=toggle -d "item_id=$I1" -d done=1 | grep -c 'not on your checklist')" 1
check "manager sees team progress" "$(curl -s -b "$T/po" "$B/onboarding" | grep -c "<td>1 / $NA</td>")" 1
check "removing a used position is blocked" "$(post "$T/admin" "process-library?dept=client-success" -d action=remove_position -d "id=$PA" | grep -c 'checklist items still use')" 1
post "$T/po" onboarding -d action=delete -d "item_id=$I1" >/dev/null
check "deleting item removes its progress" "$($M "SELECT COUNT(*) FROM onboarding_progress WHERE item_id=$I1")" 0

# --- glossary
check "editor adds term" "$(post "$T/po" glossary -d "term=SLA$S" -d "definition=Service level agreement" | status)" 302
check "duplicate term rejected" "$(post "$T/po" glossary -d "term=SLA$S" -d "definition=x" | grep -c 'already in the glossary')" 1
check "employee cannot add term" "$(post "$T/ag" glossary -d "term=Nope$S" -d "definition=x" | status)" 403
post "$T/po" glossary -d "term=CSOnly$S" -d "definition=Client success only" -d "department_id=$D1" >/dev/null
check "glossary search" "$(curl -s -b "$T/ag" "$B/glossary?q=SLA$S" | grep -c "Service level agreement")" 1
check "handbook has company-wide term" "$(curl -s -b "$T/ag" "$B/handbook" | grep -c "SLA$S")" 1
check "handbook has own-dept term" "$(curl -s -b "$T/ag" "$B/handbook" | grep -c "CSOnly$S")" 1
check "other dept handbook lacks it" "$(curl -s -b "$T/ag" "$B/handbook?dept=information-technology" | grep -c "CSOnly$S")" 0
TID=$($M "SELECT id FROM glossary WHERE term='SLA$S'")
post "$T/po" glossary -d action=delete -d "id=$TID" >/dev/null
check "term deleted + audited" "$($M "SELECT COUNT(*) FROM audit_log WHERE entity='glossary' AND entity_id=$TID")" 2

# --- process template
SRC=$($M "SELECT id FROM processes WHERE department_id=$D1 AND status='active' ORDER BY id LIMIT 1")
SRCN=$($M "SELECT name FROM processes WHERE id=$SRC")
FORM=$(curl -s -b "$T/po" "$B/process-edit?dept=client-success&copy=$SRC")
SRCH=${SRCN//&/&amp;}   # names are HTML-escaped on the page
check "template prefills name" "$(echo "$FORM" | grep -c "value=\"Copy of $SRCH\"")" 1
check "template notes its source" "$(echo "$FORM" | grep -c 'Copied from')" 1
check "template starts as draft" "$(echo "$FORM" | grep -c '<option selected>draft')" 1
check "template list offered" "$(echo "$FORM" | grep -c 'Use as template')" 1
check "edit existing has no template picker" "$(curl -s -b "$T/po" "$B/process-edit?id=$SRC" | grep -c 'Use as template')" 0
rm -rf "$T"; exit $fail
