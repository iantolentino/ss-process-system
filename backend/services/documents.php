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

// Validates and moves the upload into files/uploads/<docId>/. Returns the data for document_versions.
function store_upload(array $f, int $docId): array {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new UserError('Choose a file to upload (max 20 MB).');
    if ($f['size'] > DOC_MAX_BYTES) throw new UserError('File is larger than 20 MB.');
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, DOC_EXT, true)) throw new UserError('Allowed file types: ' . implode(', ', DOC_EXT) . '.');
    if (!is_uploaded_file($f['tmp_name'])) throw new UserError('Upload failed. Try again.');

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

    $up = store_upload($file, $docId);
    try {
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO document_versions (document_id, version_no, file_path, original_name, sha256, size_bytes, changelog, effective_date, uploaded_by)
                       VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$docId, $versionNo, $up['file_path'], $up['original_name'], $up['sha256'], $up['size_bytes'], $changelog, $effective, $user['id']]);
        $vid = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE documents SET current_version_id = ?, review_due = ? WHERE id = ?')
            ->execute([$vid, $reviewDue === '' ? null : $reviewDue, $docId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        @unlink(ROOT . '/' . $up['file_path']);
        throw $e;
    }
    audit((int) $user['id'], 'new_version', 'document', $docId, null,
        ['version_no' => $versionNo, 'effective_date' => $effective, 'file' => $up['original_name'], 'sha256' => $up['sha256'], 'changelog' => $changelog]);
    return $vid;
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
    $pdo->prepare('INSERT INTO documents (type, title, category, owner_id, sharepoint_url) VALUES (?,?,?,?,?)')
        ->execute([$type, $title, trim($in['category'] ?? ''), $user['id'], $sp === '' ? null : $sp]);
    $id = (int) $pdo->lastInsertId();
    try {
        add_version($user, $id, $in, $file);
    } catch (Throwable $e) {
        $pdo->prepare('DELETE FROM documents WHERE id = ?')->execute([$id]);   // no version = no document
        throw $e;
    }
    audit((int) $user['id'], 'create', 'document', $id, null, ['type' => $type, 'title' => $title]);
    return $id;
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

    $s = db()->prepare('SELECT title, category, status, review_due, sharepoint_url FROM documents WHERE id = ?');
    $s->execute([$id]);
    if (!($before = $s->fetch())) throw new UserError('Document not found.');
    $after = ['title' => $title, 'category' => trim($in['category'] ?? ''), 'status' => $status,
              'review_due' => $due === '' ? null : $due, 'sharepoint_url' => $sp === '' ? null : $sp];
    db()->prepare('UPDATE documents SET title=?, category=?, status=?, review_due=?, sharepoint_url=? WHERE id=?')
        ->execute([...array_values($after), $id]);
    audit((int) $user['id'], $before['status'] !== $status ? "status_$status" : 'update', 'document', $id, $before, $after);
}
