#!/usr/bin/env bash
# Smoke test for users + Policy Portal against a LOCAL server (creates test rows!).
# Usage: tests/portal_smoke.sh http://localhost/process admin@example.test 'AdminPassword'
B=${1:?base url}; ADMIN=${2:?admin email}; APW=${3:?admin password}
S=$RANDOM; EMP="emp$S@example.test"; OWN="own$S@example.test"; PW='SmokePass123'
T=$(mktemp -d); fail=0
check(){ [ "$2" = "$3" ] && echo "ok   $1" || { echo "FAIL $1 (got '$2', want '$3')"; fail=1; }; }
# usage: tok <jar> <path>
tok(){ curl -s -c "$1" -b "$1" "$B/$2" | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login(){ curl -s -c "$1" -b "$1" -o /dev/null -d "csrf=$(tok "$1" login)&email=$2&password=$3" "$B/login"; }
code(){ curl -s -c "$1" -b "$1" -o /dev/null -w '%{http_code}' "${@:3}" "$B/$2"; }

printf 'v1 text' > "$T/a.txt"; printf 'v2 text' > "$T/b.txt"; printf 'MZ' > "$T/bad.exe"; printf '<html><script>alert(1)</script></html>' > "$T/fake.pdf"
login "$T/admin" "$ADMIN" "$APW"

# --- users
for pair in "$EMP:employee" "$OWN:policy_owner"; do
  curl -s -c "$T/admin" -b "$T/admin" -o /dev/null -d "csrf=$(tok "$T/admin" 'users?new=1')&first_name=T&last_name=User&email=${pair%%:*}&role=${pair##*:}&password=$PW&active=1" "$B/users"
done
check "new users listed" "$(curl -s -b "$T/admin" "$B/users?q=$EMP" | grep -c "<td>$EMP</td>")$(curl -s -b "$T/admin" "$B/users?q=$OWN" | grep -c "<td>$OWN</td>")" 11
check "duplicate email rejected" "$(curl -s -c "$T/admin" -b "$T/admin" -d "csrf=$(tok "$T/admin" users)&first_name=T&last_name=U&email=$EMP&role=employee&password=$PW" "$B/users" | grep -c 'already used')" 1
login "$T/emp" "$EMP" "$PW"; login "$T/own" "$OWN" "$PW"
check "employee blocked from /users" "$(code "$T/emp" users)" 403

# --- documents
up(){ curl -s -c "$1" -b "$1" -o /dev/null -w '%{http_code} %{redirect_url}' -F "csrf=$(tok "$1" policies)" "${@:2}"; }
R=$(up "$T/own" -F type=policy -F "title=Smoke Policy $S" -F category=Test -F version_no=1.0 -F effective_date=2026-01-01 -F review_due=2020-01-01 -F "file=@$T/a.txt" "$B/policies")
check "owner creates document" "${R%% *}" 302
DOC=$(echo "$R" | grep -o 'id=[0-9]*' | cut -d= -f2)
# A policy owner's upload waits for an admin: approve whatever is waiting for this document.
approve(){ curl -s -c "$T/admin" -b "$T/admin" -o /dev/null -d "csrf=$(tok "$T/admin" approvals)&kind=document&decision=approve&vid=$(/c/xampp/mysql/bin/mysql.exe -uroot ss_process -N -e "SELECT id FROM document_versions WHERE document_id=$DOC AND approval='pending'")" "$B/approvals"; }
check "owner's document waits for approval" "$(code "$T/emp" "document?id=$DOC")" 404
approve
check "employee cannot create" "$(up "$T/emp" -F type=policy -F title=x -F version_no=1 -F effective_date=2026-01-01 -F "file=@$T/a.txt" "$B/policies" | cut -d' ' -f1)" 403
check "web page renamed .pdf rejected" "$(curl -s -c "$T/own" -b "$T/own" -F "csrf=$(tok "$T/own" policies)" -F type=policy -F title=Fake -F version_no=1 -F effective_date=2026-01-01 -F "file=@$T/fake.pdf" "$B/policies" | grep -c 'does not look like a real')" 1
check "bad file type rejected" "$(curl -s -c "$T/own" -b "$T/own" -F "csrf=$(tok "$T/own" policies)" -F type=policy -F title=Bad -F version_no=1 -F effective_date=2026-01-01 -F "file=@$T/bad.exe" "$B/policies" | grep -c 'Allowed file types')" 1
check "new version" "$(curl -s -c "$T/own" -b "$T/own" -o /dev/null -w '%{http_code}' -F "csrf=$(tok "$T/own" "document?id=$DOC")" -F id=$DOC -F action=version -F version_no=2.0 -F effective_date=2026-06-01 -F "changelog=Updated scope" -F "file=@$T/b.txt" "$B/document")" 302
approve
check "duplicate version rejected" "$(curl -s -c "$T/own" -b "$T/own" -F "csrf=$(tok "$T/own" "document?id=$DOC")" -F id=$DOC -F action=version -F version_no=2.0 -F effective_date=2026-06-01 -F changelog=x -F "file=@$T/b.txt" "$B/document" | grep -c 'already exists')" 1
PAGE=$(curl -s -b "$T/emp" "$B/document?id=$DOC")
check "employee sees both versions" "$(echo "$PAGE" | grep -c 'Updated scope\|Initial version')" 2
check "employee sees no edit forms" "$(echo "$PAGE" | grep -c 'Upload a new version')" 0
V=$(echo "$PAGE" | grep -o 'download?v=[0-9]*' | head -1 | cut -d= -f2)
CMP=$(curl -s -b "$T/emp" "$B/document?id=$DOC&compare=$V")
check "compare shows removed+added lines" "$(echo "$CMP" | grep -o "dadd\">+ v2 text\|ddel\">- v1 text" | wc -l | tr -d " ")" 2
check "no compare for oldest version" "$(curl -s -b "$T/emp" "$B/document?id=$DOC&compare=0" | grep -c "id=.compare.")" 0
check "download works" "$(curl -s -b "$T/emp" "$B/download?v=$V")" "v2 text"
check "uploads folder not web-readable" "$(curl -s -o /dev/null -w '%{http_code}' "$B/files/uploads/$DOC/x.txt")" 403
check "employee cannot edit" "$(curl -s -c "$T/emp" -b "$T/emp" -o /dev/null -w '%{http_code}' -d "csrf=$(tok "$T/emp" "document?id=$DOC")&id=$DOC&title=Hacked&status=active" "$B/document")" 403
curl -s -c "$T/own" -b "$T/own" -o /dev/null -d "csrf=$(tok "$T/own" "document?id=$DOC")&id=$DOC&title=Smoke+Policy+$S&status=archived" "$B/document"
check "archived hidden from employee" "$(code "$T/emp" "document?id=$DOC")" 404
check "audit trail recorded (incl. download, 2 approvals)" "$(/c/xampp/mysql/bin/mysql.exe -uroot ss_process -N -e "SELECT COUNT(*) FROM audit_log WHERE entity='document' AND entity_id=$DOC")" 7
rm -rf "$T"; exit $fail
