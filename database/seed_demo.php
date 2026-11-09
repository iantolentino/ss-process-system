<?php
// LOCAL ONLY demo data: php database/seed_demo.php
// Fictional people (@example.test), positions, onboarding, glossary, sample documents and change-request "tickets" that point at
// whatever processes are already in the database (e.g. the imported legacy ones). Safe to re-run: stops if the demo users exist.
// All demo accounts use the password DemoPass123. Never run this on production.
require __DIR__ . '/../backend/services/users.php';
require __DIR__ . '/../backend/services/processes.php';
require __DIR__ . '/../backend/services/onboarding.php';
require __DIR__ . '/../backend/services/documents.php';

if (env('APP_ENV') !== 'local') exit("refusing: APP_ENV is not local\n");
$pdo = db();
if ($pdo->query("SELECT COUNT(*) FROM users WHERE email = 'demo.admin@example.test'")->fetchColumn()) exit("demo data already seeded\n");
$admin = $pdo->query("SELECT id, first_name, last_name, role, department_id FROM users WHERE role = 'admin' AND active = 1 ORDER BY id LIMIT 1")->fetch();
if (!$admin) exit("create an admin first (database/seed.php)\n");

$dept = fn(string $slug) => (int) $pdo->query("SELECT id FROM departments WHERE slug = " . $pdo->quote($slug))->fetchColumn();
$cs = $dept('client-success'); $it = $dept('information-technology');

// positions (title, holder, reports-to title)
$pos = [];
foreach ([[$cs, 'Client Success Manager', 'Maria Santos', null], [$cs, 'Team Lead', 'Jon Reyes', 'Client Success Manager'], [$cs, 'Support Agent', 'Ana Cruz', 'Team Lead'],
          [$it, 'IT Manager', 'Leo Garcia', null], [$it, 'IT Support', 'Kim Lopez', 'IT Manager']] as [$d, $title, $holder, $up]) {
    add_position($admin, $d, ['title' => $title, 'holder_name' => $holder, 'reports_to_id' => $up ? $pos["$d|$up"] : '']);
    $pos["$d|$title"] = (int) $pdo->query('SELECT MAX(id) FROM positions')->fetchColumn();   // not lastInsertId: audit() inserts after
}

// users
$mk = function (string $first, string $last, string $email, string $role, ?int $d, ?int $p) use ($admin) {
    user_save($admin, null, ['first_name' => $first, 'last_name' => $last, 'email' => $email, 'role' => $role, 'department_id' => $d ?? '',
        'position_id' => $p ?? '', 'password' => 'DemoPass123', 'active' => 1]);
    return (int) db()->query('SELECT MAX(id) FROM users')->fetchColumn();
};
$mk('Demo', 'Admin', 'demo.admin@example.test', 'admin', null, null);
$po = $mk('Demo', 'PolicyOwner', 'demo.policy@example.test', 'policy_owner', null, null);
$cso = $mk('Demo', 'CsOwner', 'demo.cs.owner@example.test', 'process_owner', $cs, $pos["$cs|Client Success Manager"]);
$agent = $mk('Demo', 'Agent', 'demo.cs.agent@example.test', 'employee', $cs, $pos["$cs|Support Agent"]);
$mk('Demo', 'Lead', 'demo.cs.lead@example.test', 'employee', $cs, $pos["$cs|Team Lead"]);
$mk('Demo', 'ItSupport', 'demo.it.agent@example.test', 'employee', $it, $pos["$it|IT Support"]);
$policyOwner = ['id' => $po, 'role' => 'policy_owner', 'first_name' => 'Demo', 'last_name' => 'PolicyOwner', 'department_id' => null];

// sample documents (files written directly: uploads need a web request)
$docs = [['policy', 'Code of Conduct', 'HR', '1.0'], ['policy', 'Remote Work Policy', 'HR', '2.1'], ['manual', 'Support Handbook', 'Client Success', '1.0'],
         ['form', 'Leave Request Form', 'HR', '1.0'], ['memo', 'Office Hours Update', 'Announcements', '1.0']];
foreach ($docs as [$type, $title, $cat, $ver]) {
    $pdo->prepare('INSERT INTO documents (type, title, category, owner_id, review_due) VALUES (?,?,?,?,CURDATE() + INTERVAL ? DAY)')
        ->execute([$type, $title, $cat, $po, $title === 'Code of Conduct' ? -5 : 200]);   // one overdue review for the dashboard
    $id = (int) $pdo->lastInsertId();
    @mkdir(ROOT . "/files/uploads/$id", 0750, true);
    $name = bin2hex(random_bytes(8)) . '.txt';
    file_put_contents(ROOT . "/files/uploads/$id/$name", "Demo content for $title (version $ver).\n");
    $pdo->prepare('INSERT INTO document_versions (document_id, version_no, file_path, original_name, sha256, size_bytes, changelog, effective_date, uploaded_by) VALUES (?,?,?,?,?,?,?,CURDATE(),?)')
        ->execute([$id, $ver, "files/uploads/$id/$name", "$title.txt", hash_file('sha256', ROOT . "/files/uploads/$id/$name"), filesize(ROOT . "/files/uploads/$id/$name"), 'Initial version', $po]);
    $pdo->prepare('UPDATE documents SET current_version_id = ? WHERE id = ?')->execute([$pdo->lastInsertId(), $id]);
    audit($po, 'create', 'document', $id, null, ['title' => $title, 'type' => $type, 'demo' => true]);
}

// onboarding + glossary
$proc = (int) $pdo->query("SELECT id FROM processes WHERE department_id = $cs AND status = 'active' ORDER BY id LIMIT 1")->fetchColumn();
$cso_user = ['id' => $cso, 'role' => 'process_owner', 'department_id' => $cs, 'first_name' => 'Demo', 'last_name' => 'CsOwner'];
foreach ([[1, 'Meet your team and your Team Lead', ''], [1, 'Read the Support Handbook', ''], [2, 'Shadow a live ticket', $pos["$cs|Support Agent"]],
          [3, 'Handle your first client request', $pos["$cs|Support Agent"]], [0, 'Review the Code of Conduct yearly', '']] as [$wk, $t, $p]) {
    add_item($cso_user, $cs, ['title' => $t, 'week_no' => $wk, 'position_id' => $p, 'process_id' => $t === 'Shadow a live ticket' && $proc ? $proc : '']);
}
foreach ([['SLA', 'Service level agreement: how fast we promise to respond.', ''], ['CSAT', 'Customer satisfaction score.', $cs], ['VPN', 'Secure connection to the office network.', $it]] as [$term, $def, $d]) {
    save_term($policyOwner, null, ['term' => $term, 'definition' => $def, 'department_id' => $d]);
}

// change-request "tickets" on the first processes of Client Success (open, approved, rejected)
$ids = $pdo->query("SELECT id FROM processes WHERE department_id = $cs AND status = 'active' ORDER BY id LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
$agentUser = ['id' => $agent, 'first_name' => 'Demo', 'last_name' => 'Agent', 'role' => 'employee', 'department_id' => $cs];
$tickets = [['Add a step to check the client record', 'Agents often skip this and call the wrong person.', null], ['Wrong tool named in step 2', 'We use the new CRM now.', 'approved'], ['Remove the manual approval', 'It slows everything down.', 'rejected']];
foreach ($ids as $i => $pid) {
    add_change_request($agentUser, (int) $pid, ['title' => $tickets[$i][0], 'details' => $tickets[$i][1]]);
    if ($tickets[$i][2]) resolve_change_request($cso_user, (int) $pdo->query('SELECT MAX(id) FROM change_requests')->fetchColumn(), $tickets[$i][2]);
}
echo "demo data ready: 6 users (demo.*@example.test / DemoPass123), 5 positions, 5 documents, onboarding, glossary, " . count($ids) . " change requests\n";
