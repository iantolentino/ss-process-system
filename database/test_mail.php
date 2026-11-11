<?php
// Sends one test email with your .env settings: php database/test_mail.php you@yourdomain.com
// Use it after filling SMTP_* / MAIL_FROM to confirm password-reset and notice emails will arrive.
if (PHP_SAPI !== 'cli') exit('CLI only');
require __DIR__ . '/../backend/bootstrap.php';
$to = $argv[1] ?? exit("usage: php database/test_mail.php you@yourdomain.com\n");
if (env('APP_ENV') === 'local') exit("APP_ENV=local only writes mail to logs/app-*.log. Set APP_ENV=production to send for real.\n");
$ok = env('SMTP_HOST') !== '' ? smtp_send($to, 'Strata Staff test email', "If you can read this, email from the process system works.\n") : @mail($to, 'Strata Staff test email', "If you can read this, email from the process system works.\n", env('MAIL_FROM') ? 'From: ' . env('MAIL_FROM') : '');
echo $ok ? "sent via " . (env('SMTP_HOST') ?: 'PHP mail()') . " - check the inbox (and spam) of $to\n" : "FAILED - see logs/error-" . date('Y-m-d') . ".log\n";
exit($ok ? 0 : 1);
