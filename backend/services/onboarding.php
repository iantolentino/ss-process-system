<?php
// Onboarding checklists (per department, optionally per position) and the glossary.
require_once __DIR__ . '/processes.php';   // can_edit_process(), text()

function can_edit_glossary(array $user): bool {
    return in_array($user['role'], ['admin', 'policy_owner', 'process_owner'], true);
}

// --- checklist

function add_item(array $user, int $deptId, array $in): void {
    $title = text($in, 'title', 255);
    $week = (int) ($in['week_no'] ?? 1);
    if ($title === '') throw new UserError('Give the item a title.');
    if ($week < 0 || $week > 12) throw new UserError('Week must be 0 (ongoing) to 12.');
    $pdo = db();
    $pos = ($in['position_id'] ?? '') === '' ? null : (int) $in['position_id'];
    if ($pos !== null) {
        $s = $pdo->prepare('SELECT COUNT(*) FROM positions WHERE id = ? AND department_id = ?');
        $s->execute([$pos, $deptId]);
        if (!$s->fetchColumn()) throw new UserError('That position is not in this department.');
    }
    $proc = ($in['process_id'] ?? '') === '' ? null : (int) $in['process_id'];
    $doc = ($in['document_id'] ?? '') === '' ? null : (int) $in['document_id'];
    if ($proc !== null && !$pdo->query("SELECT COUNT(*) FROM processes WHERE id = $proc AND status = 'active'")->fetchColumn()) throw new UserError('Pick an existing process.');
    if ($doc !== null && !$pdo->query("SELECT COUNT(*) FROM documents WHERE id = $doc AND status = 'active'")->fetchColumn()) throw new UserError('Pick an existing document.');

    $pdo->prepare('INSERT INTO onboarding_items (department_id, position_id, week_no, title, detail, process_id, document_id) VALUES (?,?,?,?,?,?,?)')
        ->execute([$deptId, $pos, $week, $title, text($in, 'detail'), $proc, $doc]);
    audit((int) $user['id'], 'create', 'onboarding_item', (int) $pdo->lastInsertId(), null,
        ['department_id' => $deptId, 'position_id' => $pos, 'week_no' => $week, 'title' => $title]);
}

function delete_item(array $user, int $deptId, int $id): void {
    $s = db()->prepare('SELECT * FROM onboarding_items WHERE id = ? AND department_id = ?');
    $s->execute([$id, $deptId]);
    if (!($row = $s->fetch())) throw new UserError('Item not found.');
    db()->prepare('DELETE FROM onboarding_items WHERE id = ?')->execute([$id]);   // progress rows cascade
    audit((int) $user['id'], 'delete', 'onboarding_item', $id, $row, null);
}

// Items that apply to this user: their department, for everyone or for their position.
const ITEM_WHERE = 'i.department_id = ? AND (i.position_id IS NULL OR i.position_id <=> ?)';

function items_for(array $user): array {
    $s = db()->prepare('SELECT i.*, pr.name AS process_name, d.title AS doc_title, p.done_at, po.title AS position
                        FROM onboarding_items i
                        LEFT JOIN processes pr ON pr.id = i.process_id AND pr.status = "active"
                        LEFT JOIN documents d ON d.id = i.document_id AND d.status = "active"
                        LEFT JOIN onboarding_progress p ON p.item_id = i.id AND p.user_id = ?
                        LEFT JOIN positions po ON po.id = i.position_id
                        WHERE ' . ITEM_WHERE . ' ORDER BY (i.week_no = 0), i.week_no, i.id');
    $s->execute([$user['id'], $user['department_id'], $user['position_id']]);
    return $s->fetchAll();
}

function set_item_done(array $user, int $itemId, bool $done): void {
    $s = db()->prepare('SELECT COUNT(*) FROM onboarding_items i WHERE i.id = ? AND ' . ITEM_WHERE);
    $s->execute([$itemId, $user['department_id'], $user['position_id']]);
    if (!$s->fetchColumn()) throw new UserError('That item is not on your checklist.');
    if ($done) db()->prepare('INSERT IGNORE INTO onboarding_progress (user_id, item_id) VALUES (?,?)')->execute([$user['id'], $itemId]);
    else db()->prepare('DELETE FROM onboarding_progress WHERE user_id = ? AND item_id = ?')->execute([$user['id'], $itemId]);
}

// Manager view: each active person in the department with done/total.
function team_progress(int $deptId): array {
    $s = db()->prepare('SELECT u.id, CONCAT(u.first_name, " ", u.last_name) AS name, po.title AS position,
        (SELECT COUNT(*) FROM onboarding_items i WHERE i.department_id = u.department_id AND (i.position_id IS NULL OR i.position_id <=> u.position_id)) AS total,
        (SELECT COUNT(*) FROM onboarding_progress p JOIN onboarding_items i ON i.id = p.item_id
          WHERE p.user_id = u.id AND i.department_id = u.department_id AND (i.position_id IS NULL OR i.position_id <=> u.position_id)) AS done
        FROM users u LEFT JOIN positions po ON po.id = u.position_id WHERE u.department_id = ? AND u.active = 1 ORDER BY u.first_name');
    $s->execute([$deptId]);
    return $s->fetchAll();
}

// --- glossary

function save_term(array $user, ?int $id, array $in): void {
    $term = text($in, 'term', 120);
    $def = text($in, 'definition', 2000);
    $dept = ($in['department_id'] ?? '') === '' ? null : (int) $in['department_id'];
    if ($term === '' || $def === '') throw new UserError('A term and its meaning are both required.');
    $pdo = db();
    $before = null;
    if ($id !== null) {
        $s = $pdo->prepare('SELECT term, definition, department_id FROM glossary WHERE id = ?');
        $s->execute([$id]);
        if (!($before = $s->fetch())) throw new UserError('Term not found.');
    }
    try {
        if ($id === null) {
            $pdo->prepare('INSERT INTO glossary (term, definition, department_id) VALUES (?,?,?)')->execute([$term, $def, $dept]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $pdo->prepare('UPDATE glossary SET term = ?, definition = ?, department_id = ? WHERE id = ?')->execute([$term, $def, $dept, $id]);
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000' && strpos($e->getMessage(), 'term') !== false) throw new UserError("“$term” is already in the glossary.");
        throw $e;
    }
    audit((int) $user['id'], $before ? 'update' : 'create', 'glossary', $id, $before, ['term' => $term, 'definition' => $def, 'department_id' => $dept]);
}

function delete_term(array $user, int $id): void {
    $s = db()->prepare('SELECT term, definition, department_id FROM glossary WHERE id = ?');
    $s->execute([$id]);
    if (!($row = $s->fetch())) throw new UserError('Term not found.');
    db()->prepare('DELETE FROM glossary WHERE id = ?')->execute([$id]);
    audit((int) $user['id'], 'delete', 'glossary', $id, $row, null);
}
