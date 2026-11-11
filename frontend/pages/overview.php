<?php
// Company overview for executives: every department's live processes by how often they happen.
require_once __DIR__ . '/../../backend/services/processes.php';
$user = require_login();

$c = $_GET['c'] ?? '';
if ($c !== '' && !isset(CADENCE[$c])) $c = '';

$rows = db()->query("SELECT p.id, p.name, p.purpose, p.owner_position, p.cadence, p.last_reviewed_at, d.id AS did, d.name AS dept, d.slug,
        JSON_LENGTH(v.snapshot, '$.steps') AS steps, JSON_UNQUOTE(JSON_EXTRACT(v.snapshot, '$.duration')) AS duration
    FROM processes p JOIN departments d ON d.id = p.department_id JOIN process_versions v ON v.id = p.current_version_id
    WHERE p.status = 'active' AND p.kind = 'process' ORDER BY d.sort, d.name, p.name")->fetchAll();

$depts = [];   // department id => ['name', 'slug', 'n' => [cadence => count], 'rows' => []]
foreach ($rows as $r) {
    $d = &$depts[$r['did']];
    $d ??= ['name' => $r['dept'], 'slug' => $r['slug'], 'n' => [], 'rows' => []];
    $d['n'][$r['cadence']] = ($d['n'][$r['cadence']] ?? 0) + 1;
    if ($c === '' || $r['cadence'] === $c) $d['rows'][] = $r;
    unset($d);
}
$totals = array_fill_keys(array_keys(CADENCE), 0);
foreach ($depts as $d) foreach ($d['n'] as $k => $n) $totals[$k] += $n;

$title = 'Company overview';
require __DIR__ . '/../components/app_open.php';
?>
<div class="page-head"><div>
  <h1 class="title" data-animate>Company overview</h1>
  <p class="lead">How every department works, by how often things happen. Pick a column to see what is done daily, weekly, monthly and so on. Click a process for its steps, people and flowchart.</p>
</div><div class="actions noprint"><button class="btn ghost inline" type="button" onclick="print()">Print</button></div></div>

<div class="panel" data-animate>
  <h2>Processes per department</h2>
  <div class="table-wrap"><table class="tbl matrix">
    <tr><th>Department</th><?php foreach (CADENCE as $k => $label): ?><th><a href="<?= base_url('overview?c=' . $k) ?>"><?= h($label) ?></a></th><?php endforeach ?></tr>
    <?php foreach ($depts as $d): ?>
    <tr><td><a href="<?= base_url('process-library?dept=' . $d['slug']) ?>"><?= h($d['name']) ?></a></td>
      <?php foreach (CADENCE as $k => $label): $n = $d['n'][$k] ?? 0; ?><td><?= $n ? '<span class="count">' . $n . '</span>' : '<span class="muted">·</span>' ?></td><?php endforeach ?></tr>
    <?php endforeach; if (!$depts): ?><tr><td colspan="7" class="muted">No processes have been written down yet.</td></tr><?php endif ?>
    <?php if ($depts): ?><tr class="total"><td>All departments</td><?php foreach ($totals as $n): ?><td><b><?= $n ?></b></td><?php endforeach ?></tr><?php endif ?>
  </table></div>
</div>

<nav class="tabs noprint" aria-label="Filter by how often">
  <a href="<?= base_url('overview') ?>" class="<?= $c === '' ? 'active' : '' ?>">All</a>
  <?php foreach (CADENCE as $k => $label): ?><a href="<?= base_url('overview?c=' . $k) ?>" class="<?= $c === $k ? 'active' : '' ?>"><?= h($label) ?> <span class="muted"><?= $totals[$k] ?></span></a><?php endforeach ?>
</nav>

<?php $shown = 0; foreach ($depts as $d): if (!$d['rows']) continue; $shown++; ?>
<div class="panel" data-animate>
  <h2><a href="<?= base_url('process-library?dept=' . $d['slug']) ?>"><?= h($d['name']) ?></a> <span class="muted">· <?= count($d['rows']) ?></span></h2>
  <div class="table-wrap"><table class="tbl">
    <tr><th>Process</th><th>How often</th><th>Owner</th><th>Takes</th><th>Steps</th><th>Last checked</th></tr>
    <?php foreach ($d['rows'] as $r): ?>
    <tr><td><a href="<?= base_url('process?id=' . $r['id']) ?>"><?= h($r['name']) ?></a><div class="muted"><?= h(mb_strimwidth($r['purpose'], 0, 120, '…')) ?></div></td>
      <td><?= h(CADENCE[$r['cadence']]) ?></td><td><?= h($r['owner_position'] ?: '—') ?></td><td><?= h($r['duration'] ?: '—') ?></td><td><?= (int) $r['steps'] ?></td><td><?= h($r['last_reviewed_at'] ?? 'never') ?></td></tr>
    <?php endforeach ?>
  </table></div>
</div>
<?php endforeach; if (!$shown && $depts): ?><div class="empty">No <?= h(strtolower(CADENCE[$c] ?? '')) ?> processes have been written down yet.</div><?php endif ?>
<?php require __DIR__ . '/../components/app_close.php';
