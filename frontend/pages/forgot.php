<?php
$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    request_reset($_POST['email'] ?? '');
    $sent = true;
}

$title = 'Reset password';
require __DIR__ . '/../components/head.php';
require __DIR__ . '/../components/auth_open.php';
?>
<h2>Reset password</h2>
<?php if ($sent): ?>
  <div class="alert ok">If that email has an account, a reset link is on its way. It expires in 1 hour.</div>
<?php else: ?>
  <p>Enter your work email and we will send a reset link.</p>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="form-group"><label class="form-label" for="email">Email</label>
      <input class="form-input" id="email" name="email" type="email" required autofocus></div>
    <button class="btn" type="submit">Send reset link</button>
  </form>
<?php endif ?>
<a class="link" href="<?= base_url('login') ?>">← Back to sign in</a>
<?php
require __DIR__ . '/../components/auth_close.php';
require __DIR__ . '/../components/foot.php';
