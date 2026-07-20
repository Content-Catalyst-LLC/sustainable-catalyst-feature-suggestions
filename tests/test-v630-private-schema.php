<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-customer-portal.php');
$tables = array('scfs_help_desk_portal_tokens','scfs_help_desk_portal_sessions','scfs_help_desk_portal_events','scfs_help_desk_portal_satisfaction','scfs_help_desk_portal_notifications');
$checks = array(
 'additive DB version' => strpos($class, "const DB_VERSION = '1.2.0';") !== false,
 'tokens dbDelta' => strpos($class, 'dbDelta($tokens);') !== false,
 'sessions dbDelta' => strpos($class, 'dbDelta($sessions);') !== false,
 'events dbDelta' => strpos($class, 'dbDelta($events);') !== false,
 'satisfaction dbDelta' => strpos($class, 'dbDelta($satisfaction);') !== false,
 'notifications dbDelta' => strpos($class, 'dbDelta($notifications);') !== false,
 'token hash unique' => strpos($class, 'UNIQUE KEY token_hash') !== false,
 'session hash unique' => strpos($class, 'UNIQUE KEY session_hash') !== false,
);
foreach ($tables as $table) $checks[$table] = strpos($class, $table) !== false;
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.3.0 private portal schema contract passed (' . count($checks) . " checks).\n";
