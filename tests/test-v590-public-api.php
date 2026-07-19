<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-public-support-integrations.php');
$main = file_get_contents($root . '/backend/app/main.py');
$checks = array(
 'schema route' => strpos($class, '/public-support/schema') !== false,
 'products route' => strpos($class, '/public-support/products') !== false,
 'product route' => strpos($class, '/public-support/product/') !== false,
 'search route' => strpos($class, '/public-support/search') !== false,
 'version route' => strpos($class, '/public-support/version/verify') !== false,
 'embed route' => strpos($class, '/public-support/embed') !== false,
 'institution contracts route' => strpos($class, '/institutional-support/contracts') !== false,
 'institution health route' => strpos($class, '/institutional-support/health') !== false,
 'FastAPI capabilities' => strpos($main, '/v1/public-support/capabilities') !== false,
 'FastAPI version verify' => strpos($main, '/v1/public-support/version/verify') !== false,
 'FastAPI embed plan' => strpos($main, '/v1/public-support/embed/plan') !== false,
 'FastAPI contract validation' => strpos($main, '/v1/institutional-support/contracts/validate') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.9.0 public API contract passed (' . count($checks) . " checks).\n";
