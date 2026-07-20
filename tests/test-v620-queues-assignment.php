<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-agent-workspace.php');
$checks = array(
 'my open queue' => strpos($class, "'my_open'") !== false,
 'unassigned queue' => strpos($class, "'unassigned'") !== false,
 'waiting support queue' => strpos($class, "'waiting_support'") !== false,
 'escalated queue' => strpos($class, "'escalated'") !== false,
 'high priority queue' => strpos($class, "'high_priority'") !== false,
 'dynamic team queues' => strpos($class, "strpos(\$filters['queue'], 'team_')") !== false,
 'weighted workload' => strpos($class, "WHEN 'p1_critical' THEN 8") !== false,
 'assignment history' => strpos($class, 'assign_case(') !== false,
 'bulk plan' => strpos($class, 'public function bulk_plan(') !== false,
 'bulk execution' => strpos($class, 'public function execute_bulk(') !== false,
 'automatic assignment disabled' => strpos($class, "'automatic_assignment' => false") !== false,
 'human confirmation required' => strpos($class, "'human_confirmation_required' => true") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.3.0 queues and assignment contract passed (' . count($checks) . " checks).\n";
