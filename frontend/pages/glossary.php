<?php
require_once __DIR__ . '/../../backend/services/onboarding.php';
$user = require_login();
$canEdit = can_edit_glossary($user);

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) { http_response_code(403); exit('You do not have access to this action.'); }
    try {
        if (($_POST['action'] ?? '') === 'delete') { delete_term($user, (int) $_POST['id']); flash('Term deleted.'); }
        else { save_term($user, ($_POST['id'] ?? '') === '' ? null : (int) $_POST['id'], $_POST); flash('Glossary saved.'); }
        redirect('glossary');
    } catch (UserError $e) {
        $error = $e->getMessage();
    }
}

$q = trim($_GET['q'] ?? '');
$st = db()->prepare('SELECT g.*, d.name AS dept FROM glossary g LEFT JOIN departments d ON d.id = g.department_id
                     WHERE (? = \'\' OR g.term LIKE ? OR g.definition LIKE ?) ORDER BY g.term');
$st->execute([$q, "%$q%", "%$q%"]);
$terms = $st->fetchAll();
$depts = db()->query('SELECT id, name FROM departments ORDER BY sort')->fetchAll();

$title = 'Glossary';
require __DIR__ . '/../components/app_open.php';

function term_form(array $depts, array $t = []): void { ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= h($t['id'] ?? '') ?>">
    <div class="grid2">
      <div class="form-group"><label class="form-label">Term</label><input class="form-input" name="term" required value="<?= h($t['term'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label">Who is it for?</label>
        <select class="form-input" name="department_id"><option value="">Everyone (company-wide)</option>
          <?php foreach ($depts as $d): ?><option value="<?= $d['id'] ?>" <?= ($t['department_id'] ?? '') == $d['id'] ? 'selected' : '' ?>><?= h($d['name']) ?></option><?php endforeach ?></select></div>
    </div>
    <div class="form-group"><label class="form-label">What it means</label><textarea class="form-input" name="definition" rows="2" required><?= h($t['definition'] ?? '') ?></textarea></div>
    <button class="btn inline" type="submit">Save</button>
  </form>
<?php } ?>
<div class="page-head"><div>
  <h1 class="title" data-animate>Glossary</h1>
  <p class="lead">What company words and acronyms mean.</p>
</div></div>
<form class="toolbar" method="get">
  <input class="form-input" name="q" placeholder="Search for a word or acronym" aria-label="Search the glossary" value="<?= h($q) ?>"><button class="btn inline" type="submit">Search</button>
</form>
<?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif ?>
<?php if ($canEdit): ?><details class="panel" <?= $error ? 'open' : '' ?>><summary>Add a term</summary><?php term_form($depts, $error ? $_POST : []) ?></details><?php endif ?>

<div class="panel table-wrap tight"><table class="tbl" data-animate>
  <tr><th>Term</th><th>Meaning</th><th>For</th><?php if ($canEdit): ?><th></th><?php endif ?></tr>
  <?php foreach ($terms as $t): ?>
  <tr>
    <td><strong><?= h($t['term']) ?></strong></td><td><?= nl2br(h($t['definition'])) ?></td><td><?= h($t['dept'] ?? 'Company-wide') ?></td>
    <?php if ($canEdit): ?><td>
      <details><summary class="muted">Edit</summary><?php term_form($depts, $t) ?>
        <form method="post" onsubmit="return confirm('Delete this term?')"><input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button class="link linkbtn" type="submit">Delete</button></form>
      </details></td><?php endif ?>
  </tr>
  <?php endforeach; if (!$terms): ?><tr><td colspan="4" class="muted">No terms found.</td></tr><?php endif ?>
</table></div>
<?php require __DIR__ . '/../components/app_close.php';
