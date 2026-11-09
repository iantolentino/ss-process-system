<?php
// Usage (cron, daily): php /home/USER/app/backend/cron/clean_logs.php
// Deletes app-* and error-* log files older than LOG_KEEP_DAYS (default 90). audit-* files and the audit_log table are never deleted.
if (PHP_SAPI !== 'cli') exit('CLI only');
require __DIR__ . '/../bootstrap.php';

$keep = (int) env('LOG_KEEP_DAYS', '90');
$n = 0;
foreach (glob(ROOT . '/logs/{app,error}-*.log', GLOB_BRACE) as $f) {
    if (filemtime($f) < time() - $keep * 86400 && unlink($f)) $n++;
}
log_line('app', 'INFO', "log cleanup removed $n file(s)", ['keep_days' => $keep]);
echo "removed $n old log file(s)\n";
