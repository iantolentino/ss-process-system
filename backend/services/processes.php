<?php
// Process Library: processes per department. Each save creates a new immutable version holding the
// whole process (details + ordered steps) as JSON, so history, handoff views and the flowchart share one source.
require_once __DIR__ . '/../bootstrap.php';

const PROC_STATUS = ['draft', 'active', 'archived'];
const MAX_STEPS = 60;
const CADENCE = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly', 'as_needed' => 'As needed'];

function can_edit_process(array $user, int $deptId): bool {
    return $user['role'] === 'admin' || ($user['role'] === 'process_owner' && (int) $user['department_id'] === $deptId);
}

// Only an admin publishes directly. A process owner's save that staff would see waits for an admin.
function needs_approval(array $user, string $status): bool {
    return $user['role'] !== 'admin' && $status === 'active';
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
            'time' => text($s, 'time', 80),
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
        // The detailed template fields executives read first.
        'inputs' => text($in, 'inputs'),
        'kpi' => text($in, 'kpi'),
        'exceptions' => text($in, 'exceptions'),
        'escalation' => text($in, 'escalation'),
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
    $cadence = ($in['cadence'] ?? '') === '' ? 'as_needed' : (string) $in['cadence'];
    if (!isset(CADENCE[$cadence])) throw new UserError('Pick how often it happens.');
    $kind = ($in['kind'] ?? 'process') === 'template' ? 'template' : 'process';
    $snap = build_snapshot($in);
    // The name, purpose etc. travel with the version, so a waiting version can be applied later exactly as it was sent.
    $meta = ['name' => $name, 'purpose' => $purpose, 'owner_position' => text($in, 'owner_position', 160), 'status' => $status, 'cadence' => $cadence, 'kind' => $kind];
    $snap['meta'] = $meta;
    $pending = needs_approval($user, $status);
    $isNew = $id === null;

    $pdo = db();
    $before = null;
    if ($id !== null) {
        $s = $pdo->prepare('SELECT p.name, p.purpose, p.owner_position, p.status, p.cadence, p.kind, v.version_no, v.snapshot
                            FROM processes p LEFT JOIN process_versions v ON v.id = p.current_version_id WHERE p.id = ?');
        $s->execute([$id]);
        if (!($before = $s->fetch())) throw new UserError('Process not found.');
        $before['snapshot'] = json_decode($before['snapshot'] ?? 'null', true);
        if ($changelog === '') throw new UserError('Describe what changed in this version.');
        $w = $pdo->prepare("SELECT id FROM process_versions WHERE process_id = ? AND approval = 'pending'");
        $w->execute([$id]);
        if ($w->fetch()) throw new UserError('A change to this process is already waiting for approval. Ask an admin to approve or reject it first.');
    } elseif ($changelog === '') {
        $changelog = 'Initial version';
    }

    $pdo->beginTransaction();
    try {
        if ($id === null) {
            $pdo->prepare('INSERT INTO processes (department_id, name, purpose, owner_position, status, cadence, kind) VALUES (?,?,?,?,?,?,?)')
                ->execute([$deptId, $name, $purpose, $meta['owner_position'], $pending ? 'draft' : $status, $cadence, $kind]);   // waiting for approval = still a draft
            $id = (int) $pdo->lastInsertId();
            $no = 1;
        } else {
            if (!$pending) apply_process_meta($id, $meta);   // a waiting change leaves the live process exactly as staff see it now
            $no = (int) $pdo->query("SELECT COALESCE(MAX(version_no),0)+1 FROM process_versions WHERE process_id = $id")->fetchColumn();
        }
        $pdo->prepare('INSERT INTO process_versions (process_id, version_no, snapshot, changelog, created_by, approval) VALUES (?,?,?,?,?,?)')
            ->execute([$id, $no, json_encode($snap, JSON_UNESCAPED_UNICODE), $changelog, $user['id'], $pending ? 'pending' : 'approved']);
        if (!$pending || $isNew) $pdo->prepare('UPDATE processes SET current_version_id = ? WHERE id = ?')->execute([$pdo->lastInsertId(), $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    audit((int) $user['id'], $before ? 'new_version' : 'create', 'process', $id, $before,
        ['name' => $name, 'status' => $status, 'cadence' => $cadence, 'kind' => $kind, 'version_no' => $no, 'changelog' => $changelog, 'approval' => $pending ? 'pending' : 'approved', 'snapshot' => $snap]);
    if ($pending) {
        notify_admins("Waiting for your approval: $name", "{$user['first_name']} {$user['last_name']} saved version $no of the process \"$name\".\nWhat changed: $changelog\n\n" . app_url('approvals'));
    }
    return $id;
}

function apply_process_meta(int $id, array $m): void {
    db()->prepare('UPDATE processes SET name=?, purpose=?, owner_position=?, status=?, cadence=?, kind=? WHERE id=?')
        ->execute([$m['name'], $m['purpose'], $m['owner_position'], $m['status'], $m['cadence'], $m['kind'], $id]);
}

// Admin decision on a waiting version. Approving applies it: it becomes the version staff see.
function decide_process_version(array $user, int $vid, bool $approve, string $note): void {
    $pdo = db();
    $s = $pdo->prepare("SELECT v.process_id, v.version_no, v.snapshot, p.name, u.email FROM process_versions v JOIN processes p ON p.id = v.process_id JOIN users u ON u.id = v.created_by
                        WHERE v.id = ? AND v.approval = 'pending'");
    $s->execute([$vid]);
    if (!($v = $s->fetch())) throw new UserError('That item is no longer waiting for approval.');
    $note = mb_substr(trim($note), 0, 500);
    if (!$approve && $note === '') throw new UserError('Say why it is rejected, so the person can fix it.');

    $pdo->beginTransaction();
    $pdo->prepare('UPDATE process_versions SET approval = ?, decided_by = ?, decided_at = NOW(), decision_note = ? WHERE id = ?')
        ->execute([$approve ? 'approved' : 'rejected', $user['id'], $note, $vid]);
    if ($approve) {
        apply_process_meta((int) $v['process_id'], json_decode($v['snapshot'], true)['meta']);
        $pdo->prepare('UPDATE processes SET current_version_id = ? WHERE id = ?')->execute([$vid, $v['process_id']]);
    }
    $pdo->commit();
    $word = $approve ? 'approved' : 'rejected';
    audit((int) $user['id'], $word, 'process', (int) $v['process_id'], null, ['version_no' => (int) $v['version_no'], 'note' => $note]);
    notify($v['email'], "Your change was $word: {$v['name']}", "Version {$v['version_no']} of \"{$v['name']}\" was $word." . ($note !== '' ? "\nNote: $note" : '') . "\n\n" . app_url('process?id=' . $v['process_id']));
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

// "Remove" = archive (hidden from staff, kept with all versions, can be restored). Nothing is ever hard-deleted.
function set_process_status(array $user, int $id, string $status): void {
    if (!in_array($status, ['active', 'archived'], true)) throw new UserError('Invalid status.');
    $s = db()->prepare('SELECT status FROM processes WHERE id = ?');
    $s->execute([$id]);
    $old = $s->fetchColumn();
    if ($old === false) throw new UserError('Process not found.');
    if (needs_approval($user, $status)) $status = 'draft';   // a process owner restores to a draft; saving it as active sends it for approval
    db()->prepare('UPDATE processes SET status = ? WHERE id = ?')->execute([$status, $id]);
    audit((int) $user['id'], $status === 'archived' ? 'archive' : 'restore', 'process', $id, ['status' => $old], ['status' => $status]);
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
    $used = db()->prepare('SELECT COUNT(*) FROM onboarding_items WHERE position_id = ?');
    $used->execute([$id]);
    if ($used->fetchColumn()) throw new UserError('Onboarding checklist items still use this position. Delete or change them first.');
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

    // Tell the department's process owners (admins see requests on the page anyway).
    $p = db()->prepare('SELECT name, department_id FROM processes WHERE id = ?');
    $p->execute([$processId]);
    $p = $p->fetch();
    $o = db()->prepare("SELECT email FROM users WHERE role = 'process_owner' AND department_id = ? AND active = 1 AND id <> ?");
    $o->execute([$p['department_id'], $user['id']]);
    foreach ($o->fetchAll(PDO::FETCH_COLUMN) as $email) {
        notify($email, "Change request: {$p['name']}", "{$user['first_name']} {$user['last_name']} suggests a change to \"{$p['name']}\":\n\n$t\n$d\n\n" . app_url("process?id=$processId"));
    }
}

function resolve_change_request(array $user, int $id, string $status): void {
    if (!in_array($status, ['approved', 'rejected', 'done'], true)) throw new UserError('Invalid status.');
    db()->prepare('UPDATE change_requests SET status = ?, reviewed_by = ?, resolved_at = NOW() WHERE id = ?')->execute([$status, $user['id'], $id]);
    audit((int) $user['id'], "request_$status", 'change_request', $id);

    $r = db()->prepare('SELECT c.title, c.process_id, u.email, p.name FROM change_requests c JOIN users u ON u.id = c.requested_by JOIN processes p ON p.id = c.process_id WHERE c.id = ? AND u.active = 1');
    $r->execute([$id]);
    if ($r = $r->fetch()) notify($r['email'], "Your change request was $status", "Your request \"{$r['title']}\" for \"{$r['name']}\" is now: $status.\n\n" . app_url("process?id={$r['process_id']}"));
}

// Plain-language list of what differs between two process snapshots, e.g. "+ Added step: Send offer".
// shortcut: steps are matched by title, so two steps with the same title are compared as one.
function diff_snapshots(array $old, array $new): array {
    $out = [];
    foreach (['trigger' => 'Starts when', 'frequency' => 'How often', 'duration' => 'How long', 'notes' => 'Tips for new hires', 'inputs' => 'What is needed to start', 'kpi' => 'How success is measured', 'exceptions' => 'What can go wrong', 'escalation' => 'Who to ask when stuck'] as $k => $label) {
        if (($old[$k] ?? '') !== ($new[$k] ?? '')) $out[] = "~ $label changed";
    }
    $a = $old['docs'] ?? []; $b = $new['docs'] ?? [];
    sort($a); sort($b);
    if ($a !== $b) $out[] = '~ Related documents changed';

    $o = array_column($old['steps'], null, 'title');
    $n = array_column($new['steps'], null, 'title');
    foreach ($n as $t => $s) if (!isset($o[$t])) $out[] = "+ Added step: $t";
    foreach ($o as $t => $s) if (!isset($n[$t])) $out[] = "- Removed step: $t";
    $labels = ['detail' => 'what they do', 'role' => 'who', 'person' => 'person', 'tools' => 'tools', 'output' => 'output', 'time' => 'time', 'decision' => 'decision', 'no_goto' => '"No" target'];
    foreach ($n as $t => $s) {
        if (!isset($o[$t])) continue;
        $f = array_keys(array_filter($labels, fn($k) => ($o[$t][$k] ?? null) !== ($s[$k] ?? null), ARRAY_FILTER_USE_KEY));
        if ($f) $out[] = "~ Step \"$t\": " . implode(', ', array_map(fn($k) => $labels[$k], $f)) . ' changed';
    }
    if (array_keys(array_intersect_key($o, $n)) !== array_keys(array_intersect_key($n, $o))) $out[] = '~ Step order changed';
    return $out;
}
