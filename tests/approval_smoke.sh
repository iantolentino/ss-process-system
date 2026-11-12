#!/usr/bin/env bash
# Smoke test for approval before publishing, read-and-confirm, owner change, PDF view and the one search box.
# Runs against a LOCAL server (creates test rows!).
# Usage: tests/approval_smoke.sh http://localhost/process admin@example.test 'AdminPassword'
B=${1:?base url}; ADMIN=${2:?admin email}; APW=${3:?admin password}
S=$RANDOM; PW='SmokePass123'; T=$(mktemp -d); fail=0
q(){ /c/xampp/mysql/bin/mysql.exe -uroot ss_process -N -e "$1" | tr -d '\r'; }
check(){ [ "$2" = "$3" ] && echo "ok   $1" || { echo "FAIL $1 (got '$2', want '$3')"; fail=1; }; }
tok(){ curl -s -c "$1" -b "$1" "$B/$2" | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login(){ curl -s -c "$1" -b "$1" -o /dev/null -d "csrf=$(tok "$1" login)&email=$2&password=$3" "$B/login"; }
code(){ curl -s -c "$T/$1" -b "$T/$1" -o /dev/null -w '%{http_code}' "$B/$2"; }
# usage: post <who> <path> -d a=b ...   (prints the body, then "<status> <redirect>")
post(){ curl -s -c "$T/$1" -b "$T/$1" -w '\n%{http_code} %{redirect_url}' --data-urlencode "csrf=$(tok "$T/$1" dashboard)" "${@:3}" "$B/$2"; }
up(){ curl -s -c "$T/$1" -b "$T/$1" -w '\n%{http_code} %{redirect_url}' -F "csrf=$(tok "$T/$1" dashboard)" "${@:3}" "$B/$2"; }
decide(){ post "$1" approvals -d "kind=$2" -d "vid=$3" -d "decision=$4" -d "note=$5"; }

D1=$(q "SELECT id FROM departments WHERE slug='client-success'")
login "$T/admin" "$ADMIN" "$APW"
mk(){ post admin users -d "first_name=T" -d "last_name=$2" -d "email=$1" -d "role=$3" -d "department_id=$4" -d "password=$PW" -d "active=1" >/dev/null; }
mk "pol$S@example.test" Policy policy_owner ""; mk "po$S@example.test" Owner process_owner "$D1"; mk "em$S@example.test" Emp employee ""
login "$T/pol" "pol$S@example.test" $PW; login "$T/po" "po$S@example.test" $PW; login "$T/em" "em$S@example.test" $PW
printf '%%PDF-1.4\n%%%%EOF\n' > "$T/a.pdf"; printf 'v2 text' > "$T/b.txt"

# --- documents: a policy owner's upload waits for an admin
R=$(up pol policies -F type=policy -F "title=Appr Policy $S" -F version_no=1.0 -F effective_date=2026-01-01 -F ack_required=1 -F "file=@$T/a.pdf")
DOC=$(echo "$R" | tail -1 | grep -o 'id=[0-9]*' | cut -d= -f2)
V1=$(q "SELECT id FROM document_versions WHERE document_id=$DOC")
st(){ q "SELECT CONCAT(d.status,'/',v.approval) FROM documents d JOIN document_versions v ON v.id=d.current_version_id WHERE d.id=$DOC"; }
check "owner's new document waits as a draft" "$(st)" "draft/pending"
check "employee cannot see the waiting document" "$(code em "document?id=$DOC")" 404
check "employee cannot download the waiting file" "$(code em "download?v=$V1")" 404
check "owner cannot make it active without approval" "$(post pol "document?id=$DOC" -d "id=$DOC" -d "title=Appr Policy $S" -d status=active | grep -c 'until an admin approves')" 1
check "owner is kept out of the approvals page" "$(code pol approvals)" 403
check "owner cannot approve" "$(decide pol document "$V1" approve '' | tail -1 | cut -d' ' -f1)" 403
check "admin sees it on the approvals page" "$(curl -s -b "$T/admin" "$B/approvals" | grep -c "Appr Policy $S")" 1
check "admin was emailed (local: logged)" "$(grep -c 'Waiting for your approval: document version 1.0' "logs/app-$(/c/xampp/php/php.exe -r 'echo date("Y-m-d");').log" | sed 's/^[1-9][0-9]*$/yes/')" yes
check "reject needs a reason" "$(decide admin document "$V1" reject '' | grep -c 'Say why it is rejected')" 1
decide admin document "$V1" approve '' >/dev/null
check "approved: active and visible to staff" "$(st)/$(code em "document?id=$DOC")" "active/approved/200"

up pol "document?id=$DOC" -F "id=$DOC" -F action=version -F version_no=2.0 -F effective_date=2026-06-01 -F changelog=Second -F "file=@$T/b.txt" >/dev/null
V2=$(q "SELECT id FROM document_versions WHERE document_id=$DOC AND version_no='2.0'")
check "new version waits: staff keep version 1" "$(q "SELECT current_version_id FROM documents WHERE id=$DOC")" "$V1"
check "employee does not see the waiting version" "$(curl -s -b "$T/em" "$B/document?id=$DOC" | grep -c "download?v=$V2")" 0
check "second upload blocked while one waits" "$(up pol "document?id=$DOC" -F "id=$DOC" -F action=version -F version_no=3.0 -F effective_date=2026-06-01 -F changelog=Third -F "file=@$T/b.txt" | grep -c 'already waiting for approval')" 1
decide admin document "$V2" reject 'Wrong file' >/dev/null
check "rejected: version 1 stays current" "$(q "SELECT CONCAT(d.current_version_id,'/',v.approval) FROM documents d JOIN document_versions v ON v.id=$V2 WHERE d.id=$DOC")" "$V1/rejected"
check "both decisions are in the audit trail" "$(q "SELECT COUNT(*) FROM audit_log WHERE entity='document' AND entity_id=$DOC AND action IN ('approved','rejected')")" 2

# --- read and confirm
check "employee is asked on Home" "$(curl -s -b "$T/em" "$B/dashboard" | grep -c "Appr Policy $S")" 1
post em "document?id=$DOC" -d "id=$DOC" -d action=ack >/dev/null; post em "document?id=$DOC" -d "id=$DOC" -d action=ack >/dev/null
check "confirmation stored once and audited once" "$(q "SELECT CONCAT((SELECT COUNT(*) FROM acknowledgements WHERE version_id=$V1),'/',(SELECT COUNT(*) FROM audit_log WHERE entity='document' AND entity_id=$DOC AND action='acknowledged'))")" "1/1"
check "no longer asked on Home" "$(curl -s -b "$T/em" "$B/dashboard" | grep -c "Appr Policy $S")" 0
check "editor sees who has confirmed" "$(curl -s -b "$T/pol" "$B/document?id=$DOC" | grep -c 'people have confirmed')" 1
check "employee does not see that list" "$(curl -s -b "$T/em" "$B/document?id=$DOC" | grep -c 'people have confirmed')" 0

# --- owner change (admin only)
ADM=$(q "SELECT id FROM users WHERE email='$ADMIN'"); POL=$(q "SELECT id FROM users WHERE email='pol$S@example.test'")
post pol "document?id=$DOC" -d "id=$DOC" -d "title=Appr Policy $S" -d status=active -d ack_required=1 -d "owner_id=$ADM" >/dev/null
check "policy owner cannot change the owner" "$(q "SELECT owner_id FROM documents WHERE id=$DOC")" "$POL"
post admin "document?id=$DOC" -d "id=$DOC" -d "title=Appr Policy $S" -d status=active -d ack_required=1 -d "owner_id=$ADM" >/dev/null
check "admin changes the owner" "$(q "SELECT owner_id FROM documents WHERE id=$DOC")" "$ADM"

# --- PDF in the browser
check "pdf opens in the browser" "$(curl -s -b "$T/em" -D - -o /dev/null "$B/download?v=$V1&inline=1" | grep -ci 'content-disposition: inline\|content-type: application/pdf')" 2
check "other file types are always a download" "$(curl -s -b "$T/admin" -D - -o /dev/null "$B/download?v=$V2&inline=1" | grep -ci 'content-disposition: attachment')" 1

# --- processes: a process owner's active save waits for an admin
steps=(-d "steps[0][title]=First step" -d "steps[0][detail]=Do it")
post po "process-edit?dept=client-success" -d dept=client-success -d "name=Appr Proc $S" -d "purpose=Test" -d status=active "${steps[@]}" >/dev/null
PID=$(q "SELECT id FROM processes WHERE name='Appr Proc $S'")
ps(){ q "SELECT CONCAT(p.name,'/',p.status,'/',v.version_no,'/',v.approval) FROM processes p JOIN process_versions v ON v.id=p.current_version_id WHERE p.id=$PID"; }
pv(){ q "SELECT id FROM process_versions WHERE process_id=$PID AND version_no=$1"; }
check "owner's new process waits as a draft" "$(ps)" "Appr Proc $S/draft/1/pending"
check "employee cannot see the waiting process" "$(code em "process?id=$PID")" 404
decide admin process "$(pv 1)" approve '' >/dev/null
check "approved: active and visible to staff" "$(ps)/$(code em "process?id=$PID")" "Appr Proc $S/active/1/approved/200"
post po "process-edit?id=$PID" -d "id=$PID" -d dept=client-success -d "name=Changed $S" -d "purpose=Test" -d status=active -d changelog=Renamed "${steps[@]}" -d "steps[1][title]=Second step" >/dev/null
check "edit waits: staff keep the approved version" "$(ps)" "Appr Proc $S/active/1/approved"
check "employee cannot open the waiting version" "$(code em "process?id=$PID&v=2")" 404
check "admin sees what would change" "$(curl -s -b "$T/admin" "$B/process?id=$PID&v=2&changes=1" | grep -c 'Added step: Second step')" 1
check "second edit blocked while one waits" "$(post po "process-edit?id=$PID" -d "id=$PID" -d dept=client-success -d "name=Again $S" -d "purpose=Test" -d status=active -d changelog=Again "${steps[@]}" | grep -c 'already waiting for approval')" 1
decide admin process "$(pv 2)" approve '' >/dev/null
check "approved edit is applied" "$(ps)" "Changed $S/active/2/approved"

# --- one search box (while both are live)
check "search finds the document and the process" "$(curl -s -b "$T/em" "$B/search?q=$S" | grep -c "Appr Policy $S\|Changed $S")" 2
up pol policies -F type=policy -F "title=Hidden $S" -F version_no=1.0 -F effective_date=2026-01-01 -F "file=@$T/a.pdf" >/dev/null
check "search hides what staff may not see" "$(curl -s -b "$T/em" "$B/search?q=Hidden+$S" | grep -c 'document?id=')" 0
check "search needs sign-in" "$(curl -s -o /dev/null -w '%{http_code}' "$B/search?q=x")" 302

# --- drafts need no approval; restoring as an owner gives a draft
post po "process-edit?id=$PID" -d "id=$PID" -d dept=client-success -d "name=Changed $S" -d "purpose=Test" -d status=draft -d changelog=Hide "${steps[@]}" >/dev/null
check "owner's draft save is direct" "$(ps)" "Changed $S/draft/3/approved"
post po "process?id=$PID" -d "id=$PID" -d action=archive >/dev/null; post po "process?id=$PID" -d "id=$PID" -d action=restore >/dev/null
check "owner restore gives a draft, not a live process" "$(q "SELECT status FROM processes WHERE id=$PID")" draft
rm -rf "$T"; exit $fail
