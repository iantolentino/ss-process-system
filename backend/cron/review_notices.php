<?php
// Usage (cron, weekly, e.g. Monday 08:00): php /home/USER/app/backend/cron/review_notices.php
// Emails admins and policy owners one list of documents overdue or due for review within 14 days.
if (PHP_SAPI !== 'cli') exit('CLI only');
require __DIR__ . '/../bootstrap.php';

$docs = db()->query("SELECT id, type, title, review_due, review_due < CURDATE() AS overdue FROM documents
                     WHERE status = 'active' AND review_due IS NOT NULL AND review_due <= CURDATE() + INTERVAL 14 DAY
                     ORDER BY review_due")->fetchAll();
if (!$docs) { echo "nothing due\n"; exit; }

$lines = array_map(fn($d) => ($d['overdue'] ? 'OVERDUE ' : 'due ') . $d['review_due'] . '  ' . ucfirst($d['type']) . ': ' . $d['title'] . '  ' . app_url('document?id=' . $d['id']), $docs);
$to = db()->query("SELECT email FROM users WHERE role IN ('admin','policy_owner') AND active = 1")->fetchAll(PDO::FETCH_COLUMN);
foreach ($to as $email) notify($email, count($docs) . ' document(s) due for review', "These need a review:\n\n" . implode("\n", $lines) . "\n");
log_line('app', 'INFO', 'review notices sent', ['documents' => count($docs), 'recipients' => count($to)]);
echo count($docs) . " document(s), " . count($to) . " recipient(s)\n";
