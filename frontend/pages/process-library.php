<?php
require_once __DIR__ . '/../../backend/services/processes.php';
$user = require_login();

$slug = trim($_GET['dept'] ?? '');
$dept = null;
if ($slug !== '') {
    $s = db()->prepare('SELECT * FROM departments WHERE slug = ?');
    $s->execute([$slug]);
    if (!($dept = $s->fetch())) { error_page(404, 'Department not found.'); }
}
$canEdit = $dept ? can_edit_process($user, (int) $dept['id']) : false;

$error = null;
if ($dept && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) { error_page(403, 'You do not have access to this action.'); }
    try {
        if (($_POST['action'] ?? '') === 'remove_position') remove_position($user, (int) $dept['id'], (int) $_POST['id']);
        else add_position($user, (int) $dept['id'], $_POST);
        flash('Positions updated.');
        redirect('process-library?dept=' . $dept['slug']);
    } catch (UserError $e) {
        $error = $e->getMessage();
    }
}

$vis = $user['role'] === 'admin' ? "'active','draft'" : "'active'";   // drafts are for editors; keep simple: admin sees drafts everywhere
$q = trim($_GET['q'] ?? '');
$title = $dept ? $dept['name'] : 'Processes';
require __DIR__ . '/../components/app_open.php';

$cols = 'p.id, p.name, p.purpose, p.owner_position, p.status, p.last_reviewed_at, p.cadence, p.kind, v.version_no, JSON_LENGTH(v.snapshot, "$.steps") AS steps, JSON_UNQUOTE(JSON_EXTRACT(v.snapshot, "$.duration")) AS duration, d.name AS dept, d.slug';
$from = 'FROM processes p JOIN departments d ON d.id = p.department_id LEFT JOIN process_versions v ON v.id = p.current_version_id';
?>
<?php if ($dept): ?>
  <?php
    $st = db()->prepare("SELECT $cols $from WHERE p.department_id = ? AND p.kind = 'process' AND (p.status IN ($vis) OR (? AND p.status <> 'archived')) ORDER BY FIELD(p.cadence, 'daily','weekly','monthly','quarterly','yearly','as_needed'), p.name");
    $st->execute([$dept['id'], (int) $canEdit]);
    $rows = $st->fetchAll();
    $byCad = [];
    foreach ($rows as $r) $byCad[$r['cadence']][] = $r;
    // Editors also see this department's templates and the removed (archived) items so they can be restored.
    $tpl = $arch = [];
    if ($canEdit) {
        $x = db()->prepare("SELECT $cols $from WHERE p.department_id = ? AND p.kind = 'template' AND p.status <> 'archived' ORDER BY p.name");
        $x->execute([$dept['id']]);
        $tpl = $x->fetchAll();
        $x = db()->prepare("SELECT $cols $from WHERE p.department_id = ? AND p.status = 'archived' ORDER BY p.name");
        $x->execute([$dept['id']]);
        $arch = $x->fetchAll();
    }
    $pos = db()->prepare('SELECT p.*, r.title AS reports_to FROM positions p LEFT JOIN positions r ON r.id = p.reports_to_id WHERE p.department_id = ? ORDER BY p.title');
    $pos->execute([$dept['id']]);
    $positions = $pos->fetchAll();
  ?>
  <p class="crumbs"><a href="<?= base_url('process-library') ?>">Processes</a> / <?= h($dept['name']) ?></p>
  <div class="page-head"><div>
    <h1 class="title" data-animate><?= h($dept['name']) ?></h1>
    <p class="lead">How work is done in this department. Open a process to see its steps, who does what, and the flowchart.</p>
  </div><div class="actions">
    <?php if ($canEdit): ?><a class="btn inline" href="<?= base_url('process-edit?dept=' . $dept['slug']) ?>">New process</a><?php endif ?>
    <a class="btn ghost inline" href="<?= base_url('handbook?dept=' . $dept['slug']) ?>" title="Everything a new person needs, on one printable page">Start-here handbook</a>
  </div></div>
  <?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif ?>

  <?php
    // One table of processes: name + purpose, owner, how long, steps, last checked. $showCadence adds the cadence badge (used for editor lists).
    $procTable = function (array $list, bool $showCadence = false) { ?>
    <div class="table-wrap"><table class="tbl">
      <tr><th>Process</th><th>Owner</th><th>Takes</th><th>Steps</th><th>Last checked</th></tr>
      <?php foreach ($list as $r): ?>
      <tr>
        <td><a href="<?= base_url('process?id=' . $r['id']) ?>"><?= h($r['name']) ?></a>
          <?php if ($r['status'] !== 'active'): ?><span class="badge off"><?= h($r['status']) ?></span><?php endif ?>
          <?php if ($showCadence): ?><span class="badge off"><?= h(CADENCE[$r['cadence']]) ?></span><?php endif ?>
          <div class="muted"><?= h(mb_strimwidth($r['purpose'], 0, 140, '…')) ?></div></td>
        <td><?= h($r['owner_position'] ?: '—') ?></td><td><?= h($r['duration'] ?: '—') ?></td><td><?= (int) $r['steps'] ?></td><td><?= h($r['last_reviewed_at'] ?? 'never') ?></td>
      </tr>
      <?php endforeach ?>
    </table></div>
  <?php }; ?>

  <?php if ($rows): ?>
  <div class="chips" style="margin:0 0 16px"><?php foreach (CADENCE as $k => $label): if (empty($byCad[$k])) continue; ?><a class="chip" href="#c-<?= $k ?>"><b><?= count($byCad[$k]) ?></b> <?= h(strtolower($label)) ?></a><?php endforeach ?></div>
  <?php foreach (CADENCE as $k => $label): if (empty($byCad[$k])) continue; ?>
  <div class="panel" id="c-<?= $k ?>" data-animate>
    <h2><?= h($label) ?> <span class="muted">· <?= count($byCad[$k]) ?></span></h2>
    <p class="help"><?= ['daily' => 'Done every working day.', 'weekly' => 'Done once a week.', 'monthly' => 'Done once a month.', 'quarterly' => 'Done four times a year.', 'yearly' => 'Done once a year.', 'as_needed' => 'Done when a situation comes up.'][$k] ?></p>
    <?php $procTable($byCad[$k]) ?>
  </div>
  <?php endforeach ?>
  <?php else: ?>
  <div class="empty">No processes written down for this department yet.<?= $canEdit ? ' Use “New process” to add the first one, or start from a template.' : '' ?></div>
  <?php endif ?>

  <?php if ($canEdit && $tpl): ?>
  <div class="panel noprint"><h2>Templates of this department <span class="muted">· <?= count($tpl) ?></span></h2>
    <p class="help">Reusable starting points. They are not shown to staff as processes.</p><?php $procTable($tpl, true) ?></div>
  <?php endif ?>
  <?php if ($canEdit && $arch): ?>
  <details class="panel noprint"><summary>Removed (<?= count($arch) ?>)</summary>
    <p class="help">Hidden from staff but kept with every version. Open one and press Restore to bring it back.</p><?php $procTable($arch, true) ?></details>
  <?php endif ?>

  <div class="panel" data-animate>
    <h2>Who's who in this department</h2>
    <p class="help">The chart shows who reports to whom (top = most senior). New hires can see who to ask.</p>
    <?php if ($positions) require __DIR__ . '/../components/orgchart.php'; ?>
    <div class="table-wrap"><table class="tbl">
      <tr><th>Position</th><th>Held by</th><th>Reports to</th><th></th></tr>
      <?php foreach ($positions as $p): ?>
      <tr><td><?= h($p['title']) ?></td><td><?= h($p['holder_name'] ?: '—') ?></td><td><?= h($p['reports_to'] ?? '—') ?></td>
        <td><?php if ($canEdit): ?><form method="post" onsubmit="return confirm('Remove this position?')">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="remove_position"><input type="hidden" name="id" value="<?= $p['id'] ?>">
          <button class="link linkbtn" type="submit">Remove</button></form><?php endif ?></td></tr>
      <?php endforeach; if (!$positions): ?><tr><td colspan="4" class="muted">No positions listed yet.</td></tr><?php endif ?>
    </table></div>
    <?php if ($canEdit): ?>
    <form method="post" class="toolbar" style="margin:1rem 0 0">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input class="form-input" name="title" placeholder="Position, e.g. Team Lead" aria-label="Position title" required>
      <input class="form-input" name="holder_name" placeholder="Person in that position" aria-label="Person's name">
      <select class="form-input" name="reports_to_id" aria-label="Reports to"><option value="">Reports to…</option>
        <?php foreach ($positions as $p): ?><option value="<?= $p['id'] ?>"><?= h($p['title']) ?></option><?php endforeach ?></select>
      <button class="btn inline" type="submit">Add position</button>
    </form>
    <?php endif ?>
  </div>

<?php else: ?>
  <?php
    $depts = db()->query("SELECT d.*, (SELECT COUNT(*) FROM processes p WHERE p.department_id = d.id AND p.status = 'active' AND p.kind = 'process') AS n FROM departments d ORDER BY d.sort")->fetchAll();
    $found = [];
    if ($q !== '') {
        $st = db()->prepare("SELECT $cols $from WHERE p.status IN ($vis) AND p.kind = 'process' AND (p.name LIKE ? OR p.purpose LIKE ? OR v.snapshot LIKE ?) ORDER BY d.sort, p.name LIMIT 50");
        $st->execute(["%$q%", "%$q%", "%$q%"]);
        $found = $st->fetchAll();
    }
  ?>
  <div class="page-head"><div>
    <h1 class="title" data-animate>Processes</h1>
    <p class="lead">How work gets done, department by department: who does what, in what order. Pick your department, or search for a job.</p>
  </div></div>
  <form class="toolbar" method="get">
    <input class="form-input" name="q" placeholder="Search, e.g. leave, invoice, onboarding" aria-label="Search processes" value="<?= h($q) ?>">
    <button class="btn inline" type="submit">Search</button>
  </form>
  <?php if ($q !== ''): ?>
    <div class="panel table-wrap tight"><table class="tbl" data-animate>
      <tr><th>Process</th><th>Department</th><th>Steps</th></tr>
      <?php foreach ($found as $r): ?><tr><td><a href="<?= base_url('process?id=' . $r['id']) ?>"><?= h($r['name']) ?></a></td><td><?= h($r['dept']) ?></td><td><?= (int) $r['steps'] ?></td></tr>
      <?php endforeach; if (!$found): ?><tr><td colspan="3" class="muted">Nothing matched “<?= h($q) ?>”.</td></tr><?php endif ?>
    </table></div>
  <?php else: ?>
    <div class="cards">
      <?php foreach ($depts as $d): ?>
      <a class="card" data-animate href="<?= base_url('process-library?dept=' . $d['slug']) ?>"><h2><?= h($d['name']) ?></h2><p><?= (int) $d['n'] ?> process<?= $d['n'] == 1 ? '' : 'es' ?><?= $d['n'] ? '' : ' yet' ?></p></a>
      <?php endforeach ?>
    </div>
  <?php endif ?>
<?php endif ?>
<?php require __DIR__ . '/../components/app_close.php';
