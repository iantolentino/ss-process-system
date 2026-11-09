<?php
// Admin-side user changes. Users are disabled, never deleted, so history keeps pointing at real people.
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../authentication/auth.php';

// Create ($id null) or update a user. Throws UserError with a fixable message. Returns the user id.
function user_save(array $actor, ?int $id, array $in): int {
    $first = trim($in['first_name'] ?? '');
    $last = trim($in['last_name'] ?? '');
    $email = strtolower(trim($in['email'] ?? ''));
    $role = $in['role'] ?? '';
    $active = !empty($in['active']) ? 1 : 0;
    $pw = (string) ($in['password'] ?? '');
    $dept = ($in['department_id'] ?? '') === '' ? null : (int) $in['department_id'];

    if ($first === '' || $last === '') throw new UserError('First and last name are required.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new UserError('Enter a valid email.');
    if (!in_array($role, ROLES, true)) throw new UserError('Pick a role.');
    if ($id === null && $pw === '') throw new UserError('A starting password is required.');
    if ($pw !== '' && strlen($pw) < 8) throw new UserError('Password must be at least 8 characters.');

    $pos = ($in['position_id'] ?? '') === '' ? null : (int) $in['position_id'];
    $pdo = db();
    if ($pos !== null) {
        $s = $pdo->prepare('SELECT department_id FROM positions WHERE id = ?');
        $s->execute([$pos]);
        $pd = $s->fetchColumn();
        if ($pd === false) throw new UserError('Pick an existing position.');
        if ($pd != $dept) throw new UserError("The position must belong to the user's department.");
    }
    $before = null;
    if ($id !== null) {
        $s = $pdo->prepare('SELECT id, first_name, middle_name, last_name, email, role, department_id, position_id, active FROM users WHERE id = ?');
        $s->execute([$id]);
        if (!($before = $s->fetch())) throw new UserError('User not found.');
        $losesAdmin = $before['role'] === 'admin' && $before['active'] && ($role !== 'admin' || !$active);
        if ($id === (int) $actor['id'] && $losesAdmin) throw new UserError('You cannot remove your own admin access.');
        if ($losesAdmin && $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1")->fetchColumn() <= 1) {
            throw new UserError('You cannot remove the last admin.');
        }
    }

    $row = [$first, trim($in['middle_name'] ?? ''), $last, $email, $role, $dept, $pos, $active];
    try {
        if ($id === null) {
            $pdo->prepare('INSERT INTO users (first_name, middle_name, last_name, email, role, department_id, position_id, active, password_hash) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([...$row, password_hash($pw, PASSWORD_DEFAULT)]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $pdo->prepare('UPDATE users SET first_name=?, middle_name=?, last_name=?, email=?, role=?, department_id=?, position_id=?, active=? WHERE id=?')
                ->execute([...$row, $id]);
            if ($pw !== '') $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') throw new UserError('That email is already used.');
        throw $e;
    }

    $after = ['first_name' => $first, 'last_name' => $last, 'email' => $email, 'role' => $role, 'department_id' => $dept, 'position_id' => $pos, 'active' => $active];
    if ($pw !== '') $after['password_changed'] = true;
    audit((int) $actor['id'], $before ? 'update' : 'create', 'user', $id, $before, $after);
    return $id;
}
