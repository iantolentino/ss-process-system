<?php
require_once __DIR__ . '/../../backend/services/documents.php';
$user = require_login();
$canEdit = can_edit_docs($user);
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$s = db()->prepare('SELECT d.*, CONCAT(u.first_name, " ", u.last_name) AS owner FROM documents d LEFT JOIN users u ON u.id = d.owner_id WHERE d.id = ?');
$s->execute([$id]);
$doc = $s->fetch();
if (!$doc || ($doc['status'] !== 'active' && !$canEdit)) { error_page(404, 'Document not found.'); }

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if (!$canEdit && $act !== 'ack') { error_page(403, 'You do not have access to this action.'); }
    try {
        if ($act === 'ack') {
            acknowledge($user, $id);
            flash('Thank you. Your confirmation is recorded.');
        } elseif ($act === 'version') {
            add_version($user, $id, $_POST, $_FILES['file'] ?? []);
            flash($user['role'] === 'admin' ? 'New version uploaded.' : 'Sent to an admin for approval. Staff keep seeing the current version until then.');
        } else {
            update_document($user, $id, $_POST);
            flash('Document updated.');
        }
        redirect("document?id=$id");
    } catch (UserError $e) {
        $error = $e->getMessage();
    }
}

// Versions that are waiting for approval or were rejected are only shown to editors.
$vs = db()->prepare('SELECT v.*, CONCAT(u.first_name, " ", u.last_name) AS uploader FROM document_versions v JOIN users u ON u.id = v.uploaded_by WHERE document_id = ? AND (? OR v.approval = "approved") ORDER BY v.id DESC');
$vs->execute([$id, (int) $canEdit]);
$versions = $vs->fetchAll();
$next = count($versions) + 1 . '.0';

// Read and confirm: has this person confirmed the version in force, and (for editors) who has not yet.
$askAck = $doc['ack_required'] && $doc['status'] === 'active';
$myAck = $notYet = null;
if ($askAck) {
    $a = db()->prepare('SELECT acked_at FROM acknowledgements WHERE user_id = ? AND version_id = ?');
    $a->execute([$user['id'], $doc['current_version_id']]);
    $myAck = $a->fetchColumn() ?: null;
    if ($canEdit) {
        $a = db()->prepare('SELECT CONCAT(u.first_name, " ", u.last_name) FROM users u LEFT JOIN acknowledgements k ON k.user_id = u.id AND k.version_id = ?
                            WHERE u.active = 1 AND k.user_id IS NULL ORDER BY u.first_name, u.last_name');
        $a->execute([$doc['current_version_id']]);
        $notYet = $a->fetchAll(PDO::FETCH_COLUMN);
        $people = (int) db()->query('SELECT COUNT(*) FROM users WHERE active = 1')->fetchColumn();
    }
}
$owners = $user['role'] === 'admin' ? db()->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM users WHERE active = 1 AND role IN ('admin','policy_owner') ORDER BY first_name, last_name")->fetchAll() : [];

// ?compare=<version id>: that version against the one uploaded just before it.
$cmp = null;
if (isset($_GET['compare'])) {
    foreach ($versions as $k => $v) if ($v['id'] == (int) $_GET['compare'] && isset($versions[$k + 1])) $cmp = ['new' => $v, 'old' => $versions[$k + 1]];
    if ($cmp) {
        $lines = function (array $v) {
            $p = ROOT . '/' . $v['file_path'];
            return in_array(strtolower(pathinfo($p, PATHINFO_EXTENSION)), ['txt', 'csv'], true) && is_file($p) && filesize($p) < 200000
                ? preg_split('/\R/', rtrim((string) file_get_contents($p))) : null;
        };
        [$o, $n] = [$lines($cmp['old']), $lines($cmp['new'])];
        $cmp['diff'] = $o !== null && $n !== null ? diff_lines($o, $n) : null;
    }
}

$tl = db()->prepare('SELECT a.at, a.action, CONCAT(u.first_name, " ", u.last_name) AS who FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
                     WHERE a.entity = "document" AND a.entity_id = ? ORDER BY a.id DESC');
$tl->execute([$id]);
$timeline = $tl->fetchAll();

$title = $doc['title'];
require __DIR__ . '/../components/app_open.php';
?>
<p class="crumbs"><a href="<?= base_url('policies') ?>">Policies &amp; forms</a> / <a href="<?= base_url('policies?type=' . $doc['type']) ?>"><?= h(DOC_TYPES[$doc['type']]) ?></a></p>
<h1 class="title" data-animate><?= h($doc['title']) ?> <span class="badge <?= $doc['status'] === 'active' ? '' : 'off' ?>"><?= h($doc['status']) ?></span></h1>
<p class="muted"><?= h(ucfirst($doc['type'])) ?> · <?= h($doc['category'] ?: 'No category') ?> · owner <?= h($doc['owner'] ?? '—') ?>
  <?php if ($doc['review_due']): ?> · check again by <?= h($doc['review_due']) ?><?php endif ?>
  <?php if ($doc['memo_from']): ?> · from <?= h($doc['memo_from']) ?><?php endif ?>
  <?php if ($doc['audience']): ?> · for <?= h($doc['audience']) ?><?php endif ?>
  <?php if ($doc['expires_on']): ?> · <?= $doc['expires_on'] < date('Y-m-d') ? '<span class="badge warn">expired' : '<span class="badge">expires' ?> <?= h($doc['expires_on']) ?></span><?php endif ?>
  <?php if ($doc['sharepoint_url']): ?> · <a href="<?= h($doc['sharepoint_url']) ?>" target="_blank" rel="noopener">SharePoint ↗</a><?php endif ?></p>

<?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif ?>
<?php if (array_filter($versions, fn($v) => $v['approval'] === 'pending')): ?><div class="alert ok">A version is waiting for an admin's approval. Staff do not see it yet.<?php if ($user['role'] === 'admin'): ?> <a href="<?= base_url('approvals') ?>">Go to approvals</a><?php endif ?></div><?php endif ?>

<?php if ($askAck): ?>
<div class="panel" data-animate>
  <h2>Read and confirm</h2>
  <?php if ($myAck): ?><p class="muted">You confirmed on <?= h($myAck) ?> that you have read the current version.</p>
  <?php else: ?>
  <p class="help">Everyone is asked to read this document. Open the current version below, read it, then confirm here.</p>
  <form method="post"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="ack">
    <button class="btn inline" type="submit">I have read and understood this</button></form>
  <?php endif ?>
  <?php if ($notYet !== null): ?>
  <details style="margin-top:12px"><summary><?= $people - count($notYet) ?> of <?= $people ?> people have confirmed the current version</summary>
    <p class="help" style="margin-top:8px"><?= $notYet ? 'Not yet: ' . h(implode(', ', $notYet)) : 'Everyone has confirmed.' ?></p></details>
  <?php endif ?>
</div>
<?php endif ?>

<div class="panel" data-animate>
  <h2>Versions</h2>
  <p class="help">Every version is kept. The one marked "current" is the one in force. Click a file name to download it; PDF files can also be read in the browser with "View".</p>
  <div class="table-wrap"><table class="tbl">
    <tr><th>Version</th><th>Applies from</th><th>Uploaded</th><th>By</th><th>What changed</th><th>File</th></tr>
    <?php foreach ($versions as $v): ?>
    <tr>
      <td><?= h($v['version_no']) ?> <?= $v['approval'] !== 'approved' ? '<span class="badge ' . ($v['approval'] === 'pending' ? 'warn">waiting for approval' : 'off">rejected') . '</span>' : ($v['id'] == $doc['current_version_id'] ? '<span class="badge">current</span>' : '') ?></td>
      <td><?= h($v['effective_date']) ?></td><td><?= h($v['uploaded_at']) ?></td><td><?= h($v['uploader']) ?></td>
      <td><?= h($v['changelog']) ?><?= $v['decision_note'] !== '' ? ' <span class="muted">(' . h($v['decision_note']) . ')</span>' : '' ?><?php if ($v !== end($versions)): ?> · <a href="<?= base_url('document?id=' . $id . '&compare=' . $v['id']) ?>#compare">Compare with previous</a><?php endif ?></td>
      <td><a href="<?= base_url('download?v=' . $v['id']) ?>"><?= h($v['original_name']) ?></a><?php if (strtolower(pathinfo($v['original_name'], PATHINFO_EXTENSION)) === 'pdf'): ?>
        · <a href="<?= base_url('download?v=' . $v['id'] . '&inline=1') ?>" target="_blank" rel="noopener">View</a><?php endif ?></td>
    </tr>
    <?php endforeach ?>
  </table></div>
</div>

<?php if ($cmp): ?>
<div class="panel" id="compare" data-animate>
  <h2>Version <?= h($cmp['old']['version_no']) ?> → <?= h($cmp['new']['version_no']) ?></h2>
  <p class="muted">Effective <?= h($cmp['old']['effective_date']) ?> → <?= h($cmp['new']['effective_date']) ?> · uploaded by <?= h($cmp['new']['uploader']) ?> on <?= h($cmp['new']['uploaded_at']) ?>
    · size <?= (int) $cmp['old']['size_bytes'] ?> → <?= (int) $cmp['new']['size_bytes'] ?> bytes<br>
    Reason given: <?= h($cmp['new']['changelog']) ?></p>
  <?php if ($cmp['diff'] === null): ?>
    <p class="muted">A line-by-line comparison is only available for .txt and .csv files. Download both versions to compare other file types.</p>
  <?php elseif (!array_filter($cmp['diff'], fn($d) => $d[0] !== '=')): ?>
    <p class="muted">The text of both files is identical.</p>
  <?php else: ?>
    <pre class="diff"><?php foreach ($cmp['diff'] as [$k, $line]): ?><span class="d<?= $k === '=' ? 'eq' : ($k === '+' ? 'add' : 'del') ?>"><?= $k === '=' ? '  ' : $k . ' ' ?><?= h($line) ?></span><?php endforeach ?></pre>
  <?php endif ?>
</div>
<?php endif ?>

<?php if ($canEdit): ?>
<details class="panel" <?= $error && ($_POST['action'] ?? '') === 'version' ? 'open' : '' ?>>
  <summary>Upload a new version</summary>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="version">
    <div class="grid2">
      <div class="form-group"><label class="form-label">Version</label><input class="form-input" name="version_no" required value="<?= h($_POST['version_no'] ?? $next) ?>"></div>
      <div class="form-group"><label class="form-label">Starts to apply on</label><input class="form-input" type="date" name="effective_date" required value="<?= h($_POST['effective_date'] ?? date('Y-m-d')) ?>"></div>
      <div class="form-group"><label class="form-label">Check again on</label><input class="form-input" type="date" name="review_due" value="<?= h($_POST['review_due'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">File</label><input class="form-input" type="file" name="file" required></div>
    </div>
    <div class="form-group"><label class="form-label">What changed? (required)</label><input class="form-input" name="changelog" required value="<?= h($_POST['changelog'] ?? '') ?>"></div>
    <button class="btn inline" type="submit">Upload version</button>
  </form>
</details>

<details class="panel" <?= $error && ($_POST['action'] ?? '') !== 'version' ? 'open' : '' ?>>
  <summary>Edit details or archive</summary>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= $id ?>">
    <div class="grid2">
      <div class="form-group"><label class="form-label">Title</label><input class="form-input" name="title" required value="<?= h($doc['title']) ?>"></div>
      <div class="form-group"><label class="form-label">Category</label><input class="form-input" name="category" value="<?= h($doc['category']) ?>"></div>
      <div class="form-group"><label class="form-label">Check again on</label><input class="form-input" type="date" name="review_due" value="<?= h($doc['review_due']) ?>"></div>
      <div class="form-group"><label class="form-label">SharePoint link</label><input class="form-input" name="sharepoint_url" value="<?= h($doc['sharepoint_url']) ?>"></div>
      <?php if ($doc['type'] === 'memo'): ?>
      <div class="form-group"><label class="form-label">Memo from</label><input class="form-input" name="memo_from" value="<?= h($doc['memo_from']) ?>"></div>
      <div class="form-group"><label class="form-label">Audience</label><input class="form-input" name="audience" value="<?= h($doc['audience']) ?>"></div>
      <div class="form-group"><label class="form-label">Expires</label><input class="form-input" type="date" name="expires_on" value="<?= h($doc['expires_on']) ?>"></div>
      <?php endif ?>
      <div class="form-group"><label class="form-label">Status</label>
        <select class="form-input" name="status"><?php foreach (DOC_STATUS as $st): ?><option <?= $doc['status'] === $st ? 'selected' : '' ?>><?= h($st) ?></option><?php endforeach ?></select><p class="help">Archived documents are hidden from staff but never deleted.</p></div>
      <?php if ($owners): ?>
      <div class="form-group"><label class="form-label">Owner</label>
        <select class="form-input" name="owner_id"><?php foreach ($owners as $o): ?><option value="<?= $o['id'] ?>" <?= $o['id'] == $doc['owner_id'] ? 'selected' : '' ?>><?= h($o['name']) ?></option><?php endforeach ?>
          <?php if (!in_array($doc['owner_id'], array_column($owners, 'id'))): ?><option value="" selected><?= h($doc['owner'] ?? 'No owner') ?> (no longer an editor)</option><?php endif ?></select>
        <p class="help">The person responsible for keeping this document right. Change it when the owner moves on.</p></div>
      <?php endif ?>
    </div>
    <div class="form-group"><label><input type="checkbox" name="ack_required" value="1" <?= $doc['ack_required'] ? 'checked' : '' ?>> Ask everyone to confirm they have read this document</label>
      <p class="help">People see it on Home until they confirm. Each new version asks again.</p></div>
    <button class="btn inline" type="submit">Save</button>
  </form>
</details>
<?php endif ?>

<div class="panel" data-animate>
  <h2>Activity on this document</h2>
  <table class="tbl">
    <?php foreach ($timeline as $t): ?><tr><td><?= h($t['at']) ?></td><td><?= h(str_replace('_', ' ', $t['action'])) ?></td><td><?= h($t['who'] ?? 'system') ?></td></tr><?php endforeach ?>
  </table>
</div>
<?php require __DIR__ . '/../components/app_close.php';
