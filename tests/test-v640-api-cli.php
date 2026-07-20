<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-service-levels.php');
$checks = array(
 'schema route' => strpos($class, "/help-desk/service-levels/schema") !== false,
 'policy route' => strpos($class, "/help-desk/service-levels/policies") !== false,
 'calendar route' => strpos($class, "/help-desk/service-levels/calendars") !== false,
 'case route' => strpos($class, "/help-desk/service-levels/case/(?P<id>") !== false,
 'health route' => strpos($class, "/help-desk/service-levels/health") !== false,
 'status cli' => strpos($class, "scfs help-desk service-levels status") !== false,
 'case cli' => strpos($class, "scfs help-desk service-levels case") !== false,
 'tick cli' => strpos($class, "scfs help-desk service-levels tick") !== false,
 'private permissions' => strpos($class, 'scfs_view_service_levels') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.4.0 Service Levels API and CLI contract passed (' . count($checks) . " checks).\n";
