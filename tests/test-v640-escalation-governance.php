<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-service-levels.php');
$manifest = json_decode(file_get_contents($root . '/feature_suggestions_manifest.json'), true);
$checks = array(
 'escalation records' => strpos($class, 'create_escalation') !== false && strpos($class, 'acknowledge_escalation') !== false,
 'human review' => strpos($class, "'human_review_required' => true") !== false,
 'no automatic priority' => strpos($class, "'automatic_priority_change' => false") !== false,
 'no automatic assignment' => strpos($class, "'automatic_assignment' => false") !== false,
 'no automatic customer notification' => strpos($class, "'automatic_customer_notification' => false") !== false,
 'no automatic closure' => strpos($class, "'automatic_case_closure' => false") !== false,
 'no automatic contract' => strpos($class, "'contractual_commitment_created_automatically' => false") !== false,
 'manifest governance' => ($manifest['help_desk_service_levels']['human_review_required'] ?? false) === true,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.4.0 escalation governance contract passed (' . count($checks) . " checks).\n";
