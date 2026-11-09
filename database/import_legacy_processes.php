<?php
// One-off: php database/import_legacy_processes.php  (needs database/seeds/legacy_processes.json, kept out of git)
// Imports the original users' 46 processes as version 1. Safe to re-run: skips names that already exist in the department.
require __DIR__ . '/../backend/services/processes.php';

$dept = ['hr' => 'HR - People & Culture', 'ops' => 'Process and Operations', 'client' => 'Client Success',
    'comp' => 'Legal & Business Compliance', 'it' => 'Information Technology', 'accfin' => 'Accounting and Finance',
    'fac' => 'Building and Facilities', 'maweb' => 'Marketing and WebDev', 'onim' => 'Onboarding and Implementation',
    'sal' => 'Sales and Development', 'tad' => 'Training and Development', 'exs' => 'Executive Services'];

$file = __DIR__ . '/seeds/legacy_processes.json';
if (!is_file($file)) exit("missing $file\n");
$pdo = db();
$admin = $pdo->query("SELECT id, role, department_id FROM users WHERE role = 'admin' AND active = 1 ORDER BY id LIMIT 1")->fetch();
if (!$admin) exit("create an admin first (database/seed.php)\n");
$ids = $pdo->query('SELECT name, id FROM departments')->fetchAll(PDO::FETCH_KEY_PAIR);

$n = $skipped = 0;
foreach (json_decode(file_get_contents($file), true) as $p) {
    $deptId = $ids[$dept[$p['cat']] ?? ''] ?? null;
    if (!$deptId) { echo "unknown category {$p['cat']} for {$p['title']}\n"; continue; }
    $s = $pdo->prepare('SELECT id FROM processes WHERE department_id = ? AND name = ?');
    $s->execute([$deptId, $p['title']]);
    if ($s->fetch()) { $skipped++; continue; }
    save_process($admin, null, $deptId, [
        'name' => $p['title'], 'purpose' => $p['desc'], 'owner_position' => $p['owner'] ?? '',
        'duration' => $p['duration'] ?? '', 'frequency' => $p['frequency'] ?? '',
        'changelog' => 'Imported from the original Process Library',
        // Legacy steps have no person/position; the department owner fills those in later.
        'steps' => array_map(fn($st) => ['title' => $st['name'], 'detail' => $st['detail'] ?? ''], $p['steps']),
    ]);
    $n++;
}
echo "imported $n, skipped $skipped existing\n";
