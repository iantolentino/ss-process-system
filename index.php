<?php
// Front controller: /login, /logout, /forgot, /reset, /dashboard. Add new pages to $pages.
require __DIR__ . '/authentication/auth.php';

$pages = ['login', 'logout', 'forgot', 'reset', 'dashboard', 'users', 'policies', 'document', 'download'];
$path = trim(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), strlen(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'))), '/');
$page = $path === '' || $path === 'index.php' ? 'dashboard' : $path;

if (!in_array($page, $pages, true)) {
    http_response_code(404);
    exit('Page not found.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_check();

require __DIR__ . "/frontend/pages/$page.php";
