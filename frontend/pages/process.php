<?php
require_once __DIR__ . '/../../backend/services/processes.php';
$user = require_login();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$s = db()->prepare('SELECT p.*, d.name AS dept, d.slug FROM processes p JOIN departments d ON d.id = p.department_id WHERE p.id = ?');
$s->execute([$id]);
$proc = $s->fetch();
$canEdit = $proc && can_edit_process($user, (int) $proc['department_id']);
if (!$proc || ($proc['status'] !== 'active' && !$canEdit && $user['role'] !== 'admin')) { http_response_code(404); exit('Process not found.'); }

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $act = $_POST['action'] ?? '';
        if ($act === 'request') { add_change_request($user, $id, $_POST); flash('Change request sent to the process owner.'); }
        elseif ($canEdit && $act === 'review') { mark_reviewed($user, $id); flash('Marked as reviewed today.'); }
        elseif ($canEdit && $act === 'resolve') { resolve_change_request($user, (int) $_POST['request_id'], $_POST['status'] ?? ''); flash('Request updated.'); }
        else { http_response_code(403); exit('You do not have access to this action.'); }
        redirect("process?id=$id");
    } catch (UserError $e) {
        $error = $e->getMessage();
    }
}

$vs = db()->prepare('SELECT v.id, v.version_no, v.changelog, v.created_at, CONCAT(u.first_name, " ", u.last_name) AS who
                     FROM process_versions v JOIN users u ON u.id = v.created_by WHERE v.process_id = ? ORDER BY v.version_no DESC');
$vs->execute([$id]);
$versions = $vs->fetchAll();

$want = (int) ($_GET['v'] ?? 0);
$ver = null;
foreach ($versions as $v) if ($want ? $v['version_no'] == $want : $v['id'] == $proc['current_version_id']) $ver = $v;
if (!$ver) { http_response_code(404); exit('Version not found.'); }
$isOld = $ver['id'] != $proc['current_version_id'];
$q = db()->prepare('SELECT snapshot FROM process_versions WHERE id = ?');
$q->execute([$ver['id']]);
$snap = json_decode($q->fetchColumn(), true);
$steps = $snap['steps'];

// People involved, in order of first appearance.
$people = [];
foreach ($steps as $i => $st) {
    $key = $st['role'] . '|' . $st['person'];
    if ($key === '|') continue;
    $people[$key] ??= ['role' => $st['role'], 'person' => $st['person'], 'n' => []];
    $people[$key]['n'][] = $i + 1;
}

$rq = db()->prepare('SELECT c.*, CONCAT(u.first_name, " ", u.last_name) AS who FROM change_requests c JOIN users u ON u.id = c.requested_by
                     WHERE c.process_id = ? AND (? OR c.requested_by = ?) ORDER BY c.id DESC LIMIT 20');
$rq->execute([$id, (int) $canEdit, $user['id']]);
$requests = $rq->fetchAll();

$title = $proc['name'];
require __DIR__ . '/../components/app_open.php';
?>
<p class="muted noprint"><a href="<?= base_url('process-library?dept=' . $proc['slug']) ?>">← <?= h($proc['dept']) ?></a></p>
<h1 class="title" data-animate><?= h($proc['name']) ?>
  <span class="badge <?= $proc['status'] === 'active' ? '' : 'off' ?>"><?= h($proc['status']) ?></span></h1>
<p><?= nl2br(h($proc['purpose'])) ?></p>
<p class="muted" style="margin-top:.6rem">
  <?php if ($proc['owner_position']): ?>Owner: <strong><?= h($proc['owner_position']) ?></strong> · <?php endif ?>
  <?php if ($snap['frequency']): ?>Runs: <?= h($snap['frequency']) ?> · <?php endif ?>
  <?php if ($snap['duration']): ?>Takes: <?= h($snap['duration']) ?> · <?php endif ?>
  Version <?= (int) $ver['version_no'] ?> · Last reviewed: <?= h($proc['last_reviewed_at'] ?? 'never') ?></p>
<?php if ($snap['trigger']): ?><p class="muted">Starts when: <?= h($snap['trigger']) ?></p><?php endif ?>

<p class="noprint" style="margin:1rem 0;display:flex;gap:10px;flex-wrap:wrap">
  <?php if ($canEdit && !$isOld): ?><a class="btn inline" style="text-decoration:none" href="<?= base_url('process-edit?id=' . $id) ?>">Edit process</a>
    <form method="post"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="review">
      <button class="btn ghost inline" type="submit">Mark reviewed today</button></form><?php endif ?>
  <button class="btn ghost inline" type="button" onclick="print()">Print / save as PDF</button>
</p>

<?php if ($isOld): ?><div class="alert error">You are viewing version <?= (int) $ver['version_no'] ?> from <?= h($ver['created_at']) ?>. <a href="<?= base_url('process?id=' . $id) ?>">Go to current version</a></div><?php endif ?>
<?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif ?>

<div class="panel" data-animate><h2>Flowchart</h2><?php require __DIR__ . '/../components/flowchart.php'; ?></div>

<div class="panel" data-animate>
  <h2>Step by step</h2>
  <div class="table-wrap"><table class="tbl">
    <tr><th>#</th><th>Step</th><th>Who</th><th>What they do</th><th>Tools</th><th>Output</th></tr>
    <?php foreach ($steps as $i => $st): ?>
    <tr><td><?= $i + 1 ?></td><td><strong><?= h($st['title']) ?></strong><?= $st['decision'] ? ' <span class="badge off">decision</span>' : '' ?></td>
      <td><?= h(trim($st['role'] . ($st['person'] ? ' — ' . $st['person'] : '')) ?: '—') ?></td>
      <td><?= nl2br(h($st['detail'])) ?></td><td><?= h($st['tools'] ?: '—') ?></td><td><?= h($st['output'] ?: '—') ?></td></tr>
    <?php endforeach ?>
  </table></div>
</div>

<?php if ($people): ?>
<div class="panel" data-animate>
  <h2>People involved</h2>
  <ul class="plain"><?php foreach ($people as $p): ?><li><strong><?= h($p['role'] ?: '—') ?></strong><?= $p['person'] ? ' — ' . h($p['person']) : '' ?>
    <span class="muted">(step <?= h(implode(', ', $p['n'])) ?>)</span></li><?php endforeach ?></ul>
</div>
<?php endif ?>
<?php if ($snap['notes']): ?><div class="panel"><h2>Notes for new hires</h2><p><?= nl2br(h($snap['notes'])) ?></p></div><?php endif ?>

<div class="panel noprint" data-animate>
  <h2>Version history</h2>
  <table class="tbl">
    <?php foreach ($versions as $v): ?>
    <tr><td><a href="<?= base_url('process?id=' . $id . '&v=' . $v['version_no']) ?>">Version <?= (int) $v['version_no'] ?></a></td>
      <td><?= h($v['created_at']) ?></td><td><?= h($v['who']) ?></td><td><?= h($v['changelog']) ?></td></tr>
    <?php endforeach ?>
  </table>
</div>

<div class="panel noprint">
  <h2>Change requests</h2>
  <?php foreach ($requests as $r): ?>
  <div style="border-bottom:1px solid var(--border);padding:.7rem 0">
    <strong><?= h($r['title']) ?></strong> <span class="badge <?= $r['status'] === 'open' ? 'warn' : '' ?>"><?= h($r['status']) ?></span>
    <div class="muted"><?= h($r['who']) ?> · <?= h($r['created_at']) ?></div><div><?= nl2br(h($r['details'])) ?></div>
    <?php if ($canEdit && $r['status'] === 'open'): ?>
    <form method="post" class="toolbar" style="margin:.5rem 0 0"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="action" value="resolve"><input type="hidden" name="request_id" value="<?= $r['id'] ?>">
      <button class="btn ghost inline" name="status" value="approved">Approve</button><button class="btn ghost inline" name="status" value="rejected">Reject</button><button class="btn ghost inline" name="status" value="done">Mark done</button></form>
    <?php endif ?>
  </div>
  <?php endforeach; if (!$requests): ?><p class="muted">No requests yet.</p><?php endif ?>
  <details style="margin-top:1rem"><summary>Suggest a change to this process</summary>
    <form method="post" style="margin-top:.8rem"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="request">
      <div class="form-group"><label class="form-label">Title</label><input class="form-input" name="title" required></div>
      <div class="form-group"><label class="form-label">What should change and why?</label><textarea class="form-input" name="details" rows="3" required></textarea></div>
      <button class="btn inline" type="submit">Send request</button></form>
  </details>
</div>
<?php require __DIR__ . '/../components/app_close.php';
