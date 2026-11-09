<?php
require_once __DIR__ . '/../../backend/services/onboarding.php';
$user = require_login();

$slug = trim($_GET['dept'] ?? '');
if ($slug === '' && $user['department_id']) {
    $s = db()->prepare('SELECT slug FROM departments WHERE id = ?');
    $s->execute([$user['department_id']]);
    $slug = (string) $s->fetchColumn();
}
if ($slug === '') redirect('process-library');
$s = db()->prepare('SELECT * FROM departments WHERE slug = ?');
$s->execute([$slug]);
if (!($dept = $s->fetch())) { http_response_code(404); exit('Department not found.'); }

$deptId = (int) $dept['id'];
$mine = (int) $user['department_id'] === $deptId;       // checklist is personal: only for your own department
$manage = can_edit_process($user, $deptId);              // admin or this department's process owner

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $act = $_POST['action'] ?? '';
        if ($act === 'toggle' && $mine) set_item_done($user, (int) $_POST['item_id'], !empty($_POST['done']));
        elseif ($manage && $act === 'add') { add_item($user, $deptId, $_POST); flash('Checklist item added.'); }
        elseif ($manage && $act === 'delete') { delete_item($user, $deptId, (int) $_POST['item_id']); flash('Checklist item deleted.'); }
        else { http_response_code(403); exit('You do not have access to this action.'); }
        redirect('onboarding?dept=' . $slug);
    } catch (UserError $e) {
        $error = $e->getMessage();
    }
}

$items = $mine ? items_for($user) : [];
$done = count(array_filter($items, fn($i) => $i['done_at']));
$weeks = [];
foreach ($items as $i) $weeks[(int) $i['week_no']][] = $i;

if ($manage) {
    $all = db()->prepare('SELECT i.*, po.title AS position FROM onboarding_items i LEFT JOIN positions po ON po.id = i.position_id
                          WHERE i.department_id = ? ORDER BY (i.week_no = 0), i.week_no, i.id');
    $all->execute([$deptId]);
    $allItems = $all->fetchAll();
    $team = team_progress($deptId);
    $pos = db()->prepare('SELECT id, title FROM positions WHERE department_id = ? ORDER BY title');
    $pos->execute([$deptId]);
    $positions = $pos->fetchAll();
    $procs = db()->query("SELECT id, name FROM processes WHERE status = 'active' AND department_id = $deptId ORDER BY name")->fetchAll();
    $docs = db()->query("SELECT id, type, title FROM documents WHERE status = 'active' ORDER BY type, title")->fetchAll();
}

$title = 'Onboarding';
require __DIR__ . '/../components/app_open.php';
$weekLabel = fn($w) => $w === 0 ? 'Ongoing' : "Week $w";
?>
<h1 class="title" data-animate>Onboarding <span class="muted">· <?= h($dept['name']) ?></span></h1>
<?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif ?>

<?php if ($mine): ?>
  <?php if ($items): $pct = (int) round($done / count($items) * 100); ?>
  <p class="muted"><?= $done ?> of <?= count($items) ?> done (<?= $pct ?>%)</p>
  <div class="bar" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><span style="width:<?= $pct ?>%"></span></div>
  <?php foreach ($weeks as $w => $list): ?>
  <div class="panel" data-animate><h2><?= $weekLabel($w) ?></h2>
    <?php foreach ($list as $i): ?>
    <form method="post" class="check">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="item_id" value="<?= $i['id'] ?>">
      <label><input type="checkbox" name="done" value="1" <?= $i['done_at'] ? 'checked' : '' ?> onchange="this.form.submit()">
        <span class="<?= $i['done_at'] ? 'done' : '' ?>"><strong><?= h($i['title']) ?></strong></span></label>
      <?php if ($i['position']): ?><span class="badge off"><?= h($i['position']) ?></span><?php endif ?>
      <?php if ($i['detail']): ?><div class="muted"><?= nl2br(h($i['detail'])) ?></div><?php endif ?>
      <div class="muted">
        <?php if ($i['process_name']): ?>Process: <a href="<?= base_url('process?id=' . $i['process_id']) ?>"><?= h($i['process_name']) ?></a> <?php endif ?>
        <?php if ($i['doc_title']): ?>Read: <a href="<?= base_url('document?id=' . $i['document_id']) ?>"><?= h($i['doc_title']) ?></a><?php endif ?></div>
    </form>
    <?php endforeach ?>
  </div>
  <?php endforeach ?>
  <p class="muted"><a href="<?= base_url('handbook?dept=' . $slug) ?>">Department handbook</a> · <a href="<?= base_url('glossary') ?>">Glossary</a></p>
  <?php else: ?><p class="muted">No checklist has been set up for your department yet.</p><?php endif ?>
<?php endif ?>

<?php if ($manage): ?>
<div class="panel" data-animate>
  <h2>Team progress</h2>
  <table class="tbl"><tr><th>Person</th><th>Position</th><th>Done</th></tr>
    <?php foreach ($team as $m): ?><tr><td><?= h($m['name']) ?></td><td><?= h($m['position'] ?? '—') ?></td><td><?= (int) $m['done'] ?> / <?= (int) $m['total'] ?></td></tr>
    <?php endforeach; if (!$team): ?><tr><td colspan="3" class="muted">No one is assigned to this department yet (set it on the Users page).</td></tr><?php endif ?>
  </table>
</div>

<details class="panel" <?= $error ? 'open' : '' ?>><summary>Manage the checklist for <?= h($dept['name']) ?></summary>
  <table class="tbl" style="margin:1rem 0">
    <tr><th>When</th><th>Item</th><th>For</th><th></th></tr>
    <?php foreach ($allItems as $i): ?>
    <tr><td><?= $weekLabel((int) $i['week_no']) ?></td><td><?= h($i['title']) ?></td><td><?= h($i['position'] ?? 'Everyone') ?></td>
      <td><form method="post" onsubmit="return confirm('Delete this item for everyone?')"><input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="delete"><input type="hidden" name="item_id" value="<?= $i['id'] ?>"><button class="link linkbtn" type="submit">Delete</button></form></td></tr>
    <?php endforeach; if (!$allItems): ?><tr><td colspan="4" class="muted">No items yet.</td></tr><?php endif ?>
  </table>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="add">
    <div class="grid2">
      <div class="form-group"><label class="form-label">Item</label><input class="form-input" name="title" required placeholder="e.g. Shadow a support call"></div>
      <div class="form-group"><label class="form-label">Week (0 = ongoing)</label><input class="form-input" type="number" name="week_no" min="0" max="12" value="1"></div>
      <div class="form-group"><label class="form-label">For position (blank = everyone)</label>
        <select class="form-input" name="position_id"><option value="">Everyone in the department</option>
          <?php foreach ($positions as $p): ?><option value="<?= $p['id'] ?>"><?= h($p['title']) ?></option><?php endforeach ?></select></div>
      <div class="form-group"><label class="form-label">Link a process (optional)</label>
        <select class="form-input" name="process_id"><option value="">—</option>
          <?php foreach ($procs as $p): ?><option value="<?= $p['id'] ?>"><?= h($p['name']) ?></option><?php endforeach ?></select></div>
      <div class="form-group"><label class="form-label">Link a policy / form (optional)</label>
        <select class="form-input" name="document_id"><option value="">—</option>
          <?php foreach ($docs as $d): ?><option value="<?= $d['id'] ?>"><?= h(ucfirst($d['type']) . ': ' . $d['title']) ?></option><?php endforeach ?></select></div>
    </div>
    <div class="form-group"><label class="form-label">Details (optional)</label><textarea class="form-input" name="detail" rows="2"></textarea></div>
    <button class="btn inline" type="submit">Add item</button>
  </form>
</details>
<?php endif ?>
<?php if (!$mine && !$manage): ?><p class="muted">This checklist belongs to another department.</p><?php endif ?>
<?php require __DIR__ . '/../components/app_close.php';
