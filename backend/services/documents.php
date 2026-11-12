<?php
// Policy Portal: documents (policy, memo, manual, form) and their immutable versions.
// Every create / new version / metadata change / status change is written to audit_log with the date.
require_once __DIR__ . '/../bootstrap.php';

const DOC_TYPES = ['policy' => 'Policies', 'memo' => 'Memos', 'manual' => 'Manuals', 'form' => 'Forms'];
const DOC_STATUS = ['draft', 'active', 'archived'];
const DOC_EXT = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'];
const DOC_MAX_BYTES = 20 * 1024 * 1024;
const DOC_EDITORS = ['admin', 'policy_owner'];

function can_edit_docs(array $user): bool {
    return in_array($user['role'], DOC_EDITORS, true);
}

function valid_date(string $d): bool {
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x && $x->format('Y-m-d') === $d;
}

// The file content must look like what its extension claims: stops an .exe or a web page renamed to .pdf.
// shortcut: Office files are only checked to be Office/zip containers, not opened.
function content_ok(string $path, string $ext): bool {
    if (!function_exists('finfo_open')) return true;   // extension missing: the extension allow-list above still applies
    $mime = (string) finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path);
    return match ($ext) {
        'pdf' => $mime === 'application/pdf',
        'txt', 'csv' => str_starts_with($mime, 'text/') || in_array($mime, ['application/csv', 'inode/x-empty'], true),
        'docx', 'xlsx', 'pptx' => str_starts_with($mime, 'application/vnd.openxmlformats') || in_array($mime, ['application/zip', 'application/x-zip', 'application/octet-stream'], true),
        'doc', 'xls', 'ppt' => str_starts_with($mime, 'application/vnd.ms-') || in_array($mime, ['application/msword', 'application/CDFV2', 'application/x-ole-storage', 'application/octet-stream'], true),
        default => false,
    };
}
// Validates and moves the upload into files/uploads/<docId>/. Returns the data for document_versions.
function store_upload(array $f, int $docId): array {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new UserError('Choose a file to upload (max 20 MB).');
    if ($f['size'] > DOC_MAX_BYTES) throw new UserError('File is larger than 20 MB.');
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, DOC_EXT, true)) throw new UserError('Allowed file types: ' . implode(', ', DOC_EXT) . '.');
    if (!is_uploaded_file($f['tmp_name'])) throw new UserError('Upload failed. Try again.');
    if (!content_ok($f['tmp_name'], $ext)) throw new UserError('That file does not look like a real .' . $ext . ' file. Check it opens, then try again.');

    $dir = ROOT . "/files/uploads/$docId";
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) throw new RuntimeException("cannot create $dir");
    $stored = bin2hex(random_bytes(8)) . ".$ext";   // never use the user's filename on disk
    if (!move_uploaded_file($f['tmp_name'], "$dir/$stored")) throw new RuntimeException('move_uploaded_file failed');
    return [
        'file_path' => "files/uploads/$docId/$stored",
        'original_name' => basename($f['name']),
        'sha256' => hash_file('sha256', "$dir/$stored"),
        'size_bytes' => (int) $f['size'],
    ];
}

// Adds a version (never replaces an old one) and makes it current. Returns the version id.
function add_version(array $user, int $docId, array $in, array $file): int {
    $versionNo = trim($in['version_no'] ?? '');
    $effective = trim($in['effective_date'] ?? '');
    $changelog = trim($in['changelog'] ?? '');
    $reviewDue = trim($in['review_due'] ?? '');
    if ($versionNo === '' || strlen($versionNo) > 20) throw new UserError('Version number is required (max 20 characters).');
    if (!valid_date($effective)) throw new UserError('Enter a valid effective date.');
    if ($changelog === '') throw new UserError('Describe what changed in this version.');
    if ($reviewDue !== '' && !valid_date($reviewDue)) throw new UserError('Enter a valid review date.');

    $pdo = db();
    $s = $pdo->prepare('SELECT id FROM document_versions WHERE document_id = ? AND version_no = ?');
    $s->execute([$docId, $versionNo]);
    if ($s->fetch()) throw new UserError("Version $versionNo already exists for this document.");
    $s = $pdo->prepare("SELECT id FROM document_versions WHERE document_id = ? AND approval = 'pending'");
    $s->execute([$docId]);
    if ($s->fetch()) throw new UserError('A version of this document is already waiting for approval. Ask an admin to approve or reject it first.');

    // An admin's upload is published at once; anyone else's waits until an admin approves it.
    $approved = $user['role'] === 'admin';
    $up = store_upload($file, $docId);
    try {
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO document_versions (document_id, version_no, file_path, original_name, sha256, size_bytes, changelog, effective_date, uploaded_by, approval, decided_by, decided_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$docId, $versionNo, $up['file_path'], $up['original_name'], $up['sha256'], $up['size_bytes'], $changelog, $effective, $user['id'],
                $approved ? 'approved' : 'pending', $approved ? $user['id'] : null, $approved ? date('Y-m-d H:i:s') : null]);
        $vid = (int) $pdo->lastInsertId();
        // A waiting version only becomes current when the document has none yet (a brand-new document, still a draft).
        $pdo->prepare('UPDATE documents SET current_version_id = IF(? OR current_version_id IS NULL, ?, current_version_id), review_due = ? WHERE id = ?')
            ->execute([(int) $approved, $vid, $reviewDue === '' ? null : $reviewDue, $docId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        @unlink(ROOT . '/' . $up['file_path']);
        throw $e;
    }
    audit((int) $user['id'], 'new_version', 'document', $docId, null,
        ['version_no' => $versionNo, 'effective_date' => $effective, 'file' => $up['original_name'], 'sha256' => $up['sha256'], 'changelog' => $changelog, 'approval' => $approved ? 'approved' : 'pending']);
    if (!$approved) {
        notify_admins('Waiting for your approval: document version ' . $versionNo,
            "{$user['first_name']} {$user['last_name']} uploaded version $versionNo of a document.\nWhat changed: $changelog\n\n" . app_url('approvals'));
    }
    return $vid;
}

// Admin decision on a waiting version. Approving makes it the current version (and publishes a brand-new document).
function decide_document_version(array $user, int $vid, bool $approve, string $note): void {
    $pdo = db();
    $s = $pdo->prepare("SELECT v.document_id, v.version_no, d.title, u.email,
                               (SELECT COUNT(*) FROM document_versions x WHERE x.document_id = v.document_id AND x.approval = 'approved') AS prior
                        FROM document_versions v JOIN documents d ON d.id = v.document_id JOIN users u ON u.id = v.uploaded_by
                        WHERE v.id = ? AND v.approval = 'pending'");
    $s->execute([$vid]);
    if (!($v = $s->fetch())) throw new UserError('That item is no longer waiting for approval.');
    $note = mb_substr(trim($note), 0, 500);
    if (!$approve && $note === '') throw new UserError('Say why it is rejected, so the person can fix it.');

    $pdo->beginTransaction();
    $pdo->prepare('UPDATE document_versions SET approval = ?, decided_by = ?, decided_at = NOW(), decision_note = ? WHERE id = ?')
        ->execute([$approve ? 'approved' : 'rejected', $user['id'], $note, $vid]);
    if ($approve) {
        $pdo->prepare("UPDATE documents SET current_version_id = ?, status = IF(? = 0 AND status = 'draft', 'active', status) WHERE id = ?")
            ->execute([$vid, (int) $v['prior'], $v['document_id']]);
    }
    $pdo->commit();
    $word = $approve ? 'approved' : 'rejected';
    audit((int) $user['id'], $word, 'document', (int) $v['document_id'], null, ['version_no' => $v['version_no'], 'note' => $note]);
    notify($v['email'], "Your upload was $word: {$v['title']}", "Version {$v['version_no']} of \"{$v['title']}\" was $word." . ($note !== '' ? "\nNote: $note" : '') . "\n\n" . app_url('document?id=' . $v['document_id']));
}

// "I have read this": one row per person per version. Only counts for the version in force.
function acknowledge(array $user, int $docId): void {
    $s = db()->prepare("SELECT current_version_id FROM documents WHERE id = ? AND status = 'active' AND ack_required = 1");
    $s->execute([$docId]);
    if (!($vid = $s->fetchColumn())) throw new UserError('This document does not need a confirmation.');
    $i = db()->prepare('INSERT IGNORE INTO acknowledgements (user_id, version_id) VALUES (?,?)');
    $i->execute([$user['id'], $vid]);
    if ($i->rowCount()) audit((int) $user['id'], 'acknowledged', 'document', $docId, null, ['version_id' => (int) $vid]);
}

// Creates the document and its first version together. Returns the document id.
function create_document(array $user, array $in, array $file): int {
    $type = $in['type'] ?? '';
    $title = trim($in['title'] ?? '');
    $sp = trim($in['sharepoint_url'] ?? '');
    if (!isset(DOC_TYPES[$type])) throw new UserError('Pick a document type.');
    if ($title === '') throw new UserError('Title is required.');
    if ($sp !== '' && !preg_match('#^https?://#i', $sp)) throw new UserError('SharePoint link must start with http:// or https://');
    if (trim($in['changelog'] ?? '') === '') $in['changelog'] = 'Initial version';

    $pdo = db();
    $memo = memo_fields($type, $in);
    // A non-admin's new document stays a draft (hidden from staff) until an admin approves its first version.
    $pdo->prepare('INSERT INTO documents (type, title, category, owner_id, status, ack_required, sharepoint_url, memo_from, audience, expires_on) VALUES (?,?,?,?,?,?,?,?,?,?)')
        ->execute([$type, $title, trim($in['category'] ?? ''), $user['id'], $user['role'] === 'admin' ? 'active' : 'draft', (int) !empty($in['ack_required']), $sp === '' ? null : $sp, ...array_values($memo)]);
    $id = (int) $pdo->lastInsertId();
    try {
        add_version($user, $id, $in, $file);
    } catch (Throwable $e) {
        $pdo->prepare('DELETE FROM documents WHERE id = ?')->execute([$id]);   // no version = no document
        throw $e;
    }
    audit((int) $user['id'], 'create', 'document', $id, null, ['type' => $type, 'title' => $title] + array_filter($memo));
    return $id;
}

// Memo-only fields; other types always store NULLs. Throws UserError on a bad expiry date.
function memo_fields(string $type, array $in): array {
    if ($type !== 'memo') return ['memo_from' => null, 'audience' => null, 'expires_on' => null];
    $exp = trim($in['expires_on'] ?? '');
    if ($exp !== '' && !valid_date($exp)) throw new UserError('Enter a valid expiry date.');
    return ['memo_from' => trim($in['memo_from'] ?? '') ?: null, 'audience' => trim($in['audience'] ?? '') ?: null, 'expires_on' => $exp ?: null];
}

function update_document(array $user, int $id, array $in): void {
    $title = trim($in['title'] ?? '');
    $sp = trim($in['sharepoint_url'] ?? '');
    $due = trim($in['review_due'] ?? '');
    $status = $in['status'] ?? '';
    if ($title === '') throw new UserError('Title is required.');
    if (!in_array($status, DOC_STATUS, true)) throw new UserError('Pick a status.');
    if ($sp !== '' && !preg_match('#^https?://#i', $sp)) throw new UserError('SharePoint link must start with http:// or https://');
    if ($due !== '' && !valid_date($due)) throw new UserError('Enter a valid review date.');

    $s = db()->prepare('SELECT d.type, v.approval, d.title, d.category, d.status, d.review_due, d.sharepoint_url, d.memo_from, d.audience, d.expires_on, d.ack_required, d.owner_id
                        FROM documents d LEFT JOIN document_versions v ON v.id = d.current_version_id WHERE d.id = ?');
    $s->execute([$id]);
    if (!($before = $s->fetch())) throw new UserError('Document not found.');
    $type = array_shift($before);   // type is not editable; keep it out of the before/after diff
    if (array_shift($before) !== 'approved' && $status === 'active') throw new UserError('This document cannot be made active until an admin approves it.');
    $before['ack_required'] = (int) $before['ack_required'];
    $before['owner_id'] = $before['owner_id'] === null ? null : (int) $before['owner_id'];
    // Only an admin can hand a document to another owner (e.g. when the uploader leaves).
    $owner = $before['owner_id'];
    if ($user['role'] === 'admin' && ($in['owner_id'] ?? '') !== '') {
        $o = db()->prepare('SELECT id FROM users WHERE id = ? AND active = 1');
        $o->execute([(int) $in['owner_id']]);
        if (!($owner = $o->fetchColumn())) throw new UserError('Pick an active person as the owner.');
        $owner = (int) $owner;
    }
    $after = ['title' => $title, 'category' => trim($in['category'] ?? ''), 'status' => $status,
              'review_due' => $due === '' ? null : $due, 'sharepoint_url' => $sp === '' ? null : $sp] + memo_fields($type, $in)
           + ['ack_required' => (int) !empty($in['ack_required']), 'owner_id' => $owner];
    db()->prepare('UPDATE documents SET title=?, category=?, status=?, review_due=?, sharepoint_url=?, memo_from=?, audience=?, expires_on=?, ack_required=?, owner_id=? WHERE id=?')
        ->execute([...array_values($after), $id]);
    audit((int) $user['id'], $before['status'] !== $status ? "status_$status" : 'update', 'document', $id, $before, $after);
}

// Line diff of two small text files (LCS). Returns [['=', line], ['-', line], ['+', line], ...]; null when too big to compare.
function diff_lines(array $a, array $b): ?array {
    if (count($a) > 1500 || count($b) > 1500) return null;
    $n = count($a); $m = count($b); $L = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
    for ($i = $n - 1; $i >= 0; $i--) for ($j = $m - 1; $j >= 0; $j--)
        $L[$i][$j] = $a[$i] === $b[$j] ? $L[$i + 1][$j + 1] + 1 : max($L[$i + 1][$j], $L[$i][$j + 1]);
    $out = []; $i = $j = 0;
    while ($i < $n && $j < $m) {
        if ($a[$i] === $b[$j]) { $out[] = ['=', $a[$i]]; $i++; $j++; }
        elseif ($L[$i + 1][$j] >= $L[$i][$j + 1]) $out[] = ['-', $a[$i++]];
        else $out[] = ['+', $b[$j++]];
    }
    while ($i < $n) $out[] = ['-', $a[$i++]];
    while ($j < $m) $out[] = ['+', $b[$j++]];
    return $out;
}
