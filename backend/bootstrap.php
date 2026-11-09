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

// Absolute link for emails and cron (no request there). APP_URL in .env, e.g. https://example.com/process
function app_url(string $path = ''): string {
    return rtrim(env('APP_URL'), '/') . '/' . ltrim($path, '/');
}

// Sends a plain-text email. Local dev has no mail server, so it goes to the app log instead.
// shortcut: PHP mail() on cPanel, no queue/retry; a failure is logged, never shown to the user.
function notify(string $to, string $subject, string $body): void {
    $to = str_replace(["", "
"], '', $to);   // no header injection through names in subjects
    $subject = str_replace(["", "
"], ' ', $subject);
    if (env('APP_ENV') === 'local') { log_line('app', 'DEV', "mail to $to: $subject", ['body' => $body]); return; }
    $from = env('MAIL_FROM');
    if (!@mail($to, $subject, $body, $from ? "From: $from" : '')) log_line('error', 'WARN', "mail to $to failed: $subject");
}

function h($s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

// Thrown for mistakes the user can fix; pages show the message instead of a 500.
class UserError extends Exception {}

function flash(?string $msg = null): ?string {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if ($msg !== null) { $_SESSION['flash'] = $msg; return null; }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

set_exception_handler(function (Throwable $e) {
    log_line('error', 'ERROR', $e->getMessage(), ['at' => $e->getFile() . ':' . $e->getLine()]);
    http_response_code(500);
    echo env('APP_DEBUG') === '1' ? $e : 'Something went wrong.';
});
