<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-agent-workspace.php');
$checks = array(
 'private schema route' => strpos($class, "/help-desk/workspace/schema") !== false,
 'queue route' => strpos($class, "/help-desk/workspace/queues") !== false,
 'case list route' => strpos($class, "/help-desk/workspace/cases") !== false,
 'workload route' => strpos($class, "/help-desk/workspace/workload") !== false,
 'assignment route' => strpos($class, "/help-desk/workspace/assign") !== false,
 'bulk route' => strpos($class, "/help-desk/workspace/bulk") !== false,
 'saved views route' => strpos($class, "/help-desk/workspace/views") !== false,
 'view permission' => strpos($class, "'permission_callback' => array(\$this, 'can_view')") !== false,
 'assignment permission' => strpos($class, "'permission_callback' => array(\$this, 'can_assign')") !== false,
 'queue CLI' => strpos($class, 'scfs help-desk workspace queues') !== false,
 'workload CLI' => strpos($class, 'scfs help-desk workspace workload') !== false,
 'assignment CLI' => strpos($class, 'scfs help-desk workspace assign') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.2.0 private admin API and CLI contract passed (' . count($checks) . " checks).\n";
