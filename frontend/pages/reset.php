<?php
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$error = null;
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = reset_password($token, $_POST['password'] ?? '');
    $done = !$error;
}

$title = 'Set new password';
require __DIR__ . '/../components/head.php';
require __DIR__ . '/../components/auth_open.php';
?>
<h2>Set new password</h2>
<?php if ($done): ?>
  <div class="alert ok">Password updated. You can sign in now.</div>
<?php else: ?>
  <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <div class="form-group"><label class="form-label" for="password">New password (8+ characters)</label>
      <input class="form-input" id="password" name="password" type="password" minlength="8" required autofocus></div>
    <button class="btn" type="submit">Update password</button>
  </form>
<?php endif ?>
<a class="link" href="<?= base_url('login') ?>">← Back to sign in</a>
<?php
require __DIR__ . '/../components/auth_close.php';
require __DIR__ . '/../components/foot.php';
