<?php
// One-off: php database/import_legacy_users.php "path/to/employees.db"   (needs pdo_sqlite; never commit the .db)
// Copies name, email and access level only. Old passwords are NOT imported: each person gets an unusable random
// password and sets their own with "Forgot password". Safe to re-run: skips emails that already exist.
// Mapping (change here if wrong): admin -> admin, director -> policy_owner, manager -> process_owner, anything else -> employee.
require __DIR__ . '/../backend/bootstrap.php';

$file = $argv[1] ?? '';
if (!is_file($file)) exit("usage: php database/import_legacy_users.php path/to/employees.db\n");
$old = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$role = ['admin' => 'admin', 'director' => 'policy_owner', 'manager' => 'process_owner'];

$pdo = db();
$n = $skipped = 0;
foreach ($old->query('SELECT name, middile_name, lastname, email, access FROM employee_info') as $e) {
    $email = strtolower(trim($e['email']));
    $exists = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
    $exists->execute([$email]);
    if ($exists->fetch()) { $skipped++; continue; }
    $r = $role[strtolower($e['access'])] ?? 'employee';
    $pdo->prepare('INSERT INTO users (first_name, middle_name, last_name, email, password_hash, role) VALUES (?,?,?,?,?,?)')
        ->execute([trim($e['name']), trim($e['middile_name'] ?? ''), trim($e['lastname']), $email,
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $r]);
    audit(null, 'import', 'user', (int) $pdo->lastInsertId(), null, ['email' => $email, 'role' => $r, 'from' => 'legacy employees.db']);
    $n++;
}
echo "imported $n user(s), skipped $skipped existing. They must use 'Forgot password' to sign in; set their department on the Users page.\n";
