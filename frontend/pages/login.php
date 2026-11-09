<?php
if (current_user()) redirect('dashboard');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = attempt_login($_POST['email'] ?? '', $_POST['password'] ?? '');
    if (!$error) redirect('dashboard');
}

$title = 'Sign in';
require __DIR__ . '/../components/head.php';
require __DIR__ . '/../components/auth_open.php';
?>
<h2>Welcome back</h2>
<p>Sign in to see processes and policies.</p>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif ?>
<form method="post" autocomplete="on">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <div class="form-group"><label class="form-label" for="email">Email</label>
    <input class="form-input" id="email" name="email" type="email" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></div>
  <div class="form-group"><label class="form-label" for="password">Password</label>
    <input class="form-input" id="password" name="password" type="password" required></div>
  <button class="btn" type="submit">Sign in</button>
</form>
<a class="link" href="<?= base_url('forgot') ?>">Forgot password?</a>
<?php
require __DIR__ . '/../components/auth_close.php';
require __DIR__ . '/../components/foot.php';
