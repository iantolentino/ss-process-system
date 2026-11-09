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

$title = 'Users';
require __DIR__ . '/../components/app_open.php';
?>
<h1 class="title" data-animate>Users</h1>
<p class="muted">Accounts are disabled, not deleted, so history stays attached to real people.</p>
<p style="margin:1rem 0"><a class="btn inline" href="<?= base_url('users?new=1') ?>" style="text-decoration:none;display:inline-block">+ Add user</a></p>

<?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif ?>
<?php if ($showForm): $e = $editing ?? ['active' => 1, 'role' => 'employee']; ?>
<form method="post" class="panel" data-animate>
  <h2><?= !empty($e['id']) ? 'Edit user' : 'New user' ?></h2>
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="id" value="<?= h($e['id'] ?? '') ?>">
  <div class="grid2">
    <div class="form-group"><label class="form-label">First name</label><input class="form-input" name="first_name" required value="<?= h($e['first_name'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Middle name</label><input class="form-input" name="middle_name" value="<?= h($e['middle_name'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Last name</label><input class="form-input" name="last_name" required value="<?= h($e['last_name'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Email</label><input class="form-input" type="email" name="email" required value="<?= h($e['email'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Role</label>
      <select class="form-input" name="role"><?php foreach (ROLES as $r): ?><option <?= ($e['role'] ?? '') === $r ? 'selected' : '' ?>><?= h($r) ?></option><?php endforeach ?></select></div>
    <div class="form-group"><label class="form-label">Department</label>
      <select class="form-input" name="department_id"><option value="">—</option>
        <?php foreach ($depts as $d): ?><option value="<?= $d['id'] ?>" <?= ($e['department_id'] ?? '') == $d['id'] ? 'selected' : '' ?>><?= h($d['name']) ?></option><?php endforeach ?></select></div>
    <div class="form-group"><label class="form-label">Position (must be in the same department)</label>
      <select class="form-input" name="position_id"><option value="">—</option>
        <?php foreach ($positions as $p): ?><option value="<?= $p['id'] ?>" <?= ($e['position_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= h($p['title']) ?> (<?= h(array_column($depts, 'name', 'id')[$p['department_id']] ?? '') ?>)</option><?php endforeach ?></select></div>
    <div class="form-group"><label class="form-label"><?= !empty($e['id']) ? 'New password (leave blank to keep)' : 'Starting password (8+)' ?></label>
      <input class="form-input" type="password" name="password" autocomplete="new-password"></div>
    <div class="form-group"><label class="form-label">Status</label>
      <label><input type="checkbox" name="active" value="1" <?= !empty($e['active']) ? 'checked' : '' ?>> Active (can sign in)</label></div>
  </div>
  <button class="btn inline" type="submit">Save</button>
  <a class="link" href="<?= base_url('users') ?>" style="margin-left:12px">Cancel</a>
</form>
<?php endif ?>

<form class="toolbar" method="get"><input class="form-input" name="q" placeholder="Search name or email" value="<?= h($q) ?>"><button class="btn inline" type="submit">Search</button></form>
<div class="table-wrap"><table class="tbl" data-animate>
  <tr><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Last sign-in</th><th>Status</th><th></th></tr>
  <?php foreach ($users as $u): ?>
  <tr>
    <td><?= h($u['first_name'] . ' ' . $u['last_name']) ?></td><td><?= h($u['email']) ?></td><td><?= h($u['role']) ?></td>
    <td><?= h($u['dept'] ?? '—') ?></td><td><?= h($u['last_login_at'] ?? 'never') ?></td>
    <td><span class="badge <?= $u['active'] ? '' : 'off' ?>"><?= $u['active'] ? 'active' : 'disabled' ?></span></td>
    <td><a href="<?= base_url('users?edit=' . $u['id']) ?>">Edit</a></td>
  </tr>
  <?php endforeach ?>
</table></div>
<?php require __DIR__ . '/../components/pager.php'; ?>
<?php require __DIR__ . '/../components/app_close.php';
