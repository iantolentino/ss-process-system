<?php
require_once __DIR__ . '/../../backend/services/processes.php';
$user = require_login();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$s = db()->prepare('SELECT p.*, d.name AS dept, d.slug FROM processes p JOIN departments d ON d.id = p.department_id WHERE p.id = ?');
$s->execute([$id]);
$proc = $s->fetch();
$canEdit = $proc && can_edit_process($user, (int) $proc['department_id']);
if (!$proc || ($proc['status'] !== 'active' && !$canEdit && $user['role'] !== 'admin')) { error_page(404, 'Process not found.'); }

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $act = $_POST['action'] ?? '';
        if ($act === 'request') { add_change_request($user, $id, $_POST); flash('Change request sent to the process owner.'); }
        elseif ($canEdit && $act === 'review') { mark_reviewed($user, $id); flash('Marked as reviewed today.'); }
        elseif ($canEdit && in_array($act, ['archive', 'restore'], true)) { set_process_status($user, $id, $act === 'archive' ? 'archived' : 'active'); flash($act === 'archive' ? 'Removed from the library. It is kept and can be restored.' : (needs_approval($user, 'active') ? 'Restored as a draft. Edit it and save it as active to send it for approval.' : 'Restored.')); }
        elseif ($canEdit && $act === 'resolve') { resolve_change_request($user, (int) $_POST['request_id'], $_POST['status'] ?? ''); flash('Request updated.'); }
        else { error_page(403, 'You do not have access to this action.'); }
        redirect("process?id=$id");
    } catch (UserError $e) {
        $error = $e->getMessage();
    }
}

// Versions that are waiting for approval or were rejected are only shown to people who can edit this process.
$vs = db()->prepare('SELECT v.id, v.version_no, v.changelog, v.created_at, v.approval, v.decision_note, CONCAT(u.first_name, " ", u.last_name) AS who
                     FROM process_versions v JOIN users u ON u.id = v.created_by WHERE v.process_id = ? AND (? OR v.approval = "approved") ORDER BY v.version_no DESC');
$vs->execute([$id, (int) $canEdit]);
$versions = $vs->fetchAll();
$waiting = current(array_filter($versions, fn($v) => $v['approval'] === 'pending')) ?: null;

$want = (int) ($_GET['v'] ?? 0);
$ver = null;
foreach ($versions as $v) if ($want ? $v['version_no'] == $want : $v['id'] == $proc['current_version_id']) $ver = $v;
if (!$ver) { error_page(404, 'Version not found.'); }
$isOld = $ver['id'] != $proc['current_version_id'];
$q = db()->prepare('SELECT snapshot FROM process_versions WHERE id = ?');
$q->execute([$ver['id']]);
$snap = json_decode($q->fetchColumn(), true);
$steps = $snap['steps'];

// "What changed" = this version against the one before it.
$changes = null;
if (isset($_GET['changes']) && $ver['version_no'] > 1) {
    // A waiting version is compared with what staff see now, so the approver sees exactly what would change.
    $q->execute([$ver['approval'] === 'pending' ? $proc['current_version_id'] : (array_column($versions, 'id', 'version_no')[$ver['version_no'] - 1] ?? 0)]);
    $changes = diff_snapshots(json_decode($q->fetchColumn(), true) ?: ['steps' => []], $snap);
}

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
$openReqs = count(array_filter($requests, fn($r) => $r['status'] === 'open'));
?>
<p class="crumbs noprint"><a href="<?= base_url('process-library') ?>">Processes</a> / <a href="<?= base_url('process-library?dept=' . $proc['slug']) ?>"><?= h($proc['dept']) ?></a></p>
<div class="page-head"><div>
  <h1 class="title" data-animate><?= h($proc['name']) ?>
    <span class="badge <?= $proc['status'] === 'active' ? '' : 'off' ?>"><?= h($proc['status']) ?></span><?= $proc['kind'] === 'template' ? ' <span class="badge off">template</span>' : '' ?></h1>
  <p class="lead"><?= nl2br(h($proc['purpose'])) ?></p>
  <div class="chips">
    <?php if ($proc['owner_position']): ?><span class="chip">Owner: <b><?= h($proc['owner_position']) ?></b></span><?php endif ?>
    <?php if ($snap['frequency']): ?><span class="chip">Happens: <b><?= h($snap['frequency']) ?></b></span><?php endif ?>
    <?php if ($snap['duration']): ?><span class="chip">Takes: <b><?= h($snap['duration']) ?></b></span><?php endif ?>
    <?php if ($snap['trigger']): ?><span class="chip">Starts when: <b><?= h($snap['trigger']) ?></b></span><?php endif ?>
    <span class="chip"><?= h(CADENCE[$proc['cadence']] ?? 'As needed') ?></span>
    <span class="chip">Version <b><?= (int) $ver['version_no'] ?></b></span>
    <span class="chip">Last checked: <b><?= h($proc['last_reviewed_at'] ?? 'never') ?></b></span>
  </div>
</div>
<div class="actions noprint">
  <?php if ($canEdit && !$isOld): ?><a class="btn inline" href="<?= base_url('process-edit?id=' . $id) ?>">Edit this process</a>
    <form method="post"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="review">
      <button class="btn ghost inline" type="submit" title="Records today as the date someone checked this process is still correct">Confirm still correct</button></form><?php endif ?>
  <?php if ($canEdit && !$isOld): ?>
    <?php if ($proc['kind'] === 'template' && $proc['status'] === 'active'): ?><a class="btn ghost inline" href="<?= base_url('process-edit?dept=' . $proc['slug'] . '&copy=' . $id) ?>">Use this template</a><?php endif ?>
    <form method="post" onsubmit="return confirm('<?= $proc['status'] === 'archived' ? 'Restore this' : 'Remove this' ?> <?= $proc['kind'] === 'template' ? 'template' : 'process' ?>?')"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="action" value="<?= $proc['status'] === 'archived' ? 'restore' : 'archive' ?>"><button class="btn ghost inline" type="submit" title="Hidden from staff, but kept with every version. You can restore it."><?= $proc['status'] === 'archived' ? 'Restore' : 'Remove' ?></button></form><?php endif ?>
  <button class="btn ghost inline" type="button" onclick="print()">Print</button>
</div></div>

<?php if ($isOld && $ver['approval'] !== 'pending'): ?><div class="alert error">You are viewing version <?= (int) $ver['version_no'] ?> from <?= h($ver['created_at']) ?>. <a href="<?= base_url('process?id=' . $id) ?>">Go to current version</a></div><?php endif ?>
<?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif ?>
<?php if ($waiting && $waiting['id'] != $ver['id']): ?><div class="alert ok noprint">Version <?= (int) $waiting['version_no'] ?> is waiting for an admin's approval. Staff keep seeing this version until then.
  <a href="<?= base_url('process?id=' . $id . '&v=' . $waiting['version_no'] . '&changes=1') ?>">See what is waiting</a></div>
<?php elseif ($waiting): ?><div class="alert ok noprint">This version is waiting for an admin's approval. Staff do not see it yet.<?php if ($user['role'] === 'admin'): ?> <a href="<?= base_url('approvals') ?>">Go to approvals</a><?php endif ?></div><?php endif ?>

<?php if ($changes !== null): ?>
<div class="panel noprint" data-animate><h2>What changed in version <?= (int) $ver['version_no'] ?></h2>
  <p class="muted"><?= h($ver['changelog']) ?></p>
  <?php if ($changes): ?><ul class="plain" data-changes><?php foreach ($changes as $c): ?><li><?= h($c) ?></li><?php endforeach ?></ul>
  <?php else: ?><p class="muted">No differences in the steps or details (only the name, purpose, owner or status changed).</p><?php endif ?>
</div>
<?php endif ?>

<div class="tabs noprint" data-tabs hidden role="tablist" aria-label="Process sections">
  <button type="button" role="tab" data-tab="overview">Overview</button>
  <button type="button" role="tab" data-tab="flowchart">Flowchart</button>
  <button type="button" role="tab" data-tab="steps">Steps in detail</button>
  <button type="button" role="tab" data-tab="people">People &amp; documents</button>
  <button type="button" role="tab" data-tab="history">History</button>
  <button type="button" role="tab" data-tab="requests">Requests<?= $openReqs ? " ($openReqs)" : '' ?></button>
</div>

<section data-tabpanel="overview"><div class="panel" data-animate>
  <h2>At a glance</h2>
  <?php
    $facts = ['How often' => trim((CADENCE[$proc['cadence']] ?? '') . ($snap['frequency'] ? ' · ' . $snap['frequency'] : '')),
        'Owner' => $proc['owner_position'], 'Starts when' => $snap['trigger'], 'Needed before starting' => $snap['inputs'] ?? '', 'Takes' => $snap['duration'],
        'How we know it went well' => $snap['kpi'] ?? '', 'What can go wrong' => $snap['exceptions'] ?? '', 'Who to ask when stuck' => $snap['escalation'] ?? '', 'Tips for new hires' => $snap['notes']];
    $missing = count(array_filter(array_slice($facts, 3), fn($v) => $v === ''));
  ?>
  <dl class="facts">
    <?php foreach ($facts as $k => $v): if ($v === '' || $v === null) continue; ?><div><dt><?= h($k) ?></dt><dd><?= nl2br(h($v)) ?></dd></div><?php endforeach ?>
    <div><dt>Steps</dt><dd><?= count($steps) ?> · <?= count(array_filter($steps, fn($x) => $x['decision'])) ?> decision(s)</dd></div>
  </dl>
  <?php if ($canEdit && $missing > 3): ?><p class="help">Some details are empty. <a href="<?= base_url('process-edit?id=' . $id) ?>">Fill them in</a> so executives and new hires can follow this at a glance.</p><?php endif ?>
</div></section>

<section data-tabpanel="flowchart"><div class="panel" data-animate><h2>Flowchart</h2>
  <p class="help">Read from top to bottom. A dashed box is a yes/no question.</p><?php require __DIR__ . '/../components/flowchart.php'; ?></div></section>

<section data-tabpanel="steps"><div class="panel" data-animate>
  <h2>Step by step</h2>
  <div class="table-wrap"><table class="tbl">
    <?php $hasWho = (bool) array_filter($steps, fn($x) => $x['role'] . $x['person'] !== ''); $hasTools = (bool) array_filter($steps, fn($x) => $x['tools'] !== ''); $hasOut = (bool) array_filter($steps, fn($x) => $x['output'] !== ''); ?>
    <?php $hasTime = (bool) array_filter($steps, fn($x) => ($x['time'] ?? '') !== ''); ?>
    <tr><th>#</th><th>Step</th><?php if ($hasWho): ?><th>Who</th><?php endif ?><th>What they do</th><?php if ($hasTools): ?><th>Tools</th><?php endif ?><?php if ($hasOut): ?><th>Result</th><?php endif ?><?php if ($hasTime): ?><th>Time</th><?php endif ?></tr>
    <?php foreach ($steps as $i => $st): ?>
    <tr><td><?= $i + 1 ?></td><td><strong><?= h($st['title']) ?></strong><?= $st['decision'] ? ' <span class="badge off">decision</span>' : '' ?></td>
      <?php if ($hasWho): ?><td><?= h(trim($st['role'] . ($st['person'] ? ' — ' . $st['person'] : '')) ?: '—') ?></td><?php endif ?>
      <td><?= nl2br(h($st['detail'])) ?></td><?php if ($hasTools): ?><td><?= h($st['tools'] ?: '—') ?></td><?php endif ?><?php if ($hasOut): ?><td><?= h($st['output'] ?: '—') ?></td><?php endif ?><?php if ($hasTime): ?><td><?= h($st['time'] ?? '') ?: '—' ?></td><?php endif ?></tr>
    <?php endforeach ?>
  </table></div>
</div></section>

<section data-tabpanel="people">
<?php if ($people): ?>
<div class="panel" data-animate>
  <h2>People involved</h2>
  <ul class="plain"><?php foreach ($people as $p): ?><li><strong><?= h($p['role'] ?: '—') ?></strong><?= $p['person'] ? ' — ' . h($p['person']) : '' ?>
    <span class="muted">(step <?= h(implode(', ', $p['n'])) ?>)</span></li><?php endforeach ?></ul>
</div>
<?php else: ?><div class="panel"><h2>People involved</h2><p class="muted">Nobody is named on the steps yet. The process owner can add who does each step.</p></div><?php endif ?>
<?php $related = related_documents($snap['docs'] ?? []); if ($related): ?>
<div class="panel"><h2>Related policies and forms</h2>
  <ul class="plain"><?php foreach ($related as $d): ?><li><a href="<?= base_url('document?id=' . $d['id']) ?>"><?= h($d['title']) ?></a> <span class="muted">(<?= h($d['type']) ?>)</span></li><?php endforeach ?></ul></div>
<?php endif ?>
</section>

<section data-tabpanel="history" class="noprint"><div class="panel" data-animate>
  <h2>Version history</h2>
  <p class="help">Every save is kept as a new version. Nothing is overwritten.</p>
  <div class="table-wrap"><table class="tbl">
    <?php foreach ($versions as $v): ?>
    <tr><td><a href="<?= base_url('process?id=' . $id . '&v=' . $v['version_no']) ?>">Version <?= (int) $v['version_no'] ?></a></td>
      <td><?= h($v['created_at']) ?></td><td><?= h($v['who']) ?></td><td><?= h($v['changelog']) ?>
        <?php if ($v['approval'] !== 'approved'): ?> <span class="badge <?= $v['approval'] === 'pending' ? 'warn' : 'off' ?>"><?= $v['approval'] === 'pending' ? 'waiting for approval' : 'rejected' ?></span><?= $v['decision_note'] !== '' ? ' <span class="muted">' . h($v['decision_note']) . '</span>' : '' ?><?php endif ?></td>
      <td><?= $v['version_no'] > 1 ? '<a href="' . base_url('process?id=' . $id . '&v=' . $v['version_no'] . '&changes=1') . '">What changed</a>' : '' ?></td></tr>
    <?php endforeach ?>
  </table></div>
</div></section>

<section data-tabpanel="requests" class="noprint"><div class="panel">
  <h2>Change requests</h2>
  <p class="help">See something wrong or out of date? Tell the process owner here.</p>
  <?php foreach ($requests as $r): ?>
  <div class="req">
    <strong><?= h($r['title']) ?></strong> <span class="badge <?= $r['status'] === 'open' ? 'warn' : '' ?>"><?= h($r['status']) ?></span>
    <div class="muted"><?= h($r['who']) ?> · <?= h($r['created_at']) ?></div><div><?= nl2br(h($r['details'])) ?></div>
    <?php if ($canEdit && $r['status'] === 'open'): ?>
    <form method="post" class="actions" style="margin:8px 0 0"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="action" value="resolve"><input type="hidden" name="request_id" value="<?= $r['id'] ?>">
      <button class="btn ghost inline sm" name="status" value="approved">Approve</button><button class="btn ghost inline sm" name="status" value="rejected">Reject</button><button class="btn ghost inline sm" name="status" value="done">Mark done</button></form>
    <?php endif ?>
  </div>
  <?php endforeach; if (!$requests): ?><p class="muted">No requests yet.</p><?php endif ?>
  <details style="margin-top:16px"><summary>Suggest a change to this process</summary>
    <form method="post" style="margin-top:12px"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="request">
      <div class="form-group"><label class="form-label">Short title</label><input class="form-input" name="title" required placeholder="e.g. Step 3 names the old tool"></div>
      <div class="form-group"><label class="form-label">What should change and why?</label><textarea class="form-input" name="details" rows="3" required></textarea></div>
      <button class="btn inline" type="submit">Send request</button></form>
  </details>
</div></section>
<?php require __DIR__ . '/../components/app_close.php';
