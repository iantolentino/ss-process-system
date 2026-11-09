<?php /** @var array $user  @var string $title */
require __DIR__ . '/head.php';
$msg = flash();
?>
<header class="topbar">
  <nav><strong>Strata Staff</strong>&nbsp;&nbsp;
    <a href="<?= base_url('dashboard') ?>">Dashboard</a>
    <a href="<?= base_url('policies') ?>">Policy Portal</a>
    <a href="<?= base_url('process-library') ?>">Process Library</a>
    <?php if ($user['department_id']): ?><a href="<?= base_url('onboarding') ?>">Onboarding</a><?php endif ?>
    <a href="<?= base_url('glossary') ?>">Glossary</a>
    <?php if ($user['role'] === 'admin'): ?><a href="<?= base_url('users') ?>">Users</a><a href="<?= base_url('audit') ?>">Audit log</a><?php endif ?>
  </nav>
  <form method="post" action="<?= base_url('logout') ?>">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <?= h($user['first_name'] . ' ' . $user['last_name']) ?> (<?= h($user['role']) ?>)
    <button type="submit" class="link linkbtn">Sign out</button>
  </form>
</header>
<main class="page">
<?php if ($msg): ?><div class="alert ok"><?= h($msg) ?></div><?php endif ?>
