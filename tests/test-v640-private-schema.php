<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-service-levels.php');
$tables = array('scfs_help_desk_sla_policies','scfs_help_desk_support_calendars','scfs_help_desk_sla_clocks','scfs_help_desk_escalation_rules','scfs_help_desk_escalation_events');
$checks = array('dbDelta used' => strpos($class, 'dbDelta($statement)') !== false, 'foundation SLA events used' => strpos($class, 'add_sla_event') !== false, 'append-only case events used' => strpos($class, 'add_event') !== false);
foreach ($tables as $table) $checks[$table] = strpos($class, $table) !== false;
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.4.0 private service-level schema contract passed (' . count($checks) . " checks).\n";
