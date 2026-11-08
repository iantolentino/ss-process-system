<?php
// Usage: php database/seed.php                          (departments only)
//        php database/seed.php admin@example.com "Pass"  (also creates the first admin, once)
require __DIR__ . '/../backend/bootstrap.php';

$pdo = db();
$depts = ['Accounting and Finance', 'Building and Facilities', 'Client Success', 'Executive Services',
    'HR - People & Culture', 'Information Technology', 'Legal & Business Compliance', 'Marketing and WebDev',
    'Onboarding and Implementation', 'Process and Operations', 'Sales and Development', 'Training and Development'];
$ins = $pdo->prepare('INSERT IGNORE INTO departments (name, slug, sort) VALUES (?,?,?)');
foreach ($depts as $i => $d) $ins->execute([$d, trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($d)), '-'), $i]);

if (isset($argv[2])) {
    if ($pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) exit("users exist; admin not created\n");
    if (strlen($argv[2]) < 8) exit("password must be at least 8 characters\n");
    $pdo->prepare("INSERT INTO users (first_name, last_name, email, password_hash, role) VALUES ('Admin','User',?,?,'admin')")
        ->execute([strtolower($argv[1]), password_hash($argv[2], PASSWORD_DEFAULT)]);
    audit(null, 'create', 'user', (int) $pdo->lastInsertId(), null, ['email' => $argv[1], 'role' => 'admin']);
    echo "admin created\n";
}
echo "seeded\n";
