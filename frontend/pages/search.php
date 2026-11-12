<?php
// One search box for everything a person may read: documents, processes and glossary terms.
// shortcut: matches titles, categories and process text (steps included); it does not look inside uploaded files.
require_once __DIR__ . '/../../backend/services/documents.php';
require_once __DIR__ . '/../../backend/services/processes.php';
$user = require_login();

$q = mb_substr(trim($_GET['q'] ?? ''), 0, 100);
$docs = $procs = $terms = [];
if (mb_strlen($q) >= 2) {
    $like = "%$q%";
    $st = db()->prepare("SELECT id, type, title, category, status FROM documents WHERE status IN (" . (can_edit_docs($user) ? "'active','draft'" : "'active'") . ")
                         AND (title LIKE ? OR category LIKE ?) ORDER BY title LIMIT 30");
    $st->execute([$like, $like]);
    $docs = $st->fetchAll();
    // Same rule as the Processes page: drafts are shown to admins only, templates are never search results.
    $st = db()->prepare("SELECT p.id, p.name, p.purpose, p.status, d.name AS dept FROM processes p JOIN departments d ON d.id = p.department_id
                         LEFT JOIN process_versions v ON v.id = p.current_version_id
                         WHERE p.status IN (" . ($user['role'] === 'admin' ? "'active','draft'" : "'active'") . ") AND p.kind = 'process'
                         AND (p.name LIKE ? OR p.purpose LIKE ? OR v.snapshot LIKE ?) ORDER BY p.name LIMIT 30");
    $st->execute([$like, $like, $like]);
    $procs = $st->fetchAll();
    $st = db()->prepare('SELECT term, definition FROM glossary WHERE term LIKE ? OR definition LIKE ? ORDER BY term LIMIT 30');
    $st->execute([$like, $like]);
    $terms = $st->fetchAll();
}

$title = 'Search';
require __DIR__ . '/../components/app_open.php';
$draft = fn(array $r) => $r['status'] === 'active' ? '' : ' <span class="badge off">' . h($r['status']) . '</span>';
?>
<div class="page-head"><div>
  <h1 class="title" data-animate>Search</h1>
  <p class="lead">Looks in policies, memos, manuals, forms, processes (including their steps) and the glossary.</p>
</div></div>
<form class="toolbar" method="get" role="search">
  <input class="form-input" type="search" name="q" placeholder="e.g. leave, invoice, onboarding" aria-label="Search" value="<?= h($q) ?>" autofocus>
  <button class="btn inline" type="submit">Search</button>
</form>

<?php if (mb_strlen($q) < 2): ?>
  <div class="empty">Type at least two letters to search.</div>
<?php elseif (!$docs && !$procs && !$terms): ?>
  <div class="empty">Nothing matched “<?= h($q) ?>”. Try a shorter or different word.</div>
<?php else: ?>
  <?php if ($docs): ?><div class="panel" data-animate><h2>Policies and forms <span class="muted">· <?= count($docs) ?></span></h2>
    <ul class="plain" data-results="documents"><?php foreach ($docs as $d): ?><li><a href="<?= base_url('document?id=' . $d['id']) ?>"><?= h($d['title']) ?></a>
      <span class="muted">(<?= h($d['type']) ?><?= $d['category'] !== '' ? ' · ' . h($d['category']) : '' ?>)</span><?= $draft($d) ?></li><?php endforeach ?></ul></div><?php endif ?>
  <?php if ($procs): ?><div class="panel" data-animate><h2>Processes <span class="muted">· <?= count($procs) ?></span></h2>
    <ul class="plain" data-results="processes"><?php foreach ($procs as $p): ?><li><a href="<?= base_url('process?id=' . $p['id']) ?>"><?= h($p['name']) ?></a>
      <span class="muted">(<?= h($p['dept']) ?>)</span><?= $draft($p) ?><div class="muted"><?= h(mb_strimwidth($p['purpose'], 0, 140, '…')) ?></div></li><?php endforeach ?></ul></div><?php endif ?>
  <?php if ($terms): ?><div class="panel" data-animate><h2>Glossary <span class="muted">· <?= count($terms) ?></span></h2>
    <ul class="plain" data-results="glossary"><?php foreach ($terms as $t): ?><li><strong><?= h($t['term']) ?></strong>: <?= h(mb_strimwidth($t['definition'], 0, 200, '…')) ?></li><?php endforeach ?></ul></div><?php endif ?>
<?php endif ?>
<?php require __DIR__ . '/../components/app_close.php';
