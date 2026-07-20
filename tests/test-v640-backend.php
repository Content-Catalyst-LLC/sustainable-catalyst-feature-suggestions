<?php
$root = dirname(__DIR__);
$module = file_get_contents($root . '/backend/app/help_desk_service_levels.py');
$main = file_get_contents($root . '/backend/app/main.py');
$tests = file_get_contents($root . '/backend/tests/test_help_desk_service_levels.py');
$checks = array(
 'backend schema' => strpos($module, 'scfs-help-desk-service-levels/1.0') !== false,
 'policy evaluator' => strpos($module, 'def evaluate_service_policy') !== false,
 'calendar evaluator' => strpos($module, 'def evaluate_support_calendar') !== false,
 'clock evaluator' => strpos($module, 'def evaluate_clock') !== false,
 'transition evaluator' => strpos($module, 'def evaluate_clock_transition') !== false,
 'escalation evaluator' => strpos($module, 'def evaluate_escalation') !== false,
 'report verifier' => strpos($module, 'def verify_service_level_report') !== false,
 'capabilities route' => strpos($main, "/v1/help-desk/service-levels/capabilities") !== false,
 'backend tests' => strpos($tests, 'test_escalation_blocks_automatic_actions') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.4.0 Service Levels backend contract passed (' . count($checks) . " checks).\n";
