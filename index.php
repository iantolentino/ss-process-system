<?php
// Front controller: /login, /logout, /forgot, /reset, /dashboard. Add new pages to $pages.
require __DIR__ . '/authentication/auth.php';

$pages = ['login', 'logout', 'forgot', 'reset', 'dashboard', 'users', 'policies', 'document', 'download', 'process-library', 'process', 'process-edit', 'audit', 'handbook', 'glossary', 'onboarding'];
$path = trim(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), strlen(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'))), '/');
$page = $path === '' || $path === 'index.php' ? 'dashboard' : $path;

if (!in_array($page, $pages, true)) {
    http_response_code(404);
    exit('Page not found.');
}
// Browser-side hardening. 'unsafe-inline' scripts remain because a few pages have small inline scripts; everything else is locked to this site + GSAP/fonts.
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');   // reset links carry a token in the URL
header('X-Frame-Options: DENY');
if (!empty($_SERVER['HTTPS'])) header('Strict-Transport-Security: max-age=31536000');
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_check();

require __DIR__ . "/frontend/pages/$page.php";
