<?php
require_once __DIR__ . '/../../backend/services/documents.php';
$user = require_login();
$canEdit = can_edit_docs($user);
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$s = db()->prepare('SELECT d.*, CONCAT(u.first_name, " ", u.last_name) AS owner FROM documents d LEFT JOIN users u ON u.id = d.owner_id WHERE d.id = ?');
$s->execute([$id]);
$doc = $s->fetch();
if (!$doc || ($doc['status'] !== 'active' && !$canEdit)) { http_response_code(404); exit('Document not found.'); }

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) { http_response_code(403); exit('You do not have access to this action.'); }
    try {
        if (($_POST['action'] ?? '') === 'version') {
            add_version($user, $id, $_POST, $_FILES['file'] ?? []);
            flash('New version uploaded.');
        } else {
            update_document($user, $id, $_POST);
            flash('Document updated.');
        }
        redirect("document?id=$id");
    } catch (UserError $e) {
        $error = $e->getMessage();
    }
}

$vs = db()->prepare('SELECT v.*, CONCAT(u.first_name, " ", u.last_name) AS uploader FROM document_versions v JOIN users u ON u.id = v.uploaded_by WHERE document_id = ? ORDER BY v.id DESC');
$vs->execute([$id]);
$versions = $vs->fetchAll();
$next = count($versions) + 1 . '.0';

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
<p class="muted"><a href="<?= base_url('policies?type=' . $doc['type']) ?>">← <?= h(DOC_TYPES[$doc['type']]) ?></a></p>
<h1 class="title" data-animate><?= h($doc['title']) ?> <span class="badge <?= $doc['status'] === 'active' ? '' : 'off' ?>"><?= h($doc['status']) ?></span></h1>
<p class="muted"><?= h(ucfirst($doc['type'])) ?> · <?= h($doc['category'] ?: 'No category') ?> · owner <?= h($doc['owner'] ?? '—') ?>
  <?php if ($doc['review_due']): ?> · review due <?= h($doc['review_due']) ?><?php endif ?>
  <?php if ($doc['memo_from']): ?> · from <?= h($doc['memo_from']) ?><?php endif ?>
  <?php if ($doc['audience']): ?> · for <?= h($doc['audience']) ?><?php endif ?>
  <?php if ($doc['expires_on']): ?> · <?= $doc['expires_on'] < date('Y-m-d') ? '<span class="badge warn">expired' : '<span class="badge">expires' ?> <?= h($doc['expires_on']) ?></span><?php endif ?>
  <?php if ($doc['sharepoint_url']): ?> · <a href="<?= h($doc['sharepoint_url']) ?>" target="_blank" rel="noopener">SharePoint ↗</a><?php endif ?></p>

<?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif ?>

<div class="panel" data-animate>
  <h2>Version history</h2>
  <div class="table-wrap"><table class="tbl">
    <tr><th>Version</th><th>Effective</th><th>Uploaded</th><th>By</th><th>What changed</th><th></th></tr>
    <?php foreach ($versions as $v): ?>
    <tr>
      <td><?= h($v['version_no']) ?> <?= $v['id'] == $doc['current_version_id'] ? '<span class="badge">current</span>' : '' ?></td>
      <td><?= h($v['effective_date']) ?></td><td><?= h($v['uploaded_at']) ?></td><td><?= h($v['uploader']) ?></td>
      <td><?= h($v['changelog']) ?><?php if ($v !== end($versions)): ?> · <a href="<?= base_url('document?id=' . $id . '&compare=' . $v['id']) ?>#compare">Compare with previous</a><?php endif ?></td>
      <td><a href="<?= base_url('download?v=' . $v['id']) ?>"><?= h($v['original_name']) ?></a></td>
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
      <div class="form-group"><label class="form-label">Effective date</label><input class="form-input" type="date" name="effective_date" required value="<?= h($_POST['effective_date'] ?? date('Y-m-d')) ?>"></div>
      <div class="form-group"><label class="form-label">Next review date</label><input class="form-input" type="date" name="review_due" value="<?= h($_POST['review_due'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">File</label><input class="form-input" type="file" name="file" required></div>
    </div>
    <div class="form-group"><label class="form-label">What changed? (required)</label><input class="form-input" name="changelog" required value="<?= h($_POST['changelog'] ?? '') ?>"></div>
    <button class="btn inline" type="submit">Upload version</button>
  </form>
</details>

<details class="panel" <?= $error && ($_POST['action'] ?? '') !== 'version' ? 'open' : '' ?>>
  <summary>Edit details / archive</summary>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= $id ?>">
    <div class="grid2">
      <div class="form-group"><label class="form-label">Title</label><input class="form-input" name="title" required value="<?= h($doc['title']) ?>"></div>
      <div class="form-group"><label class="form-label">Category</label><input class="form-input" name="category" value="<?= h($doc['category']) ?>"></div>
      <div class="form-group"><label class="form-label">Next review date</label><input class="form-input" type="date" name="review_due" value="<?= h($doc['review_due']) ?>"></div>
      <div class="form-group"><label class="form-label">SharePoint link</label><input class="form-input" name="sharepoint_url" value="<?= h($doc['sharepoint_url']) ?>"></div>
      <?php if ($doc['type'] === 'memo'): ?>
      <div class="form-group"><label class="form-label">Memo from</label><input class="form-input" name="memo_from" value="<?= h($doc['memo_from']) ?>"></div>
      <div class="form-group"><label class="form-label">Audience</label><input class="form-input" name="audience" value="<?= h($doc['audience']) ?>"></div>
      <div class="form-group"><label class="form-label">Expires</label><input class="form-input" type="date" name="expires_on" value="<?= h($doc['expires_on']) ?>"></div>
      <?php endif ?>
      <div class="form-group"><label class="form-label">Status</label>
        <select class="form-input" name="status"><?php foreach (DOC_STATUS as $st): ?><option <?= $doc['status'] === $st ? 'selected' : '' ?>><?= h($st) ?></option><?php endforeach ?></select></div>
    </div>
    <button class="btn inline" type="submit">Save</button>
  </form>
</details>
<?php endif ?>

<div class="panel" data-animate>
  <h2>Activity</h2>
  <table class="tbl">
    <?php foreach ($timeline as $t): ?><tr><td><?= h($t['at']) ?></td><td><?= h(str_replace('_', ' ', $t['action'])) ?></td><td><?= h($t['who'] ?? 'system') ?></td></tr><?php endforeach ?>
  </table>
</div>
<?php require __DIR__ . '/../components/app_close.php';
