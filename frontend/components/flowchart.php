<?php /** @var array $steps  steps from a process snapshot */
// Plain HTML/CSS flowchart (no JS, prints cleanly). shortcut: "No" branches are shown as "go to step N" labels, not drawn
// as back-arrows; switch to Mermaid/SVG if staff need true branching diagrams.
?>
<ol class="flow">
  <li class="flow-end">Start</li>
  <?php foreach ($steps as $i => $s): $n = $i + 1; ?>
  <li class="flow-arrow" aria-hidden="true">↓</li>
  <li class="flow-node <?= $s['decision'] ? 'decision' : '' ?>">
    <span class="flow-num"><?= $n ?></span>
    <div>
      <strong><?= h($s['title']) ?></strong><?php if ($s['decision']): ?><span class="sr"> (yes/no decision)</span><?php endif ?>
      <?php if ($s['role'] || $s['person']): ?>
        <div class="flow-who"><?= h(trim($s['role'] . ($s['person'] ? ' — ' . $s['person'] : ''))) ?></div>
      <?php endif ?>
      <?php if ($s['decision']): ?>
        <div class="flow-branch"><span>Yes ↓ next step</span>
          <span>No → <?= $s['no_goto'] ? 'go to step ' . (int) $s['no_goto'] : 'process ends' ?></span></div>
      <?php endif ?>
    </div>
  </li>
  <?php endforeach ?>
  <li class="flow-arrow" aria-hidden="true">↓</li>
  <li class="flow-end">Done</li>
</ol>
