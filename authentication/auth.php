<?php
// Sessions, CSRF, roles, login lockout and password reset. Every login/logout/reset event is audited.
require_once __DIR__ . '/../backend/bootstrap.php';

const MAX_FAILS = 5;       // failed logins per email within LOCK_MINUTES
const LOCK_MINUTES = 15;
const IP_MAX_FAILS = 20;   // failed logins per IP within LOCK_MINUTES, across all emails
const IDLE_HOURS = 8;      // signed out after this long without a request
const RESET_PER_HOUR = 3;  // reset emails per account per hour
const ROLES = ['admin', 'policy_owner', 'process_owner', 'employee'];

function session_boot(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');   // never accept a session id the server did not create
    session_set_cookie_params([
        'httponly' => true, 'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) || env('APP_ENV') === 'production', 'path' => '/',
    ]);
    session_name('ssps');
    session_start();
}

function base_url(string $path = ''): string {
    return rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/' . ltrim($path, '/');
}

function redirect(string $path): void {
    header('Location: ' . base_url($path));
    exit;
}

function csrf_token(): string {
    session_boot();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_check(): void {
    session_boot();
    // Empty session token must fail too: hash_equals('', '') is true.
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        log_line('error', 'WARN', 'csrf mismatch', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
        http_response_code(400);
        exit('Invalid request. Go back, refresh and try again.');
    }
}

function current_user(): ?array {
    session_boot();
    if (empty($_SESSION['uid'])) return null;
    if (time() - ($_SESSION['seen'] ?? 0) > IDLE_HOURS * 3600) { $_SESSION = []; return null; }
    $_SESSION['seen'] = time();
    // Re-read each request so a disabled user or changed role takes effect immediately.
    $s = db()->prepare('SELECT id, first_name, last_name, email, role, department_id, position_id FROM users WHERE id = ? AND active = 1');
    $s->execute([$_SESSION['uid']]);
    return $s->fetch() ?: null;
}

function require_login(): array {
    return current_user() ?? redirect('login');
}

function require_role(string ...$roles): array {
    $u = require_login();
    if ($u['role'] !== 'admin' && !in_array($u['role'], $roles, true)) {
        log_line('app', 'WARN', 'forbidden', ['user' => $u['id'], 'roles' => $roles]);
        http_response_code(403);
        exit('You do not have access to this page.');
    }
    return $u;
}

// Returns an error message, or null on success.
function attempt_login(string $email, string $password): ?string {
    $email = strtolower(trim($email));
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $pdo = db();

    $s = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND success = 0 AND at > NOW() - INTERVAL ' . LOCK_MINUTES . ' MINUTE');
    $s->execute([$email]);
    $byIp = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND at > NOW() - INTERVAL ' . LOCK_MINUTES . ' MINUTE');
    $byIp->execute([$ip]);
    if ($s->fetchColumn() >= MAX_FAILS || $byIp->fetchColumn() >= IP_MAX_FAILS) {
        log_line('app', 'WARN', 'login locked', ['email' => $email, 'ip' => $ip]);
        return 'Too many failed attempts. Try again in ' . LOCK_MINUTES . ' minutes.';
    }

    $s = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ? AND active = 1');
    $s->execute([$email]);
    $u = $s->fetch();
    // Verify against a dummy hash when the user is unknown so timing does not reveal valid emails.
    $ok = password_verify($password, $u['password_hash'] ?? '$2y$10$usesomesillystringforsaltuQ8j8J9o3YkQ5oJzq6mQ5gH5e0xW2');
    $pdo->prepare('INSERT INTO login_attempts (email, ip, success) VALUES (?,?,?)')->execute([$email, $ip, (int) ($ok && $u)]);

    if (!$u || !$ok) {
        log_line('app', 'INFO', 'login failed', ['email' => $email, 'ip' => $ip]);
        return 'Invalid email or password.';
    }
    session_boot();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $u['id'];
    $_SESSION['seen'] = time();
    $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$u['id']]);
    audit((int) $u['id'], 'login', 'user', (int) $u['id']);
    return null;
}

function logout(): void {
    session_boot();
    if (!empty($_SESSION['uid'])) audit((int) $_SESSION['uid'], 'logout', 'user', (int) $_SESSION['uid']);
    $_SESSION = [];
    session_destroy();
}

// Always behaves the same to the caller, whether or not the email exists.
function request_reset(string $email): void {
    $s = db()->prepare('SELECT id, email FROM users WHERE email = ? AND active = 1');
    $s->execute([strtolower(trim($email))]);
    if (!($u = $s->fetch())) return;
    $id = $u['id'];
    // Throttle silently (same response as success) so nobody can flood an inbox.
    // Tokens last 1 hour, so "not yet expired" = "issued in the last hour".
    $n = db()->prepare('SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND expires_at > NOW()');
    $n->execute([$id]);
    if ($n->fetchColumn() >= RESET_PER_HOUR) { log_line('app', 'WARN', 'reset throttled', ['user' => $id]); return; }

    $token = bin2hex(random_bytes(32));
    db()->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?,NOW() + INTERVAL 1 HOUR)')
        ->execute([$id, hash('sha256', $token)]);
    audit((int) $id, 'reset_requested', 'user', (int) $id);

    // Production links come from APP_URL: the Host header is attacker-controlled and would let them steer the link to their site.
    $link = env('APP_URL') !== '' ? app_url('reset?token=' . $token)
        : (empty($_SERVER['HTTPS']) ? 'http' : 'https') . '://' . $_SERVER['HTTP_HOST'] . base_url('reset?token=' . $token);
    // Local dev has no mail server: write the link to the app log instead. Never do this in production.
    if (env('APP_ENV') === 'local') log_line('app', 'DEV', "reset link: $link");
    else notify($u['email'], 'Reset your password', "Use this link within 1 hour:\n$link\n");
}

// Returns an error message, or null on success.
function reset_password(string $token, string $new): ?string {
    if (strlen($new) < 8) return 'Password must be at least 8 characters.';
    $pdo = db();
    $s = $pdo->prepare('SELECT id, user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()');
    $s->execute([hash('sha256', $token)]);
    if (!($r = $s->fetch())) return 'This reset link is invalid or has expired.';

    $pdo->beginTransaction();
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $r['user_id']]);
    $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$r['user_id']]);   // every older link dies too
    $pdo->commit();
    audit((int) $r['user_id'], 'password_reset', 'user', (int) $r['user_id']);
    return null;
}
