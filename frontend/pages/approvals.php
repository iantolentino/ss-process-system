<?php
// Admin only: everything a policy owner or process owner sent that staff cannot see until it is approved.
require_once __DIR__ . '/../../backend/services/documents.php';
require_once __DIR__ . '/../../backend/services/processes.php';
$user = require_role('admin');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $approve = ($_POST['decision'] ?? '') === 'approve';
        $decide = ($_POST['kind'] ?? '') === 'process' ? 'decide_process_version' : 'decide_document_version';
        $decide($user, (int) ($_POST['vid'] ?? 0), $approve, (string) ($_POST['note'] ?? ''));
        flash($approve ? 'Approved. Staff can see it now.' : 'Rejected. The person was told why.');
        redirect('approvals');
    } catch (UserError $e) {
        $error = $e->getMessage();
    }
}

$docs = db()->query("SELECT v.id, v.version_no, v.changelog, v.uploaded_at AS at, v.original_name, d.id AS doc_id, d.type, d.title, CONCAT(u.first_name, ' ', u.last_name) AS who
                     FROM document_versions v JOIN documents d ON d.id = v.document_id JOIN users u ON u.id = v.uploaded_by WHERE v.approval = 'pending' ORDER BY v.id")->fetchAll();
$procs = db()->query("SELECT v.id, v.version_no, v.changelog, v.created_at AS at, p.id AS proc_id, p.name, dp.name AS dept, CONCAT(u.first_name, ' ', u.last_name) AS who
                      FROM process_versions v JOIN processes p ON p.id = v.process_id JOIN departments dp ON dp.id = p.department_id JOIN users u ON u.id = v.created_by
                      WHERE v.approval = 'pending' ORDER BY v.id")->fetchAll();

$title = 'Approvals';
require __DIR__ . '/../components/app_open.php';

// Approve button, and a reject button that needs a reason.
$buttons = function (string $kind, int $vid) { ?>
  <form method="post" class="toolbar" style="margin:8px 0 0">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="kind" value="<?= $kind ?>"><input type="hidden" name="vid" value="<?= $vid ?>">
    <button class="btn inline sm" name="decision" value="approve">Approve</button>
    <input class="form-input" name="note" maxlength="500" placeholder="Reason (needed to reject)" aria-label="Reason">
    <button class="btn ghost inline sm" name="decision" value="reject">Reject</button>
  </form>
<?php };
?>
<div class="page-head"><div>
  <h1 class="title" data-animate>Approvals</h1>
  <p class="lead">Changes sent by policy owners and process owners. Staff only see a change after you approve it. Look at it first, then approve or reject.</p>
</div></div>
<?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif ?>

<div class="panel" data-animate>
  <h2>Policies, memos, manuals and forms <span class="muted">· <?= count($docs) ?></span></h2>
  <?php foreach ($docs as $d): ?>
  <div class="req">
    <strong><a href="<?= base_url('document?id=' . $d['doc_id']) ?>"><?= h($d['title']) ?></a></strong> <span class="badge off"><?= h($d['type']) ?></span> <span class="badge warn">version <?= h($d['version_no']) ?></span>
    <div class="muted"><?= h($d['who']) ?> · <?= h($d['at']) ?> · <a href="<?= base_url('download?v=' . $d['id']) ?>"><?= h($d['original_name']) ?></a></div>
    <div><?= h($d['changelog']) ?></div>
    <?php $buttons('document', (int) $d['id']) ?>
  </div>
  <?php endforeach; if (!$docs): ?><p class="muted">Nothing is waiting.</p><?php endif ?>
</div>

<div class="panel" data-animate>
  <h2>Processes <span class="muted">· <?= count($procs) ?></span></h2>
  <?php foreach ($procs as $p): ?>
  <div class="req">
    <strong><a href="<?= base_url('process?id=' . $p['proc_id'] . '&v=' . $p['version_no'] . '&changes=1') ?>"><?= h($p['name']) ?></a></strong> <span class="badge off"><?= h($p['dept']) ?></span> <span class="badge warn">version <?= (int) $p['version_no'] ?></span>
    <div class="muted"><?= h($p['who']) ?> · <?= h($p['at']) ?></div>
    <div><?= h($p['changelog']) ?></div>
    <?php $buttons('process', (int) $p['id']) ?>
  </div>
  <?php endforeach; if (!$procs): ?><p class="muted">Nothing is waiting.</p><?php endif ?>
</div>
<?php require __DIR__ . '/../components/app_close.php';
