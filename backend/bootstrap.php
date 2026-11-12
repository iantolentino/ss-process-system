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
// With SMTP_HOST set in .env it goes through that SMTP account, otherwise PHP mail().
// shortcut: no queue or retry; a failure is logged, never shown to the user.
function notify(string $to, string $subject, string $body): void {
    $to = preg_replace('/[\r\n]+/', '', $to);          // no header injection through names in subjects
    $subject = preg_replace('/[\r\n]+/', ' ', $subject);
    if (env('APP_ENV') === 'local') { log_line('app', 'DEV', "mail to $to: $subject", ['body' => $body]); return; }
    $from = env('MAIL_FROM');
    $ok = env('SMTP_HOST') !== '' ? smtp_send($to, $subject, $body) : @mail($to, $subject, $body, $from ? "From: $from" : '');
    if (!$ok) log_line('error', 'WARN', "mail to $to failed: $subject");
}

// Minimal SMTP client: SMTP_SECURE=tls (STARTTLS, port 587, default) | ssl (port 465) | none; AUTH LOGIN when SMTP_USER is set.
function smtp_send(string $to, string $subject, string $body): bool {
    $host = env('SMTP_HOST'); $port = (int) env('SMTP_PORT', '587'); $secure = env('SMTP_SECURE', 'tls');
    $from = env('MAIL_FROM') ?: env('SMTP_USER');
    $addr = preg_match('/<([^>]+)>/', $from, $m) ? $m[1] : $from;
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . "$host:$port", $errno, $err, 15);
    if (!$fp) { log_line('error', 'WARN', "smtp connect failed: $err"); return false; }
    stream_set_timeout($fp, 15);
    $reply = function () use ($fp): string {
        $r = '';
        while (($l = fgets($fp, 515)) !== false) { $r .= $l; if (($l[3] ?? ' ') === ' ') break; }
        return $r;
    };
    $say = function (string $cmd, string $want) use ($fp, $reply): bool {
        if ($cmd !== '') fwrite($fp, "$cmd\r\n");
        $r = $reply();
        if (!str_starts_with($r, $want)) { log_line('error', 'WARN', 'smtp unexpected reply', ['expected' => $want, 'got' => trim($r)]); return false; }
        return true;
    };
    $me = parse_url(env('APP_URL'), PHP_URL_HOST) ?: 'localhost';
    $ok = $say('', '220') && $say("EHLO $me", '250');
    if ($ok && $secure === 'tls') $ok = $say('STARTTLS', '220') && stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) && $say("EHLO $me", '250');
    if ($ok && env('SMTP_USER') !== '') $ok = $say('AUTH LOGIN', '334') && $say(base64_encode(env('SMTP_USER')), '334') && $say(base64_encode(env('SMTP_PASS')), '235');
    $ok = $ok && $say("MAIL FROM:<$addr>", '250') && $say("RCPT TO:<$to>", '25') && $say('DATA', '354');
    if ($ok) {
        $msg = "From: " . ($from ?: $addr) . "\r\nTo: $to\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\nDate: " . date('r')
            . "\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n"
            . preg_replace('/^\./m', '..', preg_replace('/\r\n|\r|\n/', "\r\n", $body));
        fwrite($fp, "$msg\r\n.\r\n");
        $ok = str_starts_with($reply(), '250');
    }
    @fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return $ok;
}

function h($s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

// Thrown for mistakes the user can fix; pages show the message instead of a 500.
class UserError extends Exception {}

// A friendly page for 400 / 403 / 404 instead of bare text. Sends the status code and stops.
function error_page(int $code, string $msg): void {
    http_response_code($code);
    $titles = [400 => 'Request problem', 403 => 'No access', 404 => 'Not found'];
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $css = $base . '/frontend/css/base.css';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . h($titles[$code] ?? 'Error') . ' — Strata Staff</title><link rel="stylesheet" href="' . h($css) . '"></head><body>'
        . '<main style="max-width:440px;margin:15vh auto;padding:0 16px"><div class="panel"><p class="muted">' . $code . '</p>'
        . '<h1 class="title">' . h($titles[$code] ?? 'Something went wrong') . '</h1><p class="lead" style="margin:8px 0 20px">' . h($msg) . '</p>'
        . '<a class="btn inline" href="' . h($base . '/dashboard') . '">Go to Home</a></div></main></body></html>';
    exit;
}
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
