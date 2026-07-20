<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-secure-evidence.php');
$checks = array(
 'redaction operation' => strpos($class, 'function update_redaction') !== false,
 'redaction reason required' => strpos($class, 'scfs_redaction_reason_required') !== false,
 'retention operation' => strpos($class, 'function schedule_retention_action') !== false,
 'retention reason required' => strpos($class, 'scfs_retention_reason_required') !== false,
 'legal hold action' => strpos($class, "'legal_hold'") !== false,
 'automatic deletion disabled' => strpos($class, "'automatic_deletion' => '0'") !== false,
 'retention scan review only' => strpos($class, "array('state' => 'review_required')") !== false,
 'authority hook' => strpos($class, 'scfs_help_desk_evidence_retention_review_due') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.5.0 retention and redaction contract passed.\n';
