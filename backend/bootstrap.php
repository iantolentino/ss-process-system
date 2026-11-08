<?php
// Shared by every entry point: env, DB handle, log files, audit trail.
define('ROOT', dirname(__DIR__));

function env(string $key, string $default = ''): string {
    static $vars = null;
    if ($vars === null) {
        $vars = is_file(ROOT . '/.env') ? parse_ini_file(ROOT . '/.env') : [];
    }
    return (string) ($vars[$key] ?? getenv($key) ?: $default);
}

function db(): PDO {
    static $pdo = null;
    return $pdo ??= new PDO(
        'mysql:host=' . env('DB_HOST', 'localhost') . ';dbname=' . env('DB_NAME') . ';charset=utf8mb4',
        env('DB_USER'), env('DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

// One file per channel per day (logs/app-2026-11-09.log) = rotation for free. Never pass secrets in $ctx.
function log_line(string $channel, string $level, string $msg, array $ctx = []): void {
    $line = date('c') . " [$level] $msg" . ($ctx ? ' ' . json_encode($ctx, JSON_UNESCAPED_SLASHES) : '') . "\n";
    @file_put_contents(ROOT . "/logs/$channel-" . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
}

// Append-only history: who did what to which record, with before/after. Also mirrored to the audit log file.
function audit(?int $userId, string $action, string $entity, ?int $entityId, $before = null, $after = null): void {
    db()->prepare('INSERT INTO audit_log (user_id, action, entity, entity_id, before_json, after_json, ip)
                   VALUES (?,?,?,?,?,?,?)')
        ->execute([$userId, $action, $entity, $entityId,
            $before === null ? null : json_encode($before), $after === null ? null : json_encode($after),
            $_SERVER['REMOTE_ADDR'] ?? 'cli']);
    log_line('audit', 'INFO', "$action $entity#$entityId", ['user' => $userId]);
}

set_exception_handler(function (Throwable $e) {
    log_line('error', 'ERROR', $e->getMessage(), ['at' => $e->getFile() . ':' . $e->getLine()]);
    http_response_code(500);
    echo env('APP_DEBUG') === '1' ? $e : 'Something went wrong.';
});
