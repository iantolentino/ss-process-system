#!/usr/bin/env bash
# Smoke test for memo fields, process<->document links and the department handbook (LOCAL server; creates test rows).
# Usage: tests/handbook_smoke.sh http://localhost/process admin@example.test 'AdminPassword'
B=${1:?base url}; ADMIN=${2:?admin email}; APW=${3:?admin password}
S=$RANDOM; PW='SmokePass123'; T=$(mktemp -d); fail=0
M="/c/xampp/mysql/bin/mysql.exe -uroot ss_process -N -e"
check(){ [ "$2" = "$3" ] && echo "ok   $1" || { echo "FAIL $1 (got '$2', want '$3')"; fail=1; }; }
tok(){ curl -s -c "$1" -b "$1" "$B/$2" | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login(){ curl -s -c "$1" -b "$1" -o /dev/null -d "csrf=$(tok "$1" login)&email=$2&password=$3" "$B/login"; }
post(){ curl -s -c "$1" -b "$1" -w '\n%{http_code}' --data-urlencode "csrf=$(tok "$1" "$2")" "${@:3}" "$B/$2"; }

DEPT=$($M "SELECT id FROM departments WHERE slug='client-success'")
login "$T/admin" "$ADMIN" "$APW"
curl -s -c "$T/admin" -b "$T/admin" -o /dev/null -d "csrf=$(tok "$T/admin" users)&first_name=H&last_name=Emp$S&email=he$S@example.test&role=employee&department_id=$DEPT&password=$PW&active=1" "$B/users"
curl -s -c "$T/admin" -b "$T/admin" -o /dev/null -d "csrf=$(tok "$T/admin" users)&first_name=N&last_name=Dept$S&email=nd$S@example.test&role=employee&password=$PW&active=1" "$B/users"
login "$T/emp" "he$S@example.test" $PW; login "$T/nod" "nd$S@example.test" $PW

# --- memo fields
printf 'memo' > "$T/m.txt"; printf 'pol' > "$T/p.txt"
mcode(){ curl -s -c "$T/admin" -b "$T/admin" -o /dev/null -w '%{http_code}' -F "csrf=$(tok "$T/admin" policies)" "$@" "$B/policies"; }
check "memo created with fields" "$(mcode -F type=memo -F "title=Memo $S" -F version_no=1 -F effective_date=2026-01-01 -F "memo_from=CEO" -F "audience=All staff" -F expires_on=2026-02-01 -F "file=@$T/m.txt")" 302
MID=$($M "SELECT id FROM documents WHERE title='Memo $S'")
check "memo fields stored" "$($M "SELECT CONCAT(memo_from,'|',audience,'|',expires_on) FROM documents WHERE id=$MID")" "CEO|All staff|2026-02-01"
check "memo shows expired + audience in list" "$(curl -s -b "$T/emp" "$B/policies?type=memo&q=Memo+$S" | grep -c 'expired 2026-02-01')" 1
check "memo page shows from/for" "$(curl -s -b "$T/emp" "$B/document?id=$MID" | grep -c 'from CEO')" 1
check "bad expiry rejected" "$(mcode -F type=memo -F "title=Bad $S" -F version_no=1 -F effective_date=2026-01-01 -F expires_on=nope -F "file=@$T/m.txt")" 200
check "no half-made document after bad expiry" "$($M "SELECT COUNT(*) FROM documents WHERE title='Bad $S'")" 0
mcode -F type=policy -F "title=Pol $S" -F version_no=1 -F effective_date=2026-01-01 -F "memo_from=ignored" -F "file=@$T/p.txt" >/dev/null
PDOC=$($M "SELECT id FROM documents WHERE title='Pol $S'")
check "memo fields ignored for non-memos" "$($M "SELECT memo_from IS NULL FROM documents WHERE id=$PDOC")" 1

# --- process <-> document link + handbook
post "$T/admin" "process-edit?dept=client-success" -d dept=client-success -d "name=Linked $S" -d "purpose=Has links" -d status=active \
  -d "docs[]=$PDOC" -d "docs[]=999999" -d "steps[0][title]=Do it" -d "steps[0][role]=Agent" -d "steps[1][title]=Check" -d "steps[1][decision]=1" >/dev/null
PID=$($M "SELECT id FROM processes WHERE name='Linked $S'")
check "process page links the policy" "$(curl -s -b "$T/emp" "$B/process?id=$PID" | grep -c "document?id=$PDOC")" 1
check "missing document id ignored" "$(curl -s -b "$T/emp" "$B/process?id=$PID" | grep -c 'document?id=999999')" 0
check "edit form preselects link" "$(curl -s -b "$T/admin" "$B/process-edit?id=$PID" | grep -c "value=\"$PDOC\" selected")" 1
curl -s -c "$T/admin" -b "$T/admin" -o /dev/null -d "csrf=$(tok "$T/admin" "document?id=$PDOC")&id=$PDOC&title=Pol+$S&status=archived" "$B/document"
check "archived policy no longer linked" "$(curl -s -b "$T/emp" "$B/process?id=$PID" | grep -c "document?id=$PDOC")" 0

HB=$(curl -s -b "$T/emp" "$B/handbook")
check "handbook defaults to own department" "$(echo "$HB" | grep -c 'Client Success handbook</h1>')" 1
check "handbook lists every process" "$(echo "$HB" | grep -c 'class="proc-block"')" "$($M "SELECT COUNT(*) FROM processes WHERE department_id=$DEPT AND status='active'")"
check "handbook includes our process + decision" "$(echo "$HB" | grep -c "Linked $S")" 2
check "other department via ?dept=" "$(curl -s -b "$T/emp" "$B/handbook?dept=information-technology" | grep -c 'Information Technology handbook</h1>')" 1
check "user without department is sent to library" "$(curl -s -b "$T/nod" -o /dev/null -w '%{http_code} %{redirect_url}' "$B/handbook")" "302 $B/process-library"
check "unknown department 404" "$(curl -s -b "$T/emp" -o /dev/null -w '%{http_code}' "$B/handbook?dept=nope")" 404
check "dashboard shows start-here card for dept users" "$(curl -s -b "$T/emp" "$B/dashboard" | grep -c 'New here? Start here')" 1
check "dashboard hides it without department" "$(curl -s -b "$T/nod" "$B/dashboard" | grep -c 'New here? Start here')" 0
rm -rf "$T"; exit $fail
