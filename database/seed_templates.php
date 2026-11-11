<?php
// Starter templates for the "New process" page: php database/seed_templates.php
// Generic daily / weekly / monthly / quarterly / yearly / as-needed layouts (all filled with example text to overwrite).
// Safe to re-run: templates that already exist (same name) are skipped. Admins can edit or remove them in the app.
require __DIR__ . '/../backend/services/processes.php';

$pdo = db();
$admin = $pdo->query("SELECT id, role, department_id FROM users WHERE role = 'admin' AND active = 1 ORDER BY id LIMIT 1")->fetch();
if (!$admin) exit("create an admin first (php database/seed.php)\n");
$dept = (int) ($pdo->query("SELECT id FROM departments WHERE slug LIKE 'process%' ORDER BY id LIMIT 1")->fetchColumn() ?: $pdo->query('SELECT id FROM departments ORDER BY sort LIMIT 1')->fetchColumn());

$base = ['kind' => 'template', 'status' => 'active', 'changelog' => 'Starter template'];
$step = fn(string $t, string $d, string $role = 'Team member', string $time = '') => ['title' => $t, 'detail' => $d, 'role' => $role, 'time' => $time];
$templates = [
  ['name' => 'Daily routine', 'cadence' => 'daily', 'purpose' => 'The tasks this team does every working day, in the order they are done, so anyone can cover for a colleague.',
   'trigger' => 'Start of the working day', 'frequency' => 'Every working day, 9:00', 'duration' => '1-2 hours in total',
   'inputs' => 'System access, yesterday\'s hand-over notes.', 'kpi' => 'All items done by end of day; nothing left overdue.', 'exceptions' => 'Someone is absent: the team lead reassigns the tasks.', 'escalation' => 'Team Lead, then Department Manager',
   'steps' => [$step('Check hand-over and priorities', 'Read yesterday\'s notes and the list of urgent items.', 'Team member', '10 minutes'),
               $step('Work the main queue', 'Process items in order of priority. Update the status as you go.', 'Team member', '60 minutes'),
               $step('Check for anything overdue', 'Look for items past their due date. Is anything stuck?', 'Team member', '10 minutes'),
               $step('Write the end-of-day note', 'Record what is done, what is waiting, and anything the next person must know.', 'Team member', '10 minutes')]],
  ['name' => 'Weekly routine', 'cadence' => 'weekly', 'purpose' => 'The tasks and the report this team completes once a week.',
   'trigger' => 'Same day every week, e.g. Monday morning', 'frequency' => 'Every Monday', 'duration' => 'About half a day',
   'inputs' => 'Last week\'s numbers, open items list.', 'kpi' => 'Report sent on time; open items reviewed.', 'exceptions' => 'Public holiday: do it on the next working day.', 'escalation' => 'Department Manager',
   'steps' => [$step('Collect last week\'s numbers', 'Pull the figures from the usual source.', 'Analyst', '45 minutes'),
               $step('Review open items', 'Go through everything still open and decide: continue, escalate or close.', 'Team Lead', '30 minutes'),
               $step('Is everything on track?', 'If not, note what is at risk before reporting.', 'Team Lead', '10 minutes') + ['decision' => true, 'no_goto' => 2],
               $step('Send the weekly report', 'Send to the manager and agreed recipients.', 'Team Lead', '15 minutes')]],
  ['name' => 'Monthly routine (month-end)', 'cadence' => 'monthly', 'purpose' => 'What this team does at month-end: reconcile, review, report and close.',
   'trigger' => 'Last working day of the month', 'frequency' => 'Monthly, working days 1-3', 'duration' => '2-3 working days',
   'inputs' => 'All items for the month are entered; approvals received.', 'kpi' => 'Closed by working day 3; no unexplained differences.', 'exceptions' => 'Late or missing input: ask the owner and record the delay.', 'escalation' => 'Department Manager, then Finance',
   'steps' => [$step('Confirm all items are in', 'Check that nothing for the month is missing.', 'Team Lead', '1 hour'),
               $step('Reconcile and check totals', 'Compare against the source. Explain any differences.', 'Analyst', '4 hours'),
               $step('Manager review', 'The manager checks the summary and signs off.', 'Department Manager', '1 hour'),
               $step('Publish the monthly report', 'Share with the agreed audience and file a copy.', 'Team Lead', '30 minutes')]],
  ['name' => 'Quarterly review', 'cadence' => 'quarterly', 'purpose' => 'A review of results, risks and plans every quarter, presented to leadership.',
   'trigger' => 'First two weeks after a quarter ends', 'frequency' => 'Four times a year', 'duration' => '1 week',
   'inputs' => 'Three monthly reports, targets, open risks.', 'kpi' => 'Presented on schedule; actions agreed and assigned.', 'exceptions' => 'Targets missed: prepare the reasons and a recovery plan.', 'escalation' => 'Department Head',
   'steps' => [$step('Gather the quarter\'s results', 'Combine the monthly reports and compare with targets.', 'Analyst', '1 day'),
               $step('Prepare the review pack', 'Summarise results, risks and proposals.', 'Department Manager', '2 days'),
               $step('Present and agree actions', 'Meet leadership; record decisions and owners.', 'Department Head', '1 hour')]],
  ['name' => 'Yearly cycle', 'cadence' => 'yearly', 'purpose' => 'Tasks that happen once a year, such as renewals, audits or annual reviews.',
   'trigger' => 'Same month every year', 'frequency' => 'Once a year', 'duration' => 'Several weeks',
   'inputs' => 'Last year\'s file, current policies and contracts.', 'kpi' => 'Finished before the deadline; no findings left open.', 'exceptions' => 'Deadline moved by an outside party: re-plan with the owner.', 'escalation' => 'Department Head',
   'steps' => [$step('Plan the year\'s timeline', 'Set the dates, owners and what is needed from others.', 'Department Manager', '1 day'),
               $step('Do the work', 'Carry out the tasks in the plan and track progress.', 'Team member', 'Weeks'),
               $step('Review and sign off', 'Check the results against last year and sign off.', 'Department Head', '1 day'),
               $step('File and set next year\'s reminder', 'Store the records and put next year\'s date in the calendar.', 'Team Lead', '1 hour')]],
  ['name' => 'As-needed request', 'cadence' => 'as_needed', 'purpose' => 'How a one-off request is received, handled and closed when it comes up.',
   'trigger' => 'A request arrives', 'frequency' => 'When requested', 'duration' => '1-3 days',
   'inputs' => 'The request with enough detail to act on.', 'kpi' => 'Answered within the agreed time; requester confirms it is resolved.', 'exceptions' => 'Request is unclear: ask the requester before starting.', 'escalation' => 'Team Lead',
   'steps' => [$step('Receive and log the request', 'Record who asked, what they need and by when.', 'Team member', '10 minutes'),
               $step('Is the request clear and allowed?', 'If not, go back to the requester for more information.', 'Team Lead', '10 minutes') + ['decision' => true, 'no_goto' => 1],
               $step('Do the work', 'Handle the request and keep the requester updated.', 'Team member', '1-2 days'),
               $step('Confirm and close', 'Check with the requester and close the request.', 'Team member', '10 minutes')]],
];

$n = 0;
foreach ($templates as $t) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM processes WHERE kind = 'template' AND name = ?");
    $s->execute([$t['name']]);
    if ($s->fetchColumn()) continue;
    save_process($admin, null, $dept, $t + $base + ['owner_position' => '']);
    $n++;
}
echo "templates created: $n\n";
