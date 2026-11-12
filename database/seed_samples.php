<?php
// One clearly labelled sample for each part, so new users have a template guide: php database/seed_samples.php
//   Process Library        -> "Sample: Weekly team report" (every field filled in)
//   Policy Portal          -> "Sample: Leave Policy"       (a policy with a dated version)
//   Library (forms etc.)   -> "Sample: Leave Request Form" (a form)
// Safe on production: no company data, only example text. Re-running skips samples that already exist.
require __DIR__ . '/../backend/services/processes.php';
require __DIR__ . '/../backend/services/documents.php';

$pdo = db();
$admin = $pdo->query("SELECT id, role, department_id FROM users WHERE role = 'admin' AND active = 1 ORDER BY id LIMIT 1")->fetch();
if (!$admin) exit("create an admin first (php database/seed.php)\n");
$dept = (int) ($pdo->query("SELECT id FROM departments WHERE slug LIKE 'process%' ORDER BY id LIMIT 1")->fetchColumn() ?: $pdo->query('SELECT id FROM departments ORDER BY sort LIMIT 1')->fetchColumn());
$made = 0;

// --- Process Library sample
$name = 'Sample: Weekly team report';
$s = $pdo->prepare("SELECT COUNT(*) FROM processes WHERE name = ? AND kind = 'process'");
$s->execute([$name]);
if (!$s->fetchColumn()) {
    save_process($admin, null, $dept, [
        'name' => $name, 'cadence' => 'weekly', 'kind' => 'process', 'status' => 'active', 'owner_position' => 'Team Lead',
        'purpose' => 'SAMPLE: shows how a finished process looks. Copy this layout, or use a template, then replace every line with your own.',
        'trigger' => 'Every Monday morning', 'frequency' => 'Every Monday 9:00', 'duration' => 'About 1 hour',
        'inputs' => 'Last week\'s numbers and the list of open items.',
        'kpi' => 'Report sent by 10:00 with no missing figures.',
        'exceptions' => 'A figure is missing: ask its owner, note it in the report and send on time.',
        'escalation' => 'Department Manager',
        'notes' => 'Tip: write steps the way you would explain them to someone on their first day.',
        'changelog' => 'Sample',
        'steps' => [
            ['title' => 'Collect last week\'s numbers', 'detail' => 'Open the shared sheet and copy the totals into the report.', 'role' => 'Analyst', 'tools' => 'Spreadsheet', 'output' => 'Filled report draft', 'time' => '30 minutes'],
            ['title' => 'Is anything missing?', 'detail' => 'Check every line has a number. If a line is empty, go back to step 1 and ask its owner.', 'role' => 'Analyst', 'time' => '5 minutes', 'decision' => '1', 'no_goto' => '1'],
            ['title' => 'Send the report', 'detail' => 'Email the report to the manager and the agreed recipients.', 'role' => 'Team Lead', 'tools' => 'Email', 'output' => 'Report sent', 'time' => '10 minutes'],
        ],
    ]);
    $made++;
}

// --- Document samples (a file with guidance text is written directly, as the app would store an upload)
$docs = [
    ['policy', 'Sample: Leave Policy', 'Sample', "SAMPLE POLICY\n\nPurpose: say what this policy is for.\nScope: say who it applies to.\nRules: list the rules in plain words.\nReview: say when it is checked again.\n\nUpload your real policy as a new document, or as a new version of this one.\n"],
    ['form', 'Sample: Leave Request Form', 'Sample', "SAMPLE FORM\n\nName:\nDepartment:\nDates requested:\nReason:\nManager approval:\n\nReplace this file with your real form. Staff can download it from the Policies & forms area.\n"],
];
foreach ($docs as [$type, $title, $cat, $text]) {
    $s = $pdo->prepare('SELECT COUNT(*) FROM documents WHERE title = ?');
    $s->execute([$title]);
    if ($s->fetchColumn()) continue;
    $pdo->prepare('INSERT INTO documents (type, title, category, owner_id, review_due) VALUES (?,?,?,?,CURDATE() + INTERVAL 365 DAY)')->execute([$type, $title, $cat, $admin['id']]);
    $id = (int) $pdo->query('SELECT MAX(id) FROM documents')->fetchColumn();   // not lastInsertId: audit() inserts after
    @mkdir(ROOT . "/files/uploads/$id", 0750, true);
    $file = bin2hex(random_bytes(8)) . '.txt';
    file_put_contents(ROOT . "/files/uploads/$id/$file", $text);
    $pdo->prepare('INSERT INTO document_versions (document_id, version_no, file_path, original_name, sha256, size_bytes, changelog, effective_date, uploaded_by) VALUES (?,?,?,?,?,?,?,CURDATE(),?)')
        ->execute([$id, '1.0', "files/uploads/$id/$file", "$title.txt", hash_file('sha256', ROOT . "/files/uploads/$id/$file"), filesize(ROOT . "/files/uploads/$id/$file"), 'Sample', $admin['id']]);
    $pdo->prepare('UPDATE documents SET current_version_id = ? WHERE id = ?')->execute([$pdo->query('SELECT MAX(id) FROM document_versions')->fetchColumn(), $id]);
    audit((int) $admin['id'], 'create', 'document', $id, null, ['title' => $title, 'type' => $type, 'sample' => true]);
    $made++;
}
echo "samples created: $made\n";
