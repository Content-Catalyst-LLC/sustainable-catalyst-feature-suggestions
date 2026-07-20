<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-help-desk-customer-portal.php');
$checks = array(
 'schema route' => strpos($class, '/help-desk/portal/schema') !== false,
 'case route' => strpos($class, '/help-desk/portal/case') !== false,
 'conversation route' => strpos($class, '/help-desk/portal/conversation') !== false,
 'reply route' => strpos($class, '/help-desk/portal/reply') !== false,
 'transition route' => strpos($class, '/help-desk/portal/transition') !== false,
 'satisfaction route' => strpos($class, '/help-desk/portal/satisfaction') !== false,
 'logout route' => strpos($class, '/help-desk/portal/logout') !== false,
 'admin issue route' => strpos($class, '/help-desk/portal/admin/issue-link') !== false,
 'admin health route' => strpos($class, '/help-desk/portal/admin/health') !== false,
 'session header' => strpos($class, 'X-SCFS-Portal-Session') !== false,
 'issue link CLI' => strpos($class, 'scfs help-desk portal issue-link') !== false,
 'revoke CLI' => strpos($class, 'scfs help-desk portal revoke-case') !== false,
 'health CLI' => strpos($class, 'scfs help-desk portal health') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.3.0 portal API and CLI contract passed (' . count($checks) . " checks).\n";
