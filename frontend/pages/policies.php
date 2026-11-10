<?php
require_once __DIR__ . '/../../backend/services/documents.php';
$user = require_login();
$canEdit = can_edit_docs($user);
$type = isset(DOC_TYPES[$_GET['type'] ?? '']) ? $_GET['type'] : 'policy';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) { http_response_code(403); exit('You do not have access to this action.'); }
    try {
        $id = create_document($user, $_POST, $_FILES['file'] ?? []);
        flash('Document created.');
        redirect("document?id=$id");
    } catch (UserError $e) {
        $error = $e->getMessage();
        $type = isset(DOC_TYPES[$_POST['type'] ?? '']) ? $_POST['type'] : $type;
    }
}

$q = trim($_GET['q'] ?? '');
$cat = trim($_GET['category'] ?? '');
$statuses = $canEdit ? (isset($_GET['archived']) ? "'active','draft','archived'" : "'active','draft'") : "'active'";
$sql = "SELECT d.id, d.title, d.category, d.status, d.review_due, d.expires_on, d.audience, v.version_no, v.effective_date, v.uploaded_at
        FROM documents d LEFT JOIN document_versions v ON v.id = d.current_version_id
        WHERE d.type = ? AND d.status IN ($statuses)";
$args = [$type];
if ($q !== '') { $sql .= ' AND (d.title LIKE ? OR d.category LIKE ?)'; array_push($args, "%$q%", "%$q%"); }
if ($cat !== '') { $sql .= ' AND d.category = ?'; $args[] = $cat; }
const PER_PAGE = 50;
$pg = max(1, (int) ($_GET['p'] ?? 1));
$st = db()->prepare($sql . ' ORDER BY d.title LIMIT ' . (PER_PAGE + 1) . ' OFFSET ' . ($pg - 1) * PER_PAGE);
$st->execute($args);
$docs = $st->fetchAll();
$pgMore = count($docs) > PER_PAGE;
$docs = array_slice($docs, 0, PER_PAGE);
$pgPath = 'policies';
$pgParams = array_filter(['type' => $type, 'q' => $q, 'category' => $cat, 'archived' => isset($_GET['archived']) ? 1 : '']);
$cats = db()->query("SELECT DISTINCT category FROM documents WHERE category <> '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

$title = 'Policies & forms';
require __DIR__ . '/../components/app_open.php';
$qs = fn(string $t) => base_url('policies?type=' . $t);
?>
<div class="page-head"><div>
  <h1 class="title" data-animate>Policies &amp; forms</h1>
  <p class="lead">Company policies, memos, manuals and forms. Open one to read it, download it, or see older versions.</p>
</div></div>
<nav class="tabs" aria-label="Document type"><?php foreach (DOC_TYPES as $k => $label): ?>
  <a href="<?= $qs($k) ?>" class="<?= $k === $type ? 'active' : '' ?>"><?= h($label) ?></a><?php endforeach ?></nav>

<form class="toolbar" method="get">
  <input type="hidden" name="type" value="<?= h($type) ?>">
  <input class="form-input" name="q" placeholder="Search title or category" value="<?= h($q) ?>">
  <select class="form-input" name="category"><option value="">All categories</option>
    <?php foreach ($cats as $c): ?><option <?= $c === $cat ? 'selected' : '' ?>><?= h($c) ?></option><?php endforeach ?></select>
  <?php if ($canEdit): ?><label class="muted" style="align-self:center"><input type="checkbox" name="archived" value="1" <?= isset($_GET['archived']) ? 'checked' : '' ?>> show archived</label><?php endif ?>
  <button class="btn inline" type="submit">Search</button>
</form>

<?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif ?>
<?php if ($canEdit): ?>
<details class="panel" <?= $error || isset($_GET['add']) ? 'open' : '' ?>>
  <summary>Add a document</summary>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="grid2">
      <div class="form-group"><label class="form-label">Type</label>
        <select class="form-input" name="type"><?php foreach (DOC_TYPES as $k => $label): ?><option value="<?= $k ?>" <?= $k === $type ? 'selected' : '' ?>><?= h(ucfirst($k)) ?></option><?php endforeach ?></select></div>
      <div class="form-group"><label class="form-label">Title</label><input class="form-input" name="title" required value="<?= h($_POST['title'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Category</label><input class="form-input" name="category" list="cats" value="<?= h($_POST['category'] ?? '') ?>">
        <datalist id="cats"><?php foreach ($cats as $c): ?><option value="<?= h($c) ?>"><?php endforeach ?></datalist></div>
      <div class="form-group"><label class="form-label">Version</label><input class="form-input" name="version_no" required value="<?= h($_POST['version_no'] ?? '1.0') ?>"><p class="help">Your own numbering, e.g. 1.0 or 2024-03.</p></div>
      <div class="form-group"><label class="form-label">Starts to apply on</label><input class="form-input" type="date" name="effective_date" required value="<?= h($_POST['effective_date'] ?? date('Y-m-d')) ?>"></div>
      <div class="form-group memo-only" hidden><label class="form-label">Memo from</label><input class="form-input" name="memo_from" value="<?= h($_POST['memo_from'] ?? '') ?>"></div>
      <div class="form-group memo-only" hidden><label class="form-label">Memo is for (audience)</label><input class="form-input" name="audience" placeholder="e.g. All staff, Finance team" value="<?= h($_POST['audience'] ?? '') ?>"></div>
      <div class="form-group memo-only" hidden><label class="form-label">Memo expires (optional)</label><input class="form-input" type="date" name="expires_on" value="<?= h($_POST['expires_on'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">File</label><input class="form-input" type="file" name="file" required><p class="help"><?= h(implode(', ', DOC_EXT)) ?> · up to 20 MB</p></div>
    </div>
    <details class="step-more" style="margin-bottom:16px"><summary class="muted">More options (review date, SharePoint link, note)</summary>
      <div class="grid2" style="margin-top:12px">
        <div class="form-group"><label class="form-label">Check again on</label><input class="form-input" type="date" name="review_due" value="<?= h($_POST['review_due'] ?? '') ?>"><p class="help">Shows on Home when it is due.</p></div>
        <div class="form-group"><label class="form-label">SharePoint link</label><input class="form-input" name="sharepoint_url" value="<?= h($_POST['sharepoint_url'] ?? '') ?>"></div>
      </div>
      <div class="form-group"><label class="form-label">Note about this version</label><input class="form-input" name="changelog" value="<?= h($_POST['changelog'] ?? '') ?>" placeholder="Initial version"></div>
    </details>
    <button class="btn inline" type="submit">Upload document</button>
  </form>
  <script>
    // Memo-only fields show when the type is Memo.
    const sel = document.querySelector('select[name=type]');
    const sync = () => document.querySelectorAll('.memo-only').forEach(el => el.hidden = sel.value !== 'memo');
    sel.addEventListener('change', sync); sync();
  </script>
</details>
<?php endif ?>

<div class="panel table-wrap" style="padding:4px 8px"><table class="tbl" data-animate>
  <tr><th>Title</th><th>Category</th><th>Version</th><th>Effective</th><th>Review due</th></tr>
  <?php foreach ($docs as $d): $over = $d['review_due'] && $d['review_due'] < date('Y-m-d'); ?>
  <tr>
    <td><a href="<?= base_url('document?id=' . $d['id']) ?>"><?= h($d['title']) ?></a>
      <?php if ($d['status'] !== 'active'): ?> <span class="badge off"><?= h($d['status']) ?></span><?php endif ?>
      <?php if ($d['expires_on'] && $d['expires_on'] < date('Y-m-d')): ?> <span class="badge warn">expired <?= h($d['expires_on']) ?></span><?php endif ?>
      <?php if ($d['audience']): ?><div class="muted">For: <?= h($d['audience']) ?></div><?php endif ?></td>
    <td><?= h($d['category'] ?: '—') ?></td><td><?= h($d['version_no'] ?? '—') ?></td><td><?= h($d['effective_date'] ?? '—') ?></td>
    <td><?= $d['review_due'] ? '<span class="badge ' . ($over ? 'warn' : '') . '">' . h($d['review_due']) . '</span>' : '—' ?></td>
  </tr>
  <?php endforeach; if (!$docs): ?><tr><td colspan="5" class="muted">No <?= h(strtolower(DOC_TYPES[$type])) ?> found.</td></tr><?php endif ?>
</table></div>
<?php require __DIR__ . '/../components/pager.php'; ?>
<?php require __DIR__ . '/../components/app_close.php';
