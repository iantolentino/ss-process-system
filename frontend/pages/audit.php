<?php
// Admin-only history of every change: who, what, when, with before/after. CSV export is itself audited.
$user = require_role('admin');

$f = ['user' => (int) ($_GET['user'] ?? 0), 'entity' => trim($_GET['entity'] ?? ''), 'action' => trim($_GET['action'] ?? ''),
      'from' => trim($_GET['from'] ?? ''), 'to' => trim($_GET['to'] ?? ''), 'id' => (int) ($_GET['id'] ?? 0)];
$where = ['1=1'];
$args = [];
if ($f['user']) { $where[] = 'a.user_id = ?'; $args[] = $f['user']; }
if ($f['entity'] !== '') { $where[] = 'a.entity = ?'; $args[] = $f['entity']; }
if ($f['action'] !== '') { $where[] = 'a.action = ?'; $args[] = $f['action']; }
if ($f['id']) { $where[] = 'a.entity_id = ?'; $args[] = $f['id']; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from'])) { $where[] = 'a.at >= ?'; $args[] = $f['from'] . ' 00:00:00'; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to'])) { $where[] = 'a.at <= ?'; $args[] = $f['to'] . ' 23:59:59'; }
$cond = implode(' AND ', $where);
$from = "FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE $cond";
$cols = 'a.id, a.at, a.action, a.entity, a.entity_id, a.ip, a.before_json, a.after_json, CONCAT(u.first_name, " ", u.last_name) AS who';

// Spreadsheet apps run cells starting with = + - @ as formulas; neutralise them.
function csv_cell($v): string {
    $v = (string) $v;
    return $v !== '' && strpos('=+-@', $v[0]) !== false ? "'" . $v : $v;
}

if (($_GET['export'] ?? '') === 'csv') {
    audit((int) $user['id'], 'export', 'audit_log', null, null, array_filter($f));
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id', 'when', 'who', 'action', 'entity', 'entity_id', 'ip', 'before', 'after']);
    $st = db()->prepare("SELECT $cols $from ORDER BY a.id DESC LIMIT 50000");
    $st->execute($args);
    while ($r = $st->fetch()) {
        fputcsv($out, array_map('csv_cell', [$r['id'], $r['at'], $r['who'] ?? 'system', $r['action'], $r['entity'], $r['entity_id'], $r['ip'], $r['before_json'], $r['after_json']]));
    }
    exit;
}

$per = 50;
$page = max(1, (int) ($_GET['p'] ?? 1));
$cnt = db()->prepare("SELECT COUNT(*) $from");
$cnt->execute($args);
$total = (int) $cnt->fetchColumn();
$st = db()->prepare("SELECT $cols $from ORDER BY a.id DESC LIMIT $per OFFSET " . ($page - 1) * $per);
$st->execute($args);
$rows = $st->fetchAll();

$users = db()->query("SELECT id, CONCAT(first_name, ' ', last_name) AS n FROM users ORDER BY first_name")->fetchAll();
$entities = db()->query('SELECT DISTINCT entity FROM audit_log ORDER BY entity')->fetchAll(PDO::FETCH_COLUMN);
$actions = db()->query('SELECT DISTINCT action FROM audit_log ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);

// "field: old → new" for top-level keys that differ (nested values are compared as JSON).
function changes(?string $b, ?string $a): array {
    $b = json_decode($b ?? 'null', true) ?: [];
    $a = json_decode($a ?? 'null', true) ?: [];
    $out = [];
    foreach (array_unique(array_merge(array_keys($b), array_keys($a))) as $k) {
        $x = $b[$k] ?? null; $y = $a[$k] ?? null;
        if ($x === $y) continue;
        $fmt = fn($v) => $v === null ? '∅' : mb_strimwidth(is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE), 0, 70, '…');
        $out[] = "$k: " . ($b ? $fmt($x) . ' → ' : '') . $fmt($y);
    }
    return $out;
}

// Link to the record where one exists.
function entity_link(string $e, ?int $id): string {
    $map = ['document' => 'document?id=', 'process' => 'process?id='];
    $label = h($e) . ($id ? "#$id" : '');
    return isset($map[$e]) && $id ? '<a href="' . h(base_url($map[$e] . $id)) . '">' . $label . '</a>' : $label;
}

$qs = fn(array $over = []) => http_build_query(array_filter(array_merge($_GET, $over), fn($v) => $v !== '' && $v !== 0 && $v !== null));
$title = 'Activity history';
require __DIR__ . '/../components/app_open.php';
?>
<div class="page-head"><div>
  <h1 class="title" data-animate>Activity history</h1>
  <p class="lead">A record of who did what and when: sign-ins, uploads, new versions, edits and downloads. Entries can never be changed or deleted. <?= number_format($total) ?> entries match your filters.</p>
</div></div>

<form class="toolbar" method="get">
  <select class="form-input" name="user" aria-label="Person"><option value="">Anyone</option>
    <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= $f['user'] == $u['id'] ? 'selected' : '' ?>><?= h($u['n']) ?></option><?php endforeach ?></select>
  <select class="form-input" name="entity" aria-label="Type of item"><option value="">Any type of item</option>
    <?php foreach ($entities as $e): ?><option <?= $f['entity'] === $e ? 'selected' : '' ?>><?= h($e) ?></option><?php endforeach ?></select>
  <select class="form-input" name="action" aria-label="What they did"><option value="">Any action</option>
    <?php foreach ($actions as $a): ?><option <?= $f['action'] === $a ? 'selected' : '' ?>><?= h($a) ?></option><?php endforeach ?></select>
  <input class="form-input" type="number" name="id" min="1" placeholder="Item number" aria-label="Item number" style="max-width:110px" value="<?= $f['id'] ?: '' ?>">
  <input class="form-input" type="date" name="from" value="<?= h($f['from']) ?>" title="From" aria-label="From date">
  <input class="form-input" type="date" name="to" value="<?= h($f['to']) ?>" title="To" aria-label="To date">
  <button class="btn inline" type="submit">Filter</button>
  <a class="btn ghost inline" href="<?= base_url('audit?' . $qs(['export' => 'csv', 'p' => ''])) ?>" title="Download these entries as a spreadsheet">Export CSV</a>
</form>

<div class="panel table-wrap" style="padding:4px 8px"><table class="tbl" data-animate>
  <tr><th>When</th><th>Who</th><th>Did what</th><th>Item</th><th>What changed</th></tr>
  <?php foreach ($rows as $r): $ch = changes($r['before_json'], $r['after_json']); ?>
  <tr>
    <td style="white-space:nowrap"><?= h($r['at']) ?></td><td><?= h($r['who'] ?? 'system') ?><div class="muted"><?= h($r['ip']) ?></div></td>
    <td><span class="badge off"><?= h(str_replace('_', ' ', $r['action'])) ?></span></td>
    <td><?= entity_link($r['entity'], $r['entity_id'] ? (int) $r['entity_id'] : null) ?></td>
    <td><?php foreach (array_slice($ch, 0, 4) as $c): ?><div class="muted"><?= h($c) ?></div><?php endforeach ?>
      <?php if (count($ch) > 4): ?><div class="muted">+<?= count($ch) - 4 ?> more</div><?php endif ?>
      <?php if ($r['before_json'] || $r['after_json']): ?><details><summary class="muted">Show details</summary>
        <pre class="json"><?= h(json_encode(['before' => json_decode($r['before_json'] ?? 'null'), 'after' => json_decode($r['after_json'] ?? 'null')], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></details><?php endif ?></td>
  </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="5" class="muted">No entries match these filters.</td></tr><?php endif ?>
</table></div>

<?php $pages = (int) ceil($total / $per); if ($pages > 1): ?>
<p class="muted" style="margin-top:1rem">Page <?= $page ?> of <?= $pages ?>
  <?php if ($page > 1): ?> · <a href="<?= base_url('audit?' . $qs(['p' => $page - 1])) ?>">← Newer</a><?php endif ?>
  <?php if ($page < $pages): ?> · <a href="<?= base_url('audit?' . $qs(['p' => $page + 1])) ?>">Older →</a><?php endif ?></p>
<?php endif ?>
<?php require __DIR__ . '/../components/app_close.php';
