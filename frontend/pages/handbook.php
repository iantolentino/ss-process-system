<?php
// Department handbook: everything a new hire needs in one printable page (who's who + every active process with its flowchart and steps).
require_once __DIR__ . '/../../backend/services/processes.php';
$user = require_login();

$slug = trim($_GET['dept'] ?? '');
if ($slug === '' && $user['department_id']) {
    $s = db()->prepare('SELECT slug FROM departments WHERE id = ?');
    $s->execute([$user['department_id']]);
    $slug = (string) $s->fetchColumn();
}
if ($slug === '') redirect('process-library');   // no department known: pick one from the library

$s = db()->prepare('SELECT * FROM departments WHERE slug = ?');
$s->execute([$slug]);
if (!($dept = $s->fetch())) { http_response_code(404); exit('Department not found.'); }

$ps = db()->prepare("SELECT p.id, p.name, p.purpose, p.owner_position, p.last_reviewed_at, v.version_no, v.snapshot
                     FROM processes p JOIN process_versions v ON v.id = p.current_version_id
                     WHERE p.department_id = ? AND p.status = 'active' ORDER BY p.name");
$ps->execute([$dept['id']]);
$procs = $ps->fetchAll();

$pos = db()->prepare('SELECT p.title, p.holder_name, r.title AS reports_to FROM positions p LEFT JOIN positions r ON r.id = p.reports_to_id WHERE p.department_id = ? ORDER BY p.title');
$pos->execute([$dept['id']]);
$positions = $pos->fetchAll();

$gl = db()->prepare('SELECT term, definition FROM glossary WHERE department_id = ? OR department_id IS NULL ORDER BY term');
$gl->execute([$dept['id']]);
$terms = $gl->fetchAll();

$title = $dept['name'] . ' handbook';
require __DIR__ . '/../components/app_open.php';
?>
<p class="muted noprint"><a href="<?= base_url('process-library?dept=' . $dept['slug']) ?>">← <?= h($dept['name']) ?></a></p>
<h1 class="title" data-animate><?= h($dept['name']) ?> handbook</h1>
<p class="muted">How this team works, step by step. Generated <?= date('Y-m-d') ?> from the live Process Library — print to keep a copy, but check the library for the latest.</p>
<p class="noprint" style="margin:1rem 0"><button class="btn inline" type="button" onclick="print()">Print / save as PDF</button></p>

<div class="panel">
  <h2>Who's who</h2>
  <?php if ($positions): ?>
  <table class="tbl"><tr><th>Position</th><th>Held by</th><th>Reports to</th></tr>
    <?php foreach ($positions as $p): ?><tr><td><?= h($p['title']) ?></td><td><?= h($p['holder_name'] ?: '—') ?></td><td><?= h($p['reports_to'] ?? '—') ?></td></tr><?php endforeach ?>
  </table>
  <?php else: ?><p class="muted">No positions listed yet.</p><?php endif ?>
</div>

<div class="panel">
  <h2>Contents</h2>
  <ol><?php foreach ($procs as $p): ?><li><a href="#p<?= $p['id'] ?>"><?= h($p['name']) ?></a></li><?php endforeach ?></ol>
  <?php if (!$procs): ?><p class="muted">No processes documented yet.</p><?php endif ?>
</div>

<?php foreach ($procs as $p):
    $snap = json_decode($p['snapshot'], true);
    $steps = $snap['steps'];
    $related = related_documents($snap['docs'] ?? []);
?>
<section class="proc-block" id="p<?= $p['id'] ?>">
  <h2 class="title" style="font-size:1.6rem"><?= h($p['name']) ?></h2>
  <p><?= nl2br(h($p['purpose'])) ?></p>
  <p class="muted">
    <?php if ($p['owner_position']): ?>Owner: <strong><?= h($p['owner_position']) ?></strong> · <?php endif ?>
    <?php if ($snap['frequency']): ?>Runs: <?= h($snap['frequency']) ?> · <?php endif ?>
    <?php if ($snap['duration']): ?>Takes: <?= h($snap['duration']) ?> · <?php endif ?>
    Version <?= (int) $p['version_no'] ?> · Reviewed: <?= h($p['last_reviewed_at'] ?? 'never') ?></p>
  <?php if ($snap['trigger']): ?><p class="muted">Starts when: <?= h($snap['trigger']) ?></p><?php endif ?>
  <div class="panel"><?php require __DIR__ . '/../components/flowchart.php'; ?></div>
  <table class="tbl">
    <tr><th>#</th><th>Step</th><th>Who</th><th>What they do</th><th>Tools</th><th>Output</th></tr>
    <?php foreach ($steps as $i => $st): ?>
    <tr><td><?= $i + 1 ?></td><td><strong><?= h($st['title']) ?></strong><?= $st['decision'] ? ' <span class="badge off">decision</span>' : '' ?></td>
      <td><?= h(trim($st['role'] . ($st['person'] ? ' — ' . $st['person'] : '')) ?: '—') ?></td>
      <td><?= nl2br(h($st['detail'])) ?></td><td><?= h($st['tools'] ?: '—') ?></td><td><?= h($st['output'] ?: '—') ?></td></tr>
    <?php endforeach ?>
  </table>
  <?php if ($snap['notes']): ?><p style="margin-top:.8rem"><strong>Tips:</strong> <?= nl2br(h($snap['notes'])) ?></p><?php endif ?>
  <?php if ($related): ?><p class="muted" style="margin-top:.6rem">Related: <?php foreach ($related as $k => $d): ?><?= $k ? ', ' : '' ?><a href="<?= base_url('document?id=' . $d['id']) ?>"><?= h($d['title']) ?></a><?php endforeach ?></p><?php endif ?>
</section>
<?php endforeach ?>
<?php if ($terms): ?>
<section class="proc-block glossary">
  <h2 class="title" style="font-size:1.6rem">Glossary</h2>
  <table class="tbl"><?php foreach ($terms as $t): ?><tr><td><strong><?= h($t['term']) ?></strong></td><td><?= nl2br(h($t['definition'])) ?></td></tr><?php endforeach ?></table>
</section>
<?php endif ?>
<?php require __DIR__ . '/../components/app_close.php';
