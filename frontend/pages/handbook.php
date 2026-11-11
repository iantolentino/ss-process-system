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

$ps = db()->prepare("SELECT p.id, p.name, p.purpose, p.owner_position, p.cadence, p.last_reviewed_at, v.version_no, v.snapshot
                     FROM processes p JOIN process_versions v ON v.id = p.current_version_id
                     WHERE p.department_id = ? AND p.status = 'active' AND p.kind = 'process' ORDER BY FIELD(p.cadence, 'daily','weekly','monthly','quarterly','yearly','as_needed'), p.name");
$ps->execute([$dept['id']]);
$procs = $ps->fetchAll();

$pos = db()->prepare('SELECT p.id, p.reports_to_id, p.title, p.holder_name, r.title AS reports_to FROM positions p LEFT JOIN positions r ON r.id = p.reports_to_id WHERE p.department_id = ? ORDER BY p.title');
$pos->execute([$dept['id']]);
$positions = $pos->fetchAll();

$gl = db()->prepare('SELECT term, definition FROM glossary WHERE department_id = ? OR department_id IS NULL ORDER BY term');
$gl->execute([$dept['id']]);
$terms = $gl->fetchAll();

$title = $dept['name'] . ' handbook';
require __DIR__ . '/../components/app_open.php';
?>
<p class="crumbs noprint"><a href="<?= base_url('process-library') ?>">Processes</a> / <a href="<?= base_url('process-library?dept=' . $dept['slug']) ?>"><?= h($dept['name']) ?></a></p>
<div class="page-head"><div>
  <h1 class="title" data-animate><?= h($dept['name']) ?> handbook</h1>
  <p class="lead">Start here if you are new: who is who, then every process in this department, step by step. Made on <?= date('j M Y') ?> from the live site. Print it to keep a copy, but check the site for the latest.</p>
</div><div class="actions noprint"><button class="btn inline" type="button" onclick="print()">Print or save as PDF</button></div></div>

<div class="panel">
  <h2>Who's who</h2>
  <?php if ($positions): require __DIR__ . '/../components/orgchart.php'; ?>
  <table class="tbl"><tr><th>Position</th><th>Held by</th><th>Reports to</th></tr>
    <?php foreach ($positions as $p): ?><tr><td><?= h($p['title']) ?></td><td><?= h($p['holder_name'] ?: '—') ?></td><td><?= h($p['reports_to'] ?? '—') ?></td></tr><?php endforeach ?>
  </table>
  <?php else: ?><p class="muted">No positions have been added yet.</p><?php endif ?>
</div>

<div class="panel">
  <h2>Contents</h2>
  <ol><?php foreach ($procs as $p): ?><li><a href="#p<?= $p['id'] ?>"><?= h($p['name']) ?></a></li><?php endforeach ?></ol>
  <?php if (!$procs): ?><p class="muted">No processes have been written down for this department yet.</p><?php endif ?>
</div>

<?php foreach ($procs as $p):
    $snap = json_decode($p['snapshot'], true);
    $steps = $snap['steps'];
    $related = related_documents($snap['docs'] ?? []);
?>
<?php if ($p['cadence'] !== ($lastCad ?? null)): $lastCad = $p['cadence']; ?><h2 class="section-title" style="font-size:15px;color:var(--foreground)"><?= h(CADENCE[$p['cadence']]) ?> processes</h2><?php endif ?>
<section class="proc-block" id="p<?= $p['id'] ?>">
  <h2 class="title" style="font-size:1.6rem"><?= h($p['name']) ?> <span class="badge off"><?= h(CADENCE[$p['cadence']]) ?></span></h2>
  <p><?= nl2br(h($p['purpose'])) ?></p>
  <p class="muted">
    <?php if ($p['owner_position']): ?>Owner: <strong><?= h($p['owner_position']) ?></strong> · <?php endif ?>
    <?php if ($snap['frequency']): ?>Happens: <?= h($snap['frequency']) ?> · <?php endif ?>
    <?php if ($snap['duration']): ?>Takes: <?= h($snap['duration']) ?> · <?php endif ?>
    Version <?= (int) $p['version_no'] ?> · Last checked: <?= h($p['last_reviewed_at'] ?? 'never') ?></p>
  <?php if ($snap['trigger']): ?><p class="muted">Starts when: <?= h($snap['trigger']) ?></p><?php endif ?>
  <?php $facts = ['Needed before starting' => $snap['inputs'] ?? '', 'How we know it went well' => $snap['kpi'] ?? '', 'What can go wrong' => $snap['exceptions'] ?? '', 'Who to ask when stuck' => $snap['escalation'] ?? '']; if (array_filter($facts)): ?>
  <dl class="facts" style="margin:12px 0"><?php foreach ($facts as $k => $v): if ($v === '') continue; ?><div><dt><?= h($k) ?></dt><dd><?= nl2br(h($v)) ?></dd></div><?php endforeach ?></dl><?php endif ?>
  <div class="panel"><?php require __DIR__ . '/../components/flowchart.php'; ?></div>
  <table class="tbl">
    <?php $hasWho = (bool) array_filter($steps, fn($x) => $x['role'] . $x['person'] !== ''); $hasTools = (bool) array_filter($steps, fn($x) => $x['tools'] !== ''); $hasOut = (bool) array_filter($steps, fn($x) => $x['output'] !== ''); ?>
    <tr><th>#</th><th>Step</th><?php if ($hasWho): ?><th>Who</th><?php endif ?><th>What they do</th><?php if ($hasTools): ?><th>Tools</th><?php endif ?><?php if ($hasOut): ?><th>Result</th><?php endif ?></tr>
    <?php foreach ($steps as $i => $st): ?>
    <tr><td><?= $i + 1 ?></td><td><strong><?= h($st['title']) ?></strong><?= $st['decision'] ? ' <span class="badge off">decision</span>' : '' ?></td>
      <?php if ($hasWho): ?><td><?= h(trim($st['role'] . ($st['person'] ? ' — ' . $st['person'] : '')) ?: '—') ?></td><?php endif ?>
      <td><?= nl2br(h($st['detail'])) ?></td><?php if ($hasTools): ?><td><?= h($st['tools'] ?: '—') ?></td><?php endif ?><?php if ($hasOut): ?><td><?= h($st['output'] ?: '—') ?></td><?php endif ?></tr>
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
