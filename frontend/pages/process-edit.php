<?php
require_once __DIR__ . '/../../backend/services/processes.php';
$user = require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null);
$proc = $snap = null;
if ($id) {
    $s = db()->prepare('SELECT p.*, d.name AS dept, d.slug FROM processes p JOIN departments d ON d.id = p.department_id WHERE p.id = ?');
    $s->execute([$id]);
    if (!($proc = $s->fetch())) { error_page(404, 'Process not found.'); }
    $deptId = (int) $proc['department_id'];
    $v = db()->prepare('SELECT snapshot FROM process_versions WHERE id = ?');
    $v->execute([$proc['current_version_id']]);
    $snap = json_decode($v->fetchColumn(), true);
    $dept = ['id' => $deptId, 'name' => $proc['dept'], 'slug' => $proc['slug']];
} else {
    $s = db()->prepare('SELECT * FROM departments WHERE slug = ?');
    $s->execute([$_GET['dept'] ?? $_POST['dept'] ?? '']);
    if (!($dept = $s->fetch())) { error_page(404, 'Department not found.'); }
    $deptId = (int) $dept['id'];
}
if (!can_edit_process($user, $deptId)) { error_page(403, 'You do not have access to edit this department.'); }

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = save_process($user, $id, $deptId, $_POST);
        flash(needs_approval($user, $_POST['status'] ?? 'active') ? 'Sent to an admin for approval. Staff will see it once it is approved.' : 'Process saved.');
        redirect("process?id=$id");
    } catch (UserError $e) {
        $error = $e->getMessage();
    }
}


// New process "from template": prefill from a template or another process (any department). People are cleared; roles/steps stay.
$template = null;
$copyId = (int) ($_GET['copy'] ?? 0);
if (!$proc && $copyId) {
    $c = db()->prepare("SELECT p.name, p.purpose, p.owner_position, p.cadence, p.kind, v.snapshot FROM processes p JOIN process_versions v ON v.id = p.current_version_id WHERE p.id = ? AND (p.status = 'active' OR p.kind = 'template')");
    $c->execute([$copyId]);
    if ($src = $c->fetch()) {
        $ts = json_decode($src['snapshot'], true);
        $ts['steps'] = array_map(fn($s) => ['person' => ''] + $s, $ts['steps']);
        $template = ['name' => $src['kind'] === 'template' ? '' : 'Copy of ' . $src['name'], 'purpose' => $src['purpose'], 'owner_position' => $src['owner_position'],
                     'cadence' => $src['cadence'], 'kind' => 'process', 'status' => 'draft'] + $ts;
        $template['source'] = $src['name'];
    }
}
// Gallery of reusable templates (all departments), and every live process to copy from.
$tplRows = $proc ? [] : db()->query("SELECT p.id, p.name, p.purpose, p.cadence, d.name AS dept, JSON_LENGTH(v.snapshot, '$.steps') AS steps FROM processes p JOIN departments d ON d.id = p.department_id
    JOIN process_versions v ON v.id = p.current_version_id WHERE p.kind = 'template' AND p.status = 'active' ORDER BY FIELD(p.cadence, 'daily','weekly','monthly','quarterly','yearly','as_needed'), p.name")->fetchAll();
$templates = $proc ? [] : db()->query("SELECT p.id, p.name, d.name AS dept FROM processes p JOIN departments d ON d.id = p.department_id WHERE p.status = 'active' AND p.kind = 'process' ORDER BY d.sort, p.name")->fetchAll();

// What the form shows: what was just posted (after an error), else the saved process, else a template/blank one.
$f = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : ($proc ? $proc + $snap : ($template ?? ['status' => 'draft', 'cadence' => 'as_needed']));
$steps = $_SERVER['REQUEST_METHOD'] === 'POST' ? array_values($_POST['steps'] ?? []) : ($snap['steps'] ?? $template['steps'] ?? []);
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
      <div class="form-group"><label class="form-label">Who does it (position)</label>
        <input class="form-input" name="steps[<?= $i ?>][role]" list="positions" value="<?= h($s['role'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Person's name (optional)</label>
        <input class="form-input" name="steps[<?= $i ?>][person]" value="<?= h($s['person'] ?? '') ?>"></div>
    </div>
    <div class="form-group"><label class="form-label">What they do</label>
      <textarea class="form-input" rows="2" name="steps[<?= $i ?>][detail]"><?= h($s['detail'] ?? '') ?></textarea></div>
    <details class="step-more" <?= !empty($s['tools']) || !empty($s['output']) || !empty($s['time']) ? 'open' : '' ?>><summary class="muted">Tools, result and time (optional)</summary>
    <div class="grid2" style="margin-top:12px">
      <div class="form-group"><label class="form-label">Tools / systems</label><input class="form-input" name="steps[<?= $i ?>][tools]" placeholder="e.g. Excel, SAP" value="<?= h($s['tools'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">What comes out of this step</label><input class="form-input" name="steps[<?= $i ?>][output]" placeholder="e.g. Signed form" value="<?= h($s['output'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">How long it takes</label><input class="form-input" name="steps[<?= $i ?>][time]" placeholder="e.g. 15 minutes" value="<?= h($s['time'] ?? '') ?>"></div>
    </div></details>
    <label><input type="checkbox" data-decision name="steps[<?= $i ?>][decision]" value="1" <?= !empty($s['decision']) ? 'checked' : '' ?>> This step is a yes/no decision</label>
    <span data-nogoto <?= empty($s['decision']) ? 'hidden' : '' ?>> · If “No”, go to step # <input class="form-input" style="width:80px;display:inline-block" type="number" min="1"
      name="steps[<?= $i ?>][no_goto]" value="<?= !empty($s['no_goto']) ? (int) $s['no_goto'] : '' ?>"> (blank = process ends)</span>
  </div>
<?php }

$isTpl = ($f['kind'] ?? 'process') === 'template';
$title = $proc ? 'Edit ' . $proc['name'] : 'New process';
require __DIR__ . '/../components/app_open.php';
?>
<p class="crumbs"><a href="<?= base_url('process-library') ?>">Processes</a> / <a href="<?= base_url('process-library?dept=' . $dept['slug']) ?>"><?= h($dept['name']) ?></a><?php if ($proc): ?> / <a href="<?= base_url('process?id=' . $id) ?>"><?= h($proc['name']) ?></a><?php endif ?></p>
<div class="page-head"><div>
  <h1 class="title" data-animate><?= $proc ? ($isTpl ? 'Edit template' : 'Edit process') : 'New process' ?> <span class="muted">· <?= h($dept['name']) ?></span></h1>
  <p class="lead">Write down how this job is done, step by step, so a new person (or an executive) can follow it. Every save keeps the old version, so nothing is lost.</p>
</div></div>
<?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif ?>

<?php if (!$proc): ?>
<div class="panel noprint">
  <?php if (!$template): ?>
  <h2>Start from a template</h2>
  <p class="help">Pick the closest one: it fills in a ready-made layout you only have to adjust. Or scroll down to start with a blank page.</p>
  <div class="cards" style="margin:12px 0 0">
    <?php foreach ($tplRows as $t): ?>
    <a class="card" href="<?= base_url('process-edit?dept=' . $dept['slug'] . '&copy=' . $t['id']) ?>"><span class="badge off"><?= h(CADENCE[$t['cadence']]) ?></span>
      <h2><?= h($t['name']) ?></h2><p><?= h(mb_strimwidth($t['purpose'], 0, 110, '…')) ?></p></a>
    <?php endforeach; if (!$tplRows): ?><p class="muted">No templates yet. An admin can create them (choose “Reusable template” when saving a process).</p><?php endif ?>
  </div>
  <?php endif ?>
  <details style="margin-top:16px" <?= $template ? 'open' : '' ?>><summary class="muted"><?= $template ? 'Pick a different template or process' : 'Or copy an existing process' ?></summary>
    <form method="get" class="toolbar" style="margin-top:12px">
      <input type="hidden" name="dept" value="<?= h($dept['slug']) ?>">
      <select class="form-input" name="copy" aria-label="Process to copy"><option value="">Choose…</option>
        <?php foreach ($templates as $t): ?><option value="<?= $t['id'] ?>" <?= $copyId == $t['id'] ? 'selected' : '' ?>><?= h($t['dept'] . ' — ' . $t['name']) ?></option><?php endforeach ?></select>
      <button class="btn ghost inline" type="submit">Use as template</button>
    </form>
    <p class="help">Copies the steps so you only change what is different. People's names are cleared.</p>
  </details>
</div>
<?php endif ?>
<?php if ($template): ?><div class="alert ok">Copied from “<?= h($template['source']) ?>”. People's names were cleared; change anything that does not fit your department, then save.</div><?php endif ?>
<form method="post" id="process-form">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= h($id) ?>"><input type="hidden" name="dept" value="<?= h($dept['slug']) ?>">
  <div class="panel">
    <h2>1. The basics</h2>
    <div class="form-group"><label class="form-label">Name</label><input class="form-input" name="name" required placeholder="e.g. Month-end close" value="<?= h($f['name'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">What is this process for?</label><textarea class="form-input" name="purpose" rows="2" required placeholder="One or two sentences. An executive should understand why it exists."><?= h($f['purpose'] ?? '') ?></textarea></div>
    <div class="grid2">
      <div class="form-group"><label class="form-label">How often does it happen?</label>
        <select class="form-input" name="cadence"><?php foreach (CADENCE as $k => $label): ?><option value="<?= $k ?>" <?= ($f['cadence'] ?? 'as_needed') === $k ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach ?></select>
        <p class="help">Processes are grouped by this on the department page.</p></div>
      <div class="form-group"><label class="form-label">Who can see it?</label>
        <select class="form-input" name="status"><?php foreach (PROC_STATUS as $st): ?><option <?= ($f['status'] ?? '') === $st ? 'selected' : '' ?>><?= h($st) ?></option><?php endforeach ?></select>
        <p class="help">draft = only editors · active = everyone<?= $user['role'] === 'admin' ? '' : ', after an admin approves it' ?> · archived = hidden</p></div>
      <div class="form-group"><label class="form-label">Save as</label>
        <select class="form-input" name="kind"><option value="process" <?= !$isTpl ? 'selected' : '' ?>>A live process (staff see it)</option><option value="template" <?= $isTpl ? 'selected' : '' ?>>A reusable template (a starting point for new processes)</option></select></div>
    </div>
  </div>

  <div class="panel">
    <h2>2. Steps</h2>
    <p class="help">List what happens, in order. The flowchart is drawn from this. Say who does each step; new hires rely on it.</p>
    <datalist id="positions"><?php foreach ($positions as $p): ?><option value="<?= h($p['title']) ?>"><?= h($p['holder_name']) ?></option><?php endforeach ?></datalist>
    <div id="steps"><?php foreach ($steps as $i => $s) step_row((string) $i, $s); ?></div>
    <button type="button" class="btn ghost inline" id="add-step">+ Add step</button>
    <template id="step-template"><?php step_row('__i__', []); ?></template>
  </div>

  <details class="panel" open>
    <summary>3. Details that help readers (recommended)</summary>
    <p class="help" style="margin:-8px 0 12px">This is what executives and new hires read first. Short answers are fine.</p>
    <div class="grid2">
      <div class="form-group"><label class="form-label">Owner (position)</label><input class="form-input" name="owner_position" list="positions" value="<?= h($f['owner_position'] ?? '') ?>"><p class="help">Responsible for keeping this up to date.</p></div>
      <div class="form-group"><label class="form-label">Starts when…</label><input class="form-input" name="trigger" placeholder="e.g. The first working day of the month" value="<?= h($f['trigger'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Exact schedule</label><input class="form-input" name="frequency" placeholder="e.g. Every Monday 9:00" value="<?= h($f['frequency'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">How long it takes (overall)</label><input class="form-input" name="duration" placeholder="e.g. 2 hours" value="<?= h($f['duration'] ?? '') ?>"></div>
    </div>
    <div class="form-group"><label class="form-label">What is needed before starting</label><textarea class="form-input" name="inputs" rows="2" placeholder="Reports, access, approvals, files…"><?= h($f['inputs'] ?? '') ?></textarea></div>
    <div class="form-group"><label class="form-label">How we know it went well</label><textarea class="form-input" name="kpi" rows="2" placeholder="e.g. Done by 10:00, no errors, 100% of tickets answered"><?= h($f['kpi'] ?? '') ?></textarea></div>
    <div class="form-group"><label class="form-label">What can go wrong</label><textarea class="form-input" name="exceptions" rows="2" placeholder="Common problems and what to do"><?= h($f['exceptions'] ?? '') ?></textarea></div>
    <div class="form-group"><label class="form-label">Who to ask when stuck</label><input class="form-input" name="escalation" placeholder="e.g. Team Lead, then Finance Manager" value="<?= h($f['escalation'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Tips for new hires</label><textarea class="form-input" name="notes" rows="3"><?= h($f['notes'] ?? '') ?></textarea><p class="help">Common mistakes, shortcuts.</p></div>
    <div class="form-group"><label class="form-label">Related policies, manuals and forms</label>
      <select class="form-input" name="docs[]" multiple size="6">
        <?php foreach ($allDocs as $d): ?><option value="<?= $d['id'] ?>" <?= in_array((int) $d['id'], $picked, true) ? 'selected' : '' ?>><?= h(ucfirst($d['type']) . ': ' . $d['title']) ?></option><?php endforeach ?>
      </select><p class="help">Hold Ctrl (Cmd on Mac) and click to pick several.</p></div>
  </details>

  <div class="panel">
    <div class="form-group"><label class="form-label"><?= $proc ? 'What changed in this version? (required)' : 'Version note (optional)' ?></label>
      <input class="form-input" name="changelog" <?= $proc ? 'required' : '' ?> placeholder="<?= $proc ? 'e.g. Added an approval step after step 3' : 'e.g. First version' ?>" value="<?= h($_POST['changelog'] ?? '') ?>"></div>
    <button class="btn inline" type="submit">Save <?= $proc ? 'new version' : 'process' ?></button>
  </div>
</form>
<script src="<?= base_url('frontend/js/process-edit.js') ?>"></script>
<?php require __DIR__ . '/../components/app_close.php';
