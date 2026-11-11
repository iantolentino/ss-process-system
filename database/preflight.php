<?php
// Run on the server after deploying: php database/preflight.php   (exit 1 if anything is wrong)
// Checks the settings that, when missed, break resets, uploads, backups or security. Changes nothing.
if (PHP_SAPI !== 'cli') exit('CLI only');
require __DIR__ . '/../backend/bootstrap.php';

$bad = 0;
$check = function (string $name, bool $ok, string $fix = '') use (&$bad) {
    echo ($ok ? 'ok   ' : 'FAIL ') . $name . ($ok ? '' : " -> $fix") . "\n";
    $bad += !$ok;
};
$bytes = function (string $v): int { $n = (int) $v; return match (strtoupper(substr(trim($v), -1))) { 'G' => $n << 30, 'M' => $n << 20, 'K' => $n << 10, default => $n }; };

$prod = env('APP_ENV') === 'production';
$check('PHP 8.0+', PHP_VERSION_ID >= 80000, 'select PHP 8 in MultiPHP Manager (now ' . PHP_VERSION . ')');
$check('extensions pdo_mysql, mbstring, json, phar', !array_diff(['pdo_mysql', 'mbstring', 'json', 'phar'], array_filter(['pdo_mysql', 'mbstring', 'json', 'phar'], 'extension_loaded')), 'enable them in MultiPHP Manager');
$check('APP_ENV=production', $prod, 'set in .env (local only logs mail instead of sending it)');
$check('APP_DEBUG=0', env('APP_DEBUG') !== '1', 'set APP_DEBUG=0 in .env');
$check('APP_URL is https', str_starts_with(env('APP_URL'), 'https://'), 'set APP_URL="https://your-domain" in .env (reset links are built from it)');
$check('SMTP_PORT matches SMTP_SECURE (587 tls, 465 ssl)', env('SMTP_HOST') === '' || (env('SMTP_SECURE', 'tls') === 'ssl' ? env('SMTP_PORT', '587') === '465' : env('SMTP_SECURE', 'tls') !== 'tls' || env('SMTP_PORT', '587') === '587'), 'use 587 with tls or 465 with ssl, unless your host says otherwise');
$check('MAIL_FROM set', env('MAIL_FROM') !== '', 'set MAIL_FROM="Name <no-reply@your-domain>" so mail is not rejected as spam');
$check('upload_max_filesize >= 25M', $bytes(ini_get('upload_max_filesize')) >= 25 << 20, 'MultiPHP INI Editor (now ' . ini_get('upload_max_filesize') . ')');
$check('post_max_size >= 30M', $bytes(ini_get('post_max_size')) >= 30 << 20, 'MultiPHP INI Editor (now ' . ini_get('post_max_size') . ')');
$check('date.timezone set', ini_get('date.timezone') !== '', 'set it in MultiPHP INI Editor so logs, audit rows and MySQL agree');
$envPerm = is_file(ROOT . '/.env') ? substr(sprintf('%o', fileperms(ROOT . '/.env')), -3) : '';
$check('.env not world-readable', $envPerm !== '' && ($envPerm[2] ?? '9') === '0', 'chmod 600 .env (now ' . ($envPerm ?: 'missing') . ')');
foreach (['files/uploads', 'logs', 'database/backups'] as $d) $check("$d writable", is_dir(ROOT . "/$d") && is_writable(ROOT . "/$d"), "create it and make it writable by PHP, not 777");

try {
    $pdo = db();
    $check('database connects', true);
    $check('migrations applied (004)', (bool) $pdo->query("SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND index_name = 'idx_name' AND table_name = 'users'")->fetchColumn(), 'php database/migrate.php');
    $check('an active admin exists', (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1")->fetchColumn() > 0, 'php database/seed.php admin@your-domain PASSWORD');
    $check('no @example.test accounts', (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email LIKE '%@example.test' AND active = 1")->fetchColumn() === 0, 'disable test/demo users on Users page');
} catch (Throwable $e) {
    $check('database connects', false, 'check DB_* in .env: ' . $e->getMessage());
}

exec(escapeshellarg(env('MYSQLDUMP', 'mysqldump')) . ' --version 2>&1', $o, $rc);
$check('mysqldump found (backups)', $rc === 0, 'set MYSQLDUMP=/path/to/mysqldump in .env');
$check('mail() available', function_exists('mail') && ini_get('sendmail_path') !== '' || !$prod, 'ask the host to enable mail()');

echo $bad ? "\n$bad problem(s) found.\n" : "\nAll checks passed.\n";
exit($bad ? 1 : 0);
