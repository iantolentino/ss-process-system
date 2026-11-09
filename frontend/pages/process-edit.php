<?php
require_once __DIR__ . '/../../backend/services/processes.php';
$user = require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null);
$proc = $snap = null;
if ($id) {
    $s = db()->prepare('SELECT p.*, d.name AS dept, d.slug FROM processes p JOIN departments d ON d.id = p.department_id WHERE p.id = ?');
    $s->execute([$id]);
    if (!($proc = $s->fetch())) { http_response_code(404); exit('Process not found.'); }
    $deptId = (int) $proc['department_id'];
    $v = db()->prepare('SELECT snapshot FROM process_versions WHERE id = ?');
    $v->execute([$proc['current_version_id']]);
    $snap = json_decode($v->fetchColumn(), true);
    $dept = ['id' => $deptId, 'name' => $proc['dept'], 'slug' => $proc['slug']];
} else {
    $s = db()->prepare('SELECT * FROM departments WHERE slug = ?');
    $s->execute([$_GET['dept'] ?? $_POST['dept'] ?? '']);
    if (!($dept = $s->fetch())) { http_response_code(404); exit('Department not found.'); }
    $deptId = (int) $dept['id'];
}
if (!can_edit_process($user, $deptId)) { http_response_code(403); exit('You do not have access to edit this department.'); }

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = save_process($user, $id, $deptId, $_POST);
        flash('Process saved.');
        redirect("process?id=$id");
    } catch (UserError $e) {
        $error = $e->getMessage();
    }
}

// What the form shows: what was just posted (after an error), else the saved process, else a blank one.
$f = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : ($proc ? $proc + $snap : ['status' => 'active']);
$steps = $_SERVER['REQUEST_METHOD'] === 'POST' ? array_values($_POST['steps'] ?? []) : ($snap['steps'] ?? []);
if (!$steps) $steps = [[]];
$allDocs = db()->query("SELECT id, type, title FROM documents WHERE status = 'active' ORDER BY type, title")->fetchAll();
$picked = array_map('intval', (array) ($f['docs'] ?? []));
$pos = db()->prepare('SELECT title, holder_name FROM positions WHERE department_id = ? ORDER BY title');
$pos->execute([$deptId]);
$positions = $pos->fetchAll();

function step_row(string $i, array $s): void { ?>
  <div class="step" data-step>
    <div class="step-head"><span class="flow-num" data-num></span>
      <input class="form-input" name="steps[<?= $i ?>][title]" placeholder="Step name (e.g. Review resume)" value="<?= h($s['title'] ?? '') ?>">
      <button type="button" class="btn ghost inline" data-act="up" title="Move up">↑</button>
      <button type="button" class="btn ghost inline" data-act="down" title="Move down">↓</button>
      <button type="button" class="btn ghost inline" data-act="del" title="Remove">✕</button></div>
    <div class="grid2">
      <div class="form-group"><label class="form-label">Who (position)</label>
        <input class="form-input" name="steps[<?= $i ?>][role]" list="positions" value="<?= h($s['role'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Person's name (optional)</label>
        <input class="form-input" name="steps[<?= $i ?>][person]" value="<?= h($s['person'] ?? '') ?>"></div>
    </div>
    <div class="form-group"><label class="form-label">What they do</label>
      <textarea class="form-input" rows="2" name="steps[<?= $i ?>][detail]"><?= h($s['detail'] ?? '') ?></textarea></div>
    <div class="grid2">
      <div class="form-group"><label class="form-label">Tools / systems</label><input class="form-input" name="steps[<?= $i ?>][tools]" value="<?= h($s['tools'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Output / result</label><input class="form-input" name="steps[<?= $i ?>][output]" value="<?= h($s['output'] ?? '') ?>"></div>
    </div>
    <label><input type="checkbox" data-decision name="steps[<?= $i ?>][decision]" value="1" <?= !empty($s['decision']) ? 'checked' : '' ?>> This step is a yes/no decision</label>
    <span data-nogoto <?= empty($s['decision']) ? 'hidden' : '' ?>> · If “No”, go to step # <input class="form-input" style="width:80px;display:inline-block" type="number" min="1"
      name="steps[<?= $i ?>][no_goto]" value="<?= !empty($s['no_goto']) ? (int) $s['no_goto'] : '' ?>"> (blank = process ends)</span>
  </div>
<?php }

$title = $proc ? 'Edit ' . $proc['name'] : 'New process';
require __DIR__ . '/../components/app_open.php';
?>
<p class="muted"><a href="<?= base_url($proc ? 'process?id=' . $id : 'process-library?dept=' . $dept['slug']) ?>">← Back</a></p>
<h1 class="title" data-animate><?= $proc ? 'Edit process' : 'New process' ?> <span class="muted">· <?= h($dept['name']) ?></span></h1>
<?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif ?>

<form method="post" id="process-form">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= h($id) ?>"><input type="hidden" name="dept" value="<?= h($dept['slug']) ?>">
  <div class="panel">
    <h2>About this process</h2>
    <div class="form-group"><label class="form-label">Name</label><input class="form-input" name="name" required value="<?= h($f['name'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Purpose — what is this process for?</label><textarea class="form-input" name="purpose" rows="3" required><?= h($f['purpose'] ?? '') ?></textarea></div>
    <div class="grid2">
      <div class="form-group"><label class="form-label">Owner (position)</label><input class="form-input" name="owner_position" list="positions" value="<?= h($f['owner_position'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Starts when…</label><input class="form-input" name="trigger" placeholder="e.g. A manager opens a vacancy" value="<?= h($f['trigger'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">How often</label><input class="form-input" name="frequency" placeholder="e.g. Per new hire" value="<?= h($f['frequency'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">How long it takes</label><input class="form-input" name="duration" placeholder="e.g. 3–5 days" value="<?= h($f['duration'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Status</label>
        <select class="form-input" name="status"><?php foreach (PROC_STATUS as $st): ?><option <?= ($f['status'] ?? '') === $st ? 'selected' : '' ?>><?= h($st) ?></option><?php endforeach ?></select></div>
    </div>
    <div class="form-group"><label class="form-label">Notes for new hires (tips, common mistakes, who to ask)</label><textarea class="form-input" name="notes" rows="3"><?= h($f['notes'] ?? '') ?></textarea></div>
    <div class="form-group"><label class="form-label">Related policies, manuals and forms (Ctrl/Cmd-click to pick several)</label>
      <select class="form-input" name="docs[]" multiple size="6">
        <?php foreach ($allDocs as $d): ?><option value="<?= $d['id'] ?>" <?= in_array((int) $d['id'], $picked, true) ? 'selected' : '' ?>><?= h(ucfirst($d['type']) . ': ' . $d['title']) ?></option><?php endforeach ?>
      </select></div>
  </div>

  <div class="panel">
    <h2>Steps</h2>
    <p class="muted">List what happens, in order. The flowchart is drawn from this.</p>
    <datalist id="positions"><?php foreach ($positions as $p): ?><option value="<?= h($p['title']) ?>"><?= h($p['holder_name']) ?></option><?php endforeach ?></datalist>
    <div id="steps"><?php foreach ($steps as $i => $s) step_row((string) $i, $s); ?></div>
    <button type="button" class="btn ghost inline" id="add-step">+ Add step</button>
    <template id="step-template"><?php step_row('__i__', []); ?></template>
  </div>

  <div class="panel">
    <div class="form-group"><label class="form-label"><?= $proc ? 'What changed in this version? (required)' : 'Version note (optional)' ?></label>
      <input class="form-input" name="changelog" <?= $proc ? 'required' : '' ?> value="<?= h($_POST['changelog'] ?? '') ?>"></div>
    <button class="btn inline" type="submit">Save <?= $proc ? 'new version' : 'process' ?></button>
  </div>
</form>
<script src="<?= base_url('frontend/js/process-edit.js') ?>"></script>
<?php require __DIR__ . '/../components/app_close.php';
