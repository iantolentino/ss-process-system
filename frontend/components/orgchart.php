<?php
// Reporting lines drawn as a top-down hierarchy chart (nested lists + CSS connectors). Needs $positions rows with id, title, holder_name, reports_to_id.
// shortcut: positions caught in a reporting loop (A reports to B, B to A) have no root and are not drawn.
if (!function_exists('org_tree')) {
    function org_tree(array $rows, ?int $parent, int $depth = 0): void {
        $kids = array_filter($rows, fn($p) => ($p['reports_to_id'] === null ? null : (int) $p['reports_to_id']) === $parent);
        if (!$kids || $depth > 12) return;
        echo '<ul class="org">';
        foreach ($kids as $p) {
            echo '<li><span class="org-node"><strong>' . h($p['title']) . '</strong>' . ($p['holder_name'] ? '<br><span class="muted">' . h($p['holder_name']) . '</span>' : '') . '</span>';
            org_tree($rows, (int) $p['id'], $depth + 1);
            echo '</li>';
        }
        echo '</ul>';
    }
}
echo '<div class="org-wrap" role="group" aria-label="Reporting lines, top to bottom">';
org_tree($positions, null);
echo '</div>';
