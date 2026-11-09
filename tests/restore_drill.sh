#!/usr/bin/env bash
# Restore drill (LOCAL): runs a backup, restores it into a scratch database and compares with the live one. Touches nothing live.
# Usage: bash tests/restore_drill.sh
cd "$(dirname "$0")/.." || exit 1
PHP=/c/xampp/php/php.exe; MY="/c/xampp/mysql/bin/mysql.exe -uroot"; SCR=ss_restore_test; fail=0
check(){ [ "$2" = "$3" ] && echo "ok   $1" || { echo "FAIL $1 (got '$2', want '$3')"; fail=1; }; }

$PHP backend/cron/backup.php || { echo "FAIL backup script"; exit 1; }
DB=database/backups/db-$(date +%F).sql.gz; FILES=database/backups/files-$(date +%F).tar.gz
check "db backup exists and is not empty" "$([ -s "$DB" ] && echo yes)" yes

$MY -e "DROP DATABASE IF EXISTS $SCR; CREATE DATABASE $SCR CHARACTER SET utf8mb4"
gunzip -c "$DB" | $MY $SCR
T=$($MY -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema='ss_process' ORDER BY 1")
for t in $T; do
  a=$($MY -N -e "CHECKSUM TABLE ss_process.$t" | cut -f2); b=$($MY -N -e "CHECKSUM TABLE $SCR.$t" | cut -f2)
  check "table $t identical" "$b" "$a"
done
check "audit_log rows restored" "$($MY -N -e "SELECT COUNT(*) FROM $SCR.audit_log")" "$($MY -N -e "SELECT COUNT(*) FROM ss_process.audit_log")"

# files: restore into a temp dir and verify every stored version against the sha256 recorded in the database
if [ -f "$FILES" ]; then
  D=$(mktemp -d); tar -xzf "$FILES" -C "$D"
  bad=0; n=0
  while IFS=$'\t' read -r path sha; do
    n=$((n+1)); rel=${path#files/uploads/}
    sha=${sha//[[:cntrl:]]/}   # Windows mysql.exe ends lines with CR
    [ "$(sha256sum "$D/$rel" 2>/dev/null | cut -d' ' -f1)" = "$sha" ] || { bad=$((bad+1)); echo "  mismatch: $path"; }
  done < <($MY -N -e "SELECT file_path, sha256 FROM $SCR.document_versions" | tr -d '')
  check "all $n restored files match their recorded sha256" "$bad" 0
  rm -rf "$D"
fi
$MY -e "DROP DATABASE $SCR"
exit $fail
