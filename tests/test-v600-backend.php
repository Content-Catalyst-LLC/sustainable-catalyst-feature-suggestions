<?php
$root = dirname(__DIR__);
$module = file_get_contents($root . '/backend/app/connected_product_support_platform.py');
$main = file_get_contents($root . '/backend/app/main.py');
$tests = file_get_contents($root . '/backend/tests/test_connected_product_support_platform.py');
$checks = array(
 'platform evaluator' => strpos($module, 'def evaluate_connected_platform(') !== false,
 'journey planner' => strpos($module, 'def plan_connected_journey(') !== false,
 'report integrity' => strpos($module, 'def verify_connected_platform_report(') !== false,
 'connected state' => strpos($module, '"connected"') !== false,
 'capability endpoint' => strpos($main, '/v1/connected-platform/capabilities') !== false,
 'evaluate endpoint' => strpos($main, '/v1/connected-platform/evaluate') !== false,
 'journey endpoint' => strpos($main, '/v1/connected-platform/journey/plan') !== false,
 'report endpoint' => strpos($main, '/v1/connected-platform/reports/verify') !== false,
 'backend tests' => strpos($tests, 'test_connected_assessment_reaches_connected_state') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.1.0 backend contract passed (' . count($checks) . " checks).\n";
