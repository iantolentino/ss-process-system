<?php
$user = require_login();

// Active documents overdue or due for review within 30 days.
$due = db()->query("SELECT id, type, title, review_due, review_due < CURDATE() AS overdue FROM documents
                    WHERE status = 'active' AND review_due IS NOT NULL AND review_due <= CURDATE() + INTERVAL 30 DAY
                    ORDER BY review_due LIMIT 10")->fetchAll();
// Documents this person is asked to read and has not confirmed yet (current version only).
$toRead = db()->prepare("SELECT d.id, d.type, d.title FROM documents d LEFT JOIN acknowledgements k ON k.version_id = d.current_version_id AND k.user_id = ?
                         WHERE d.status = 'active' AND d.ack_required = 1 AND k.user_id IS NULL ORDER BY d.title LIMIT 20");
$toRead->execute([$user['id']]);
$toRead = $toRead->fetchAll();
$mySlug = '';
if ($user['department_id']) {
    $s = db()->prepare('SELECT slug FROM departments WHERE id = ?');
    $s->execute([$user['department_id']]);
    $mySlug = (string) $s->fetchColumn();
}
$isDocEditor = in_array($user['role'], ['admin', 'policy_owner'], true);
$isProcEditor = $user['role'] === 'admin' || ($user['role'] === 'process_owner' && $mySlug);

$title = 'Home';
require __DIR__ . '/../components/app_open.php';
$card = fn(string $href, string $ico, string $head, string $text) =>
    '<a class="card" data-animate href="' . h(base_url($href)) . '"><span class="ico">' . icon($ico) . '</span><h2>' . h($head) . '</h2><p>' . h($text) . '</p></a>';
?>
<div class="page-head"><div>
  <h1 class="title" data-animate>Welcome, <?= h($user['first_name']) ?></h1>
  <p class="lead">Not sure where to start? Pick what you want to do below.</p>
</div></div>

<?php if (!empty($waitingCount)): ?><div class="alert ok"><?= $waitingCount ?> change<?= $waitingCount === 1 ? ' is' : 's are' ?> waiting for your approval. <a href="<?= base_url('approvals') ?>">Open approvals</a></div><?php endif ?>
<?php if ($toRead): ?>
<div class="panel" data-animate>
  <h2>Please read and confirm</h2>
  <p class="help">Open each document, read it, then press “I have read and understood this”.</p>
  <ul class="plain"><?php foreach ($toRead as $d): ?><li><a href="<?= base_url('document?id=' . $d['id']) ?>"><?= h($d['title']) ?></a> <span class="muted">(<?= h($d['type']) ?>)</span></li><?php endforeach ?></ul>
</div>
<?php endif ?>

<p class="section-title">What do you want to do?</p>
<div class="cards">
  <?php if ($user['department_id']): ?>
  <?= $card('handbook', 'book', 'New here? Start here', 'Your department in one page: who is who and every process, step by step.') ?>
  <?= $card('onboarding', 'check', 'My checklist', 'What to do in your first weeks, with links to what you need to read.') ?>
  <?php endif ?>
  <?= $card('overview', 'grid', 'See how every department works', 'All departments side by side: daily, weekly and monthly processes.') ?>
  <?= $card('process-library', 'flow', 'Learn how a job is done', 'Step-by-step guides with a flowchart and who does each step.') ?>
  <?= $card('policies', 'file', 'Find a policy, form or memo', 'Search the company documents. Every version is kept.') ?>
  <?= $card('glossary', 'tag', 'Look up a word', 'Company terms and acronyms explained.') ?>
</div>

<?php if ($isDocEditor || $isProcEditor): ?>
<p class="section-title">Your tasks as <?= h(str_replace('_', ' ', $user['role'])) ?></p>
<div class="cards">
  <?php if ($isDocEditor): ?><?= $card('policies?add=1', 'plus', 'Upload a policy or form', 'Add a new document, or open one to upload a new version.') ?><?php endif ?>
  <?php if ($isProcEditor): ?><?= $card($mySlug ? 'process-edit?dept=' . $mySlug : 'process-library', 'plus', $mySlug ? 'Add a process' : 'Add or update a process', $mySlug ? 'Write down how a job is done in your department.' : 'Pick a department, then add or edit its processes.') ?><?php endif ?>
  <?php if ($user['role'] === 'admin'): ?><?= $card('users?new=1', 'users', 'Add a person', 'Create an account and choose what they can do.') ?><?php endif ?>
</div>
<?php endif ?>

<?php if ($isDocEditor): ?>
<div class="panel" data-animate>
  <h2>Documents to review</h2>
  <?php if ($due): ?>
  <p class="help">Each company document has a date when someone should check it is still right.</p>
  <div class="table-wrap"><table class="tbl">
    <?php foreach ($due as $d): ?>
    <tr><td><a href="<?= base_url('document?id=' . $d['id']) ?>"><?= h($d['title']) ?></a></td><td><?= h(ucfirst($d['type'])) ?></td>
      <td><span class="badge <?= $d['overdue'] ? 'warn' : 'off' ?>"><?= $d['overdue'] ? 'Overdue since ' : 'Due ' ?><?= h($d['review_due']) ?></span></td></tr>
    <?php endforeach ?>
  </table></div>
  <?php else: ?><p class="muted">Nothing needs review in the next 30 days.</p><?php endif ?>
</div>
<?php endif ?>
<p class="muted">Need a walkthrough? <a href="<?= base_url('help') ?>">How to use this site</a>.</p>
<?php require __DIR__ . '/../components/app_close.php';
