<?php
$user = require_login();
$title = 'Dashboard';
require __DIR__ . '/../components/head.php';
?>
<header class="topbar">
  <nav><strong>Strata Staff</strong>&nbsp;&nbsp;
    <a href="<?= base_url('dashboard') ?>">Dashboard</a>
    <a href="<?= base_url('policies') ?>">Policy Portal</a>
    <a href="<?= base_url('process-library') ?>">Process Library</a>
  </nav>
  <form method="post" action="<?= base_url('logout') ?>">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> (<?= htmlspecialchars($user['role']) ?>)
    <button type="submit" class="link" style="border:0;background:none;cursor:pointer;margin-left:10px">Sign out</button>
  </form>
</header>
<main class="page">
  <h1 data-animate style="font-family:var(--font-display);font-weight:400">Welcome, <?= htmlspecialchars($user['first_name']) ?></h1>
  <div class="cards">
    <a class="card" data-animate href="<?= base_url('process-library') ?>"><h3>Process Library</h3><p>How each department works: steps, people, flowcharts.</p></a>
    <a class="card" data-animate href="<?= base_url('policies') ?>"><h3>Policy Portal</h3><p>Policies, memos, manuals and forms with version history.</p></a>
  </div>
</main>
<?php require __DIR__ . '/../components/foot.php';
