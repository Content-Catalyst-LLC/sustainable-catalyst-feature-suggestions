<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-public-support-integrations.php');
$checks = array(
 'contract registry' => strpos($class, 'public function integration_contract(') !== false,
 'public support contract' => strpos($class, "'public-support'") !== false,
 'embed contract' => strpos($class, "'support-embed'") !== false,
 'institution contract' => strpos($class, "'institutional-support'") !== false,
 'health record' => strpos($class, 'public function health_record()') !== false,
 'admin page' => strpos($class, "const ADMIN_PAGE = 'scfs-public-support-integrations';") !== false,
 'JSON export' => strpos($class, 'public function export_contract_action()') !== false,
 'WP-CLI contract' => strpos($class, 'scfs public-support contract') !== false,
 'WP-CLI version' => strpos($class, 'scfs public-support verify-version') !== false,
 'WP-CLI embed' => strpos($class, 'scfs public-support embed') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.9.0 institutional integration contract passed (' . count($checks) . " checks).\n";
