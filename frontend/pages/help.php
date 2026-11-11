<?php
$user = require_login();
$title = 'How to use this site';
require __DIR__ . '/../components/app_open.php';
$r = $user['role'];
?>
<div class="page-head"><div>
  <h1 class="title" data-animate>How to use this site</h1>
  <p class="lead">Everything the company knows about how work gets done, in one place: how jobs are done, and which rules apply.</p>
</div></div>

<div class="panel" data-animate>
  <h2>The three places you will use</h2>
  <ul class="plain">
    <li><strong><a href="<?= base_url('process-library') ?>">Processes</a></strong>: pick your department, then a job. You see a flowchart, who does each step, which tools they use and what comes out.</li>
    <li><strong><a href="<?= base_url('policies') ?>">Policies &amp; forms</a></strong>: policies, memos, manuals and forms. Open one to read, download or see older versions.</li>
    <li><strong><a href="<?= base_url('glossary') ?>">Glossary</a></strong>: what an acronym or company word means.</li>
  </ul>
</div>

<?php if ($user['department_id']): ?>
<div class="panel" data-animate>
  <h2>If you are new</h2>
  <ol style="padding-left:20px;display:grid;gap:6px">
    <li>Open <a href="<?= base_url('handbook') ?>">Start here</a>. It shows your department's people and every process. You can print it.</li>
    <li>Go to <a href="<?= base_url('onboarding') ?>">My checklist</a> and tick things off as you finish them.</li>
    <li>Not sure how something works? Open it in <a href="<?= base_url('process-library') ?>">Processes</a> and read the steps top to bottom.</li>
  </ol>
</div>
<?php endif ?>

<div class="panel" data-animate>
  <h2>Something is wrong or out of date?</h2>
  <p>Open the process, go to the <strong>Requests</strong> tab and use <strong>Suggest a change</strong>. The person who owns that process gets an email and you get the answer by email.</p>
</div>

<?php if (in_array($r, ['admin', 'policy_owner'], true)): ?>
<div class="panel" data-animate>
  <h2>Uploading policies and forms</h2>
  <ul class="plain">
    <li><strong>New document:</strong> Policies &amp; forms → <em>Add a document</em>. Give it a title, a version number, the date it starts to apply, and the file.</li>
    <li><strong>New version:</strong> open the document → <em>Upload a new version</em> and say what changed. The old version stays available. Nothing is overwritten.</li>
    <li><strong>Compare:</strong> in the version list, <em>Compare with previous</em> shows what changed (line by line for .txt and .csv files).</li>
    <li><strong>Remove a document:</strong> set its status to archived. It is hidden from staff but never deleted.</li>
    <li><strong>Review date:</strong> set when it should next be checked. The Home page and a weekly email show what is due.</li>
  </ul>
</div>
<?php endif ?>

<?php if (in_array($r, ['admin', 'process_owner'], true)): ?>
<div class="panel" data-animate>
  <h2>Writing and updating processes</h2>
  <ul class="plain">
    <li><strong>New process:</strong> Processes → your department → <em>New process</em>. Pick a <em>template</em> (daily, weekly, monthly…) and change what does not fit, or start blank. Choose how often it happens so it is listed under Daily, Weekly, Monthly and so on.</li>
    <li><strong>Steps:</strong> for each step say who does it, what they do, the tools used, what comes out and how long it takes.</li>
    <li><strong>Details for readers:</strong> what is needed first, how we know it went well, what can go wrong and who to ask when stuck. These fill the Overview tab that executives read first.</li>
    <li><strong>Remove:</strong> hides a process but keeps it and its history; use <em>Restore</em> to bring it back.</li>
    <li><strong>Decisions:</strong> tick "yes/no decision" on a step and say which step to go to if the answer is No.</li>
    <li><strong>Every save is a new version.</strong> Old versions stay and you can see what changed.</li>
    <li><strong>Who is who:</strong> add your positions and who reports to whom on the department page. New hires rely on it.</li>
    <li><strong>Still correct?</strong> Press <em>Confirm still correct</em> on a process after you check it.</li>
    <li><strong>Requests:</strong> staff suggestions arrive on the process's Requests tab. Approve, reject, or mark them done.</li>
  </ul>
</div>
<?php endif ?>

<?php if ($r === 'admin'): ?>
<div class="panel" data-animate>
  <h2>Admin</h2>
  <ul class="plain">
    <li><strong>People &amp; roles:</strong> add people and choose their role: <em>admin</em> (everything), <em>policy owner</em> (policies and forms), <em>process owner</em> (processes of their department), <em>employee</em> (read and suggest). Disable a person instead of deleting so history keeps their name.</li>
    <li><strong>Activity history:</strong> who changed, uploaded or downloaded what and when, with before and after values. You can export it.</li>
  </ul>
</div>
<?php endif ?>
<?php require __DIR__ . '/../components/app_close.php';
