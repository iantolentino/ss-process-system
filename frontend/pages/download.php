<?php
require_once __DIR__ . '/../../backend/services/documents.php';
$user = require_login();

$s = db()->prepare('SELECT v.*, d.status FROM document_versions v JOIN documents d ON d.id = v.document_id WHERE v.id = ?');
$s->execute([(int) ($_GET['v'] ?? 0)]);
$v = $s->fetch();
$path = $v ? ROOT . '/' . $v['file_path'] : '';
// Staff only get approved versions of active documents; editors also get drafts, waiting and rejected versions.
if (!$v || (($v['status'] !== 'active' || $v['approval'] !== 'approved') && !can_edit_docs($user)) || !is_file($path)) { error_page(404, 'File not found.'); }

// ?inline=1 shows a PDF in the browser's own viewer instead of saving it. Every other file type is always a download.
$inline = isset($_GET['inline']) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
audit((int) $user['id'], $inline ? 'view' : 'download', 'document', (int) $v['document_id'], null, ['version_id' => (int) $v['id']]);
if ($inline) header_remove('Content-Security-Policy');   // the page policy (no plugins) can stop the built-in PDF viewer; a PDF is not a web page
header('Content-Type: ' . ($inline ? 'application/pdf' : 'application/octet-stream'));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', $v['original_name']) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
