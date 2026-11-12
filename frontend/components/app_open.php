<?php /** @var array $user  @var string $title  @var string $page */
require __DIR__ . '/head.php';
$msg = flash();

// Sidebar: groups of [page, label, icon]. Icons are small line drawings (lucide style) kept inline so there is nothing to download.
$icons = [
    'home' => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/>',
    'book' => '<path d="M4 4h12a4 4 0 0 1 4 4v12H8a4 4 0 0 1-4-4z"/><path d="M8 4v16"/>',
    'check' => '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 12l3 3 5-6"/>',
    'flow' => '<rect x="3" y="3" width="7" height="6" rx="1"/><rect x="14" y="15" width="7" height="6" rx="1"/><path d="M6.5 9v3a2 2 0 0 0 2 2H14"/><path d="M10 18h4"/>',
    'file' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h6"/>',
    'tag' => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1"/>',
    'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1 .9-1 1.7"/><path d="M12 17h.01"/>',
    'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/>',
    'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    'plus' => '<path d="M12 5v14M5 12h14"/>',
];
function icon(string $name): string { global $icons; return '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($icons[$name] ?? '') . '</svg>'; }

$here = ['process' => 'process-library', 'process-edit' => 'process-library', 'document' => 'policies'][$page ?? ''] ?? ($page ?? '');
$nav = [['Start', array_filter([
        ['dashboard', 'Home', 'home'],
        $user['department_id'] ? ['handbook', 'Start here (new hires)', 'book'] : null,
        $user['department_id'] ? ['onboarding', 'My checklist', 'check'] : null])],
    ['Library', [['overview', 'Company overview', 'grid'], ['process-library', 'Processes', 'flow'], ['policies', 'Policies & forms', 'file'], ['glossary', 'Glossary', 'tag'], ['help', 'How to use this site', 'help']]]];
if ($user['role'] === 'admin') {
    $waitingCount = (int) db()->query("SELECT (SELECT COUNT(*) FROM document_versions WHERE approval = 'pending') + (SELECT COUNT(*) FROM process_versions WHERE approval = 'pending')")->fetchColumn();
    $nav[] = ['Admin', [['approvals', 'Approvals' . ($waitingCount ? " ($waitingCount)" : ''), 'check'], ['users', 'People & roles', 'users'], ['audit', 'Activity history', 'clock']]];
}
?>
<a class="skip" href="#main">Skip to content</a>
<div class="shell">
<aside class="sidebar" id="sidebar" aria-label="Main">
  <a class="side-brand" href="<?= base_url('dashboard') ?>"><span class="side-logo" aria-hidden="true">S</span>Strata Staff</a>
  <form class="side-search" role="search" method="get" action="<?= base_url('search') ?>">
    <input class="form-input" type="search" name="q" placeholder="Search everything" aria-label="Search policies, processes and glossary" value="<?= ($page ?? '') === 'search' ? h($_GET['q'] ?? '') : '' ?>">
  </form>
  <nav>
  <?php foreach ($nav as [$group, $links]): ?>
    <div class="side-group"><div class="side-label"><?= h($group) ?></div>
    <?php foreach ($links as [$p, $label, $ico]): ?>
      <a class="side-link" href="<?= base_url($p) ?>"<?= $here === $p ? ' aria-current="page"' : '' ?>><?= icon($ico) ?><?= h($label) ?></a>
    <?php endforeach ?></div>
  <?php endforeach ?>
  </nav>
  <form class="side-foot" method="post" action="<?= base_url('logout') ?>">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="who"><?= h($user['first_name'] . ' ' . $user['last_name']) ?></div>
    <div class="role"><?= h(ucfirst(str_replace('_', ' ', $user['role']))) ?></div>
    <button type="submit" class="linkbtn">Sign out</button>
  </form>
</aside>
<div class="content">
<div class="mobilebar"><button class="btn ghost sm" type="button" id="navbtn" aria-controls="sidebar" aria-expanded="false">Menu</button><strong><?= h($title) ?></strong></div>
<main class="page" id="main" tabindex="-1">
<?php if ($msg): ?><div class="alert ok"><?= h($msg) ?></div><?php endif ?>
