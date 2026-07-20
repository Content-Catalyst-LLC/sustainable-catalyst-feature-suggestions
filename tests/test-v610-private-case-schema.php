<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-case-foundation.php');
$tables = array('scfs_cases','scfs_case_participants','scfs_case_messages','scfs_case_events','scfs_case_assignments','scfs_case_relationships','scfs_case_attachments','scfs_case_sla_events');
$checks = array(
 'dbDelta schema' => strpos($class, 'dbDelta($statement)') !== false,
 'case number unique' => strpos($class, 'UNIQUE KEY case_number') !== false,
 'case number generator' => strpos($class, "sprintf('%s-%04d-%06d'") !== false,
 'schema version option' => strpos($class, "const DB_VERSION_OPTION = 'scfs_help_desk_db_version';") !== false,
 'additive schema' => strpos($class, "'additive_schema_only' => true") !== false,
);
foreach ($tables as $table) $checks[$table] = strpos($class, "'{$table}'") !== false || strpos($class, $table) !== false;
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.1.0 private case schema contract passed (' . count($checks) . " checks).\n";
