<?php
// Process Library: processes per department. Each save creates a new immutable version holding the
// whole process (details + ordered steps) as JSON, so history, handoff views and the flowchart share one source.
require_once __DIR__ . '/../bootstrap.php';

const PROC_STATUS = ['draft', 'active', 'archived'];
const MAX_STEPS = 60;

function can_edit_process(array $user, int $deptId): bool {
    return $user['role'] === 'admin' || ($user['role'] === 'process_owner' && (int) $user['department_id'] === $deptId);
}

function text(array $a, string $k, int $max = 2000): string {
    return mb_substr(trim((string) ($a[$k] ?? '')), 0, $max);
}

// Normalises the posted form into the snapshot stored in process_versions.
function build_snapshot(array $in): array {
    $steps = [];
    foreach (array_values($in['steps'] ?? []) as $s) {
        if (!is_array($s) || text($s, 'title') === '') continue;   // blank rows are ignored
        $decision = !empty($s['decision']);
        $steps[] = [
            'title' => text($s, 'title', 200),
            'detail' => text($s, 'detail'),
            'role' => text($s, 'role', 160),
            'person' => text($s, 'person', 160),
            'tools' => text($s, 'tools', 300),
            'output' => text($s, 'output', 300),
            'decision' => $decision,
            // For decisions: step number to jump to when the answer is "No" (blank = process ends).
            'no_goto' => $decision ? (int) ($s['no_goto'] ?? 0) : 0,
        ];
    }
    if (!$steps) throw new UserError('Add at least one step.');
    if (count($steps) > MAX_STEPS) throw new UserError('A process can have at most ' . MAX_STEPS . ' steps.');
    foreach ($steps as $i => $s) {
        if ($s['no_goto'] < 0 || $s['no_goto'] > count($steps) || $s['no_goto'] === $i + 1) {
            throw new UserError('Step ' . ($i + 1) . ': "No" must go to another existing step number (or blank to end).');
        }
    }
    return [
        'trigger' => text($in, 'trigger', 500),
        'duration' => text($in, 'duration', 80),
        'frequency' => text($in, 'frequency', 80),
        'notes' => text($in, 'notes'),
        // Policies / forms this process relies on (document ids). Shown as links; missing or archived ones are skipped on display.
        'docs' => array_slice(array_values(array_unique(array_filter(array_map('intval', (array) ($in['docs'] ?? []))))), 0, 30),
        'steps' => $steps,
    ];
}

// Creates ($id null) or updates a process, always adding a new version. Returns the process id.
function save_process(array $user, ?int $id, int $deptId, array $in): int {
    $name = text($in, 'name', 255);
    $purpose = text($in, 'purpose', 4000);
    $status = $in['status'] ?? 'active';
    $changelog = text($in, 'changelog');
    if ($name === '' || $purpose === '') throw new UserError('Name and purpose are required.');
    if (!in_array($status, PROC_STATUS, true)) throw new UserError('Pick a status.');
    $snap = build_snapshot($in);

    $pdo = db();
    $before = null;
    if ($id !== null) {
        $s = $pdo->prepare('SELECT p.name, p.purpose, p.owner_position, p.status, v.version_no, v.snapshot
                            FROM processes p LEFT JOIN process_versions v ON v.id = p.current_version_id WHERE p.id = ?');
        $s->execute([$id]);
        if (!($before = $s->fetch())) throw new UserError('Process not found.');
        $before['snapshot'] = json_decode($before['snapshot'] ?? 'null', true);
        if ($changelog === '') throw new UserError('Describe what changed in this version.');
    } elseif ($changelog === '') {
        $changelog = 'Initial version';
    }

    $pdo->beginTransaction();
    try {
        if ($id === null) {
            $pdo->prepare('INSERT INTO processes (department_id, name, purpose, owner_position, status) VALUES (?,?,?,?,?)')
                ->execute([$deptId, $name, $purpose, text($in, 'owner_position', 160), $status]);
            $id = (int) $pdo->lastInsertId();
            $no = 1;
        } else {
            $pdo->prepare('UPDATE processes SET name=?, purpose=?, owner_position=?, status=? WHERE id=?')
                ->execute([$name, $purpose, text($in, 'owner_position', 160), $status, $id]);
            $no = (int) $pdo->query("SELECT COALESCE(MAX(version_no),0)+1 FROM process_versions WHERE process_id = $id")->fetchColumn();
        }
        $pdo->prepare('INSERT INTO process_versions (process_id, version_no, snapshot, changelog, created_by) VALUES (?,?,?,?,?)')
            ->execute([$id, $no, json_encode($snap, JSON_UNESCAPED_UNICODE), $changelog, $user['id']]);
        $pdo->prepare('UPDATE processes SET current_version_id = ? WHERE id = ?')->execute([$pdo->lastInsertId(), $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    audit((int) $user['id'], $before ? 'new_version' : 'create', 'process', $id, $before,
        ['name' => $name, 'status' => $status, 'version_no' => $no, 'changelog' => $changelog, 'snapshot' => $snap]);
    return $id;
}

// Active documents for a list of ids (silently drops archived/deleted ones).
function related_documents(array $ids): array {
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?'));
    $s = db()->prepare("SELECT id, type, title FROM documents WHERE status = 'active' AND id IN ($in) ORDER BY type, title");
    $s->execute($ids);
    return $s->fetchAll();
}

function mark_reviewed(array $user, int $id): void {
    db()->prepare('UPDATE processes SET last_reviewed_at = CURDATE() WHERE id = ?')->execute([$id]);
    audit((int) $user['id'], 'reviewed', 'process', $id, null, ['date' => date('Y-m-d')]);
}

function add_position(array $user, int $deptId, array $in): void {
    $title = text($in, 'title', 160);
    if ($title === '') throw new UserError('Position title is required.');
    $parent = ($in['reports_to_id'] ?? '') === '' ? null : (int) $in['reports_to_id'];
    db()->prepare('INSERT INTO positions (department_id, title, holder_name, reports_to_id) VALUES (?,?,?,?)')
        ->execute([$deptId, $title, text($in, 'holder_name', 160), $parent]);
    audit((int) $user['id'], 'create', 'position', (int) db()->lastInsertId(), null, ['title' => $title, 'department_id' => $deptId]);
}

function remove_position(array $user, int $deptId, int $id): void {
    $s = db()->prepare('SELECT * FROM positions WHERE id = ? AND department_id = ?');
    $s->execute([$id, $deptId]);
    if (!($p = $s->fetch())) throw new UserError('Position not found.');
    db()->prepare('UPDATE positions SET reports_to_id = NULL WHERE reports_to_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM positions WHERE id = ?')->execute([$id]);
    audit((int) $user['id'], 'delete', 'position', $id, $p, null);
}

function add_change_request(array $user, int $processId, array $in): void {
    $t = text($in, 'title', 255);
    $d = text($in, 'details');
    if ($t === '' || $d === '') throw new UserError('Give the request a title and details.');
    db()->prepare('INSERT INTO change_requests (process_id, requested_by, title, details) VALUES (?,?,?,?)')
        ->execute([$processId, $user['id'], $t, $d]);
    audit((int) $user['id'], 'create', 'change_request', (int) db()->lastInsertId(), null, ['process_id' => $processId, 'title' => $t]);
}

function resolve_change_request(array $user, int $id, string $status): void {
    if (!in_array($status, ['approved', 'rejected', 'done'], true)) throw new UserError('Invalid status.');
    db()->prepare('UPDATE change_requests SET status = ?, reviewed_by = ?, resolved_at = NOW() WHERE id = ?')->execute([$status, $user['id'], $id]);
    audit((int) $user['id'], "request_$status", 'change_request', $id);
}
