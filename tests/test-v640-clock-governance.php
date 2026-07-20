<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-service-levels.php');
$checks = array(
 'clock initialization' => strpos($class, 'initialize_case_clocks') !== false,
 'clock evaluation' => strpos($class, 'evaluate_case') !== false,
 'pause operation' => strpos($class, 'pause_case') !== false,
 'resume accounting' => strpos($class, 'paused_seconds') !== false && strpos($class, 'resume_case') !== false,
 'target completion' => strpos($class, 'complete_target') !== false,
 'clock states' => strpos($class, "array('running', 'warning', 'paused', 'breached', 'completed', 'cancelled')") !== false,
 'hourly evaluation' => strpos($class, "const CRON_HOOK = 'scfs_help_desk_service_level_tick';") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.4.0 response-clock governance contract passed (' . count($checks) . " checks).\n";
