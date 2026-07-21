<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-secure-evidence.php');
$routes = array('/help-desk/evidence/schema','/help-desk/evidence/case/(?P<id>\\d+)','/help-desk/evidence/intakes','/help-desk/evidence/attachments','/help-desk/evidence/attachments/(?P<id>\\d+)/access','/help-desk/evidence/access/resolve','/help-desk/evidence/attachments/(?P<id>\\d+)/redaction','/help-desk/evidence/attachments/(?P<id>\\d+)/retention','/help-desk/evidence/diagnostics','/help-desk/evidence/health');
$checks = array(); foreach ($routes as $route) $checks[$route] = strpos($class, $route) !== false;
foreach (array('status','case','request','retention-scan') as $command) $checks['cli ' . $command] = strpos($class, "scfs help-desk evidence {$command}") !== false;
$checks['private permissions'] = substr_count($class, "'permission_callback'") >= count($routes);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.11.0 REST and CLI contract passed.\n';
