<?php
// Usage (cron, daily): php /home/USER/app/backend/cron/backup.php
// Writes database/backups/db-DATE.sql.gz and files-DATE.tar.gz, then deletes backups older than BACKUP_KEEP_DAYS (default 30).
// shortcut: backups stay on this server; download them or rely on cPanel/host backups for an off-site copy.
if (PHP_SAPI !== 'cli') exit('CLI only');
require __DIR__ . '/../bootstrap.php';

$dir = ROOT . '/database/backups';
$stamp = date('Y-m-d');
$keep = (int) env('BACKUP_KEEP_DAYS', '30');
if (!is_dir($dir)) mkdir($dir, 0750, true);

// 1. Database. The password goes through MYSQL_PWD so it never shows in the process list.
$sql = "$dir/db-$stamp.sql";
putenv('MYSQL_PWD=' . env('DB_PASS'));
exec(sprintf('%s --host=%s --user=%s --single-transaction --routines --result-file=%s %s 2>&1',
    escapeshellarg(env('MYSQLDUMP', 'mysqldump')), escapeshellarg(env('DB_HOST', 'localhost')),
    escapeshellarg(env('DB_USER')), escapeshellarg($sql), escapeshellarg(env('DB_NAME'))), $out, $rc);
putenv('MYSQL_PWD');
if ($rc !== 0 || !is_file($sql) || filesize($sql) === 0) {
    @unlink($sql);
    log_line('error', 'ERROR', 'backup: mysqldump failed', ['rc' => $rc, 'out' => implode(' ', array_slice($out, 0, 3))]);
    fwrite(STDERR, "database backup FAILED (see logs/error-$stamp.log)\n");
    exit(1);   // non-zero so cron can email the failure
}
$in = fopen($sql, 'rb');
$gz = gzopen("$sql.gz", 'wb9');
while (!feof($in)) gzwrite($gz, fread($in, 1 << 20));
fclose($in); gzclose($gz); unlink($sql);

// 2. Uploaded files (skipped when there are none).
$up = ROOT . '/files/uploads';
if (count(glob("$up/*")) > 0) {
    @unlink("$dir/files-$stamp.tar.gz"); @unlink("$dir/files-$stamp.tar");
    $tar = new PharData("$dir/files-$stamp.tar");
    $tar->buildFromDirectory($up);
    $tar->compress(Phar::GZ);
    unlink("$dir/files-$stamp.tar");
}

// 3. Prune.
foreach (glob("$dir/{db,files}-*.gz", GLOB_BRACE) as $f) {
    if (filemtime($f) < time() - $keep * 86400) unlink($f);
}
log_line('app', 'INFO', 'backup ok', ['db_bytes' => filesize("$sql.gz")]);
echo "backup ok: db-$stamp.sql.gz" . (is_file("$dir/files-$stamp.tar.gz") ? " + files-$stamp.tar.gz" : '') . "\n";
