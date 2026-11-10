#!/usr/bin/env bash
# Smoke test for paging with many rows (LOCAL server; inserts and removes temporary rows) and the demo accounts.
# Usage: tests/scale_smoke.sh http://localhost/process admin@example.test 'AdminPassword'
B=${1:?base url}; ADMIN=${2:?admin email}; APW=${3:?admin password}
S=$RANDOM; T=$(mktemp -d); fail=0
M="/c/xampp/mysql/bin/mysql.exe -uroot ss_process -N -e"
check(){ [ "$2" = "$3" ] && echo "ok   $1" || { echo "FAIL $1 (got '$2', want '$3')"; fail=1; }; }
tok(){ curl -s -c "$1" -b "$1" "$B/$2" | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login(){ curl -s -c "$1" -b "$1" -o /dev/null -d "csrf=$(tok "$1" login)&email=$2&password=$3" "$B/login"; }
login "$T/admin" "$ADMIN" "$APW"

# 120 policies "Bulk $S nnn" and 120 users "bulk$S-n@example.test"
$M "INSERT INTO documents (type,title,status) SELECT 'policy', CONCAT('Bulk $S ', LPAD(seq,3,'0')), 'active' FROM seq_1_to_120" 2>/dev/null \
  || $M "INSERT INTO documents (type,title,status) SELECT 'policy', CONCAT('Bulk $S ', LPAD(a.n*10+b.n,3,'0')), 'active' FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11) a, (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b"
N=$($M "SELECT COUNT(*) FROM documents WHERE title LIKE 'Bulk $S %'")
row(){ grep -c "Bulk $S " ; }
P1=$(curl -s -b "$T/admin" "$B/policies?q=Bulk+$S" | row); P2=$(curl -s -b "$T/admin" "$B/policies?q=Bulk+$S&p=2" | row); P3=$(curl -s -b "$T/admin" "$B/policies?q=Bulk+$S&p=3" | row)
check "page 1 shows 50" "$P1" 50
check "page 2 shows 50" "$P2" 50
check "page 3 shows the last 20" "$P3" 20
check "inserted 120 rows" "$N" 120
check "next link on page 1" "$(curl -s -b "$T/admin" "$B/policies?q=Bulk+$S" | grep -c 'Next →')" 1
check "no next link on last page" "$(curl -s -b "$T/admin" "$B/policies?q=Bulk+$S&p=$(( (N + 49) / 50 ))" | grep -c 'Next →')" 0
$M "DELETE FROM documents WHERE title LIKE 'Bulk $S %'"

# users: search + edit by id works for anyone, even past page 1
UID1=$($M "SELECT id FROM users ORDER BY id DESC LIMIT 1")
check "users search finds admin" "$(curl -s -b "$T/admin" "$B/users?q=$ADMIN" | grep -c "<td>$ADMIN</td>")" 1
check "edit form opens by id" "$(curl -s -b "$T/admin" "$B/users?edit=$UID1" | grep -c '<h2>Edit person</h2>')" 1
rm -rf "$T"; exit $fail
