<?php
$user = require_login();

// Active documents overdue or due for review within 30 days.
$due = db()->query("SELECT id, type, title, review_due, review_due < CURDATE() AS overdue FROM documents
                    WHERE status = 'active' AND review_due IS NOT NULL AND review_due <= CURDATE() + INTERVAL 30 DAY
                    ORDER BY review_due LIMIT 10")->fetchAll();

$title = 'Dashboard';
require __DIR__ . '/../components/app_open.php';
?>
<h1 class="title" data-animate>Welcome, <?= h($user['first_name']) ?></h1>
<div class="cards">
  <a class="card" data-animate href="<?= base_url('process-library') ?>"><h3>Process Library</h3><p>How each department works: steps, people, flowcharts.</p></a>
  <a class="card" data-animate href="<?= base_url('policies') ?>"><h3>Policy Portal</h3><p>Policies, memos, manuals and forms with version history.</p></a>
</div>
<?php if ($due): ?>
<div class="panel" data-animate>
  <h2>Reviews due</h2>
  <table class="tbl">
    <?php foreach ($due as $d): ?>
    <tr><td><a href="<?= base_url('document?id=' . $d['id']) ?>"><?= h($d['title']) ?></a></td><td><?= h($d['type']) ?></td>
      <td><span class="badge <?= $d['overdue'] ? 'warn' : '' ?>"><?= $d['overdue'] ? 'overdue ' : 'due ' ?><?= h($d['review_due']) ?></span></td></tr>
    <?php endforeach ?>
  </table>
</div>
<?php endif ?>
<?php require __DIR__ . '/../components/app_close.php';
