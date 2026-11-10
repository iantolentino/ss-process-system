<?php
require_once __DIR__ . '/../../backend/services/users.php';
$user = require_role('admin');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $editId = ($_POST['id'] ?? '') === '' ? null : (int) $_POST['id'];
        user_save($user, $editId, $_POST);
        flash($editId ? 'User updated.' : 'User created.');
        redirect('users');
    } catch (UserError $e) {
        $error = $e->getMessage();
    }
}

$depts = db()->query('SELECT id, name FROM departments ORDER BY sort')->fetchAll();
$positions = db()->query('SELECT id, title, department_id FROM positions ORDER BY title')->fetchAll();
const PER_PAGE = 50;
$q = trim($_GET['q'] ?? '');
$pg = max(1, (int) ($_GET['p'] ?? 1));
$st = db()->prepare('SELECT u.*, d.name AS dept FROM users u LEFT JOIN departments d ON d.id = u.department_id
                     WHERE (? = \'\' OR u.email LIKE ? OR CONCAT(u.first_name, \' \', u.last_name) LIKE ?)
                     ORDER BY u.active DESC, u.last_name, u.first_name LIMIT ' . (PER_PAGE + 1) . ' OFFSET ' . ($pg - 1) * PER_PAGE);
$st->execute([$q, "%$q%", "%$q%"]);
$users = $st->fetchAll();
$pgMore = count($users) > PER_PAGE;
$users = array_slice($users, 0, PER_PAGE);
$pgPath = 'users';
$pgParams = array_filter(['q' => $q]);

// Form shows when creating, editing, or after a failed save (keeps what was typed).
$editing = null;
if ($error) $editing = $_POST;
elseif (isset($_GET['edit'])) {
    $e = db()->prepare('SELECT * FROM users WHERE id = ?');
    $e->execute([(int) $_GET['edit']]);
    $editing = $e->fetch() ?: null;
}
$showForm = $editing !== null || isset($_GET['new']);

$title = 'People & roles';
require __DIR__ . '/../components/app_open.php';
?>
<div class="page-head"><div>
  <h1 class="title" data-animate>People &amp; roles</h1>
  <p class="lead">Everyone who can sign in, and what each person is allowed to do. People are turned off, never deleted, so their history stays attached to their name.</p>
</div><div class="actions"><a class="btn inline" href="<?= base_url('users?new=1') ?>">Add a person</a></div></div>

<?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif ?>
<?php if ($showForm): $e = $editing ?? ['active' => 1, 'role' => 'employee']; ?>
<form method="post" class="panel" data-animate>
  <h2><?= !empty($e['id']) ? 'Edit person' : 'Add a person' ?></h2>
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="id" value="<?= h($e['id'] ?? '') ?>">
  <div class="grid2">
    <div class="form-group"><label class="form-label">First name</label><input class="form-input" name="first_name" required value="<?= h($e['first_name'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Middle name</label><input class="form-input" name="middle_name" value="<?= h($e['middle_name'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Last name</label><input class="form-input" name="last_name" required value="<?= h($e['last_name'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Email</label><input class="form-input" type="email" name="email" required value="<?= h($e['email'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">What can they do?</label>
      <select class="form-input" name="role"><?php foreach (ROLES as $r): ?><option value="<?= h($r) ?>" <?= ($e['role'] ?? '') === $r ? 'selected' : '' ?>><?= h(ROLE_LABELS[$r] ?? $r) ?></option><?php endforeach ?></select></div>
    <div class="form-group"><label class="form-label">Department</label>
      <select class="form-input" name="department_id"><option value="">—</option>
        <?php foreach ($depts as $d): ?><option value="<?= $d['id'] ?>" <?= ($e['department_id'] ?? '') == $d['id'] ? 'selected' : '' ?>><?= h($d['name']) ?></option><?php endforeach ?></select></div>
    <div class="form-group"><label class="form-label">Position</label>
      <select class="form-input" name="position_id"><option value="">—</option>
        <?php foreach ($positions as $p): ?><option value="<?= $p['id'] ?>" <?= ($e['position_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= h($p['title']) ?> (<?= h(array_column($depts, 'name', 'id')[$p['department_id']] ?? '') ?>)</option><?php endforeach ?></select><p class="help">Optional. Must be in the person's department; used for their checklist.</p></div>
    <div class="form-group"><label class="form-label"><?= !empty($e['id']) ? 'New password' : 'Starting password' ?></label>
      <input class="form-input" type="password" name="password" autocomplete="new-password"><p class="help"><?= !empty($e['id']) ? 'Leave blank to keep the current one.' : 'At least 8 characters. They can change it with "Forgot password".' ?></p></div>
    <div class="form-group"><label class="form-label">Account</label>
      <label><input type="checkbox" name="active" value="1" <?= !empty($e['active']) ? 'checked' : '' ?>> Can sign in</label><p class="help">Untick to turn the account off.</p></div>
  </div>
  <button class="btn inline" type="submit">Save</button>
  <a class="btn ghost inline" href="<?= base_url('users') ?>">Cancel</a>
</form>
<?php endif ?>

<form class="toolbar" method="get"><input class="form-input" name="q" placeholder="Search by name or email" value="<?= h($q) ?>"><button class="btn inline" type="submit">Search</button></form>
<div class="panel table-wrap tight"><table class="tbl" data-animate>
  <tr><th>Name</th><th>Email</th><th>Can do</th><th>Department</th><th>Last sign-in</th><th>Account</th><th></th></tr>
  <?php foreach ($users as $u): ?>
  <tr>
    <td><?= h($u['first_name'] . ' ' . $u['last_name']) ?></td><td><?= h($u['email']) ?></td><td><?= h(strtok(ROLE_LABELS[$u['role']] ?? $u['role'], ':')) ?></td>
    <td><?= h($u['dept'] ?? '—') ?></td><td><?= h($u['last_login_at'] ?? 'never') ?></td>
    <td><span class="badge <?= $u['active'] ? '' : 'off' ?>"><?= $u['active'] ? 'active' : 'turned off' ?></span></td>
    <td><a href="<?= base_url('users?edit=' . $u['id']) ?>">Edit</a></td>
  </tr>
  <?php endforeach ?>
</table></div>
<?php require __DIR__ . '/../components/pager.php'; ?>
<?php require __DIR__ . '/../components/app_close.php';
