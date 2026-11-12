<?php
require_once __DIR__ . '/../../backend/services/documents.php';
$user = require_login();

$s = db()->prepare('SELECT v.*, d.status FROM document_versions v JOIN documents d ON d.id = v.document_id WHERE v.id = ?');
$s->execute([(int) ($_GET['v'] ?? 0)]);
$v = $s->fetch();
$path = $v ? ROOT . '/' . $v['file_path'] : '';
if (!$v || ($v['status'] !== 'active' && !can_edit_docs($user)) || !is_file($path)) { error_page(404, 'File not found.'); }

audit((int) $user['id'], 'download', 'document', (int) $v['document_id'], null, ['version_id' => (int) $v['id']]);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $v['original_name']) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
