<?php
require_once __DIR__ . '/../../backend/services/processes.php';
$user = require_login();

$slug = trim($_GET['dept'] ?? '');
$dept = null;
if ($slug !== '') {
    $s = db()->prepare('SELECT * FROM departments WHERE slug = ?');
    $s->execute([$slug]);
    if (!($dept = $s->fetch())) { http_response_code(404); exit('Department not found.'); }
}
$canEdit = $dept ? can_edit_process($user, (int) $dept['id']) : false;

$error = null;
if ($dept && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) { http_response_code(403); exit('You do not have access to this action.'); }
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
$title = $dept ? $dept['name'] : 'Process Library';
require __DIR__ . '/../components/app_open.php';

$cols = 'p.id, p.name, p.purpose, p.owner_position, p.status, p.last_reviewed_at, v.version_no, JSON_LENGTH(v.snapshot, "$.steps") AS steps, d.name AS dept, d.slug';
$from = 'FROM processes p JOIN departments d ON d.id = p.department_id LEFT JOIN process_versions v ON v.id = p.current_version_id';
?>
<?php if ($dept): ?>
  <?php
    $st = db()->prepare("SELECT $cols $from WHERE p.department_id = ? AND (p.status IN ($vis) OR (? AND p.status <> 'archived')) ORDER BY p.name");
    $st->execute([$dept['id'], (int) $canEdit]);
    $rows = $st->fetchAll();
    $pos = db()->prepare('SELECT p.*, r.title AS reports_to FROM positions p LEFT JOIN positions r ON r.id = p.reports_to_id WHERE p.department_id = ? ORDER BY p.title');
    $pos->execute([$dept['id']]);
    $positions = $pos->fetchAll();
  ?>
  <p class="muted"><a href="<?= base_url('process-library') ?>">← All departments</a></p>
  <h1 class="title" data-animate><?= h($dept['name']) ?></h1>
  <?php if ($canEdit): ?><p style="margin:1rem 0"><a class="btn inline" style="text-decoration:none;display:inline-block" href="<?= base_url('process-edit?dept=' . $dept['slug']) ?>">+ New process</a></p><?php endif ?>
  <?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif ?>

  <div class="table-wrap"><table class="tbl" data-animate>
    <tr><th>Process</th><th>Owner</th><th>Steps</th><th>Version</th><th>Last reviewed</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><a href="<?= base_url('process?id=' . $r['id']) ?>"><?= h($r['name']) ?></a>
        <?php if ($r['status'] !== 'active'): ?><span class="badge off"><?= h($r['status']) ?></span><?php endif ?>
        <div class="muted"><?= h(mb_strimwidth($r['purpose'], 0, 120, '…')) ?></div></td>
      <td><?= h($r['owner_position'] ?: '—') ?></td><td><?= (int) $r['steps'] ?></td><td>v<?= (int) $r['version_no'] ?></td><td><?= h($r['last_reviewed_at'] ?? 'never') ?></td>
    </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="5" class="muted">No processes documented yet.</td></tr><?php endif ?>
  </table></div>

  <div class="panel" data-animate>
    <h2>Who's who in this department</h2>
    <p class="muted">Positions and who holds them — new hires can see who to ask.</p>
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
      <input class="form-input" name="title" placeholder="Position title" required>
      <input class="form-input" name="holder_name" placeholder="Person's name">
      <select class="form-input" name="reports_to_id"><option value="">Reports to…</option>
        <?php foreach ($positions as $p): ?><option value="<?= $p['id'] ?>"><?= h($p['title']) ?></option><?php endforeach ?></select>
      <button class="btn inline" type="submit">Add</button>
    </form>
    <?php endif ?>
  </div>

<?php else: ?>
  <?php
    $depts = db()->query("SELECT d.*, (SELECT COUNT(*) FROM processes p WHERE p.department_id = d.id AND p.status = 'active') AS n FROM departments d ORDER BY d.sort")->fetchAll();
    $found = [];
    if ($q !== '') {
        $st = db()->prepare("SELECT $cols $from WHERE p.status IN ($vis) AND (p.name LIKE ? OR p.purpose LIKE ? OR v.snapshot LIKE ?) ORDER BY d.sort, p.name LIMIT 50");
        $st->execute(["%$q%", "%$q%", "%$q%"]);
        $found = $st->fetchAll();
    }
  ?>
  <h1 class="title" data-animate>Process Library</h1>
  <p class="muted">How work gets done, department by department: who does what, in what order.</p>
  <form class="toolbar" method="get" style="margin-top:1.2rem">
    <input class="form-input" name="q" placeholder="Search all processes and steps" value="<?= h($q) ?>">
    <button class="btn inline" type="submit">Search</button>
  </form>
  <?php if ($q !== ''): ?>
    <div class="table-wrap"><table class="tbl" data-animate>
      <tr><th>Process</th><th>Department</th><th>Steps</th></tr>
      <?php foreach ($found as $r): ?><tr><td><a href="<?= base_url('process?id=' . $r['id']) ?>"><?= h($r['name']) ?></a></td><td><?= h($r['dept']) ?></td><td><?= (int) $r['steps'] ?></td></tr>
      <?php endforeach; if (!$found): ?><tr><td colspan="3" class="muted">Nothing matched “<?= h($q) ?>”.</td></tr><?php endif ?>
    </table></div>
  <?php else: ?>
    <div class="cards">
      <?php foreach ($depts as $d): ?>
      <a class="card" data-animate href="<?= base_url('process-library?dept=' . $d['slug']) ?>"><h3><?= h($d['name']) ?></h3><p><?= (int) $d['n'] ?> process<?= $d['n'] == 1 ? '' : 'es' ?></p></a>
      <?php endforeach ?>
    </div>
  <?php endif ?>
<?php endif ?>
<?php require __DIR__ . '/../components/app_close.php';
