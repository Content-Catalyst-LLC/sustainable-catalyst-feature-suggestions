<?php
$root = dirname(__DIR__);
$module = file_get_contents($root . '/backend/app/help_desk_case_foundation.py');
$main = file_get_contents($root . '/backend/app/main.py');
$tests = file_get_contents($root . '/backend/tests/test_help_desk_case_foundation.py');
$checks = array(
 'intake evaluator' => strpos($module, 'def assess_case_intake(') !== false,
 'case number generator' => strpos($module, 'def generate_case_number(') !== false,
 'transition evaluator' => strpos($module, 'def evaluate_case_transition(') !== false,
 'relationship evaluator' => strpos($module, 'def evaluate_case_relationship(') !== false,
 'privacy evaluator' => strpos($module, 'def evaluate_privacy_boundary(') !== false,
 'report verifier' => strpos($module, 'def verify_case_report(') !== false,
 'capabilities endpoint' => strpos($main, '/v1/help-desk/capabilities') !== false,
 'validation endpoint' => strpos($main, '/v1/help-desk/cases/validate') !== false,
 'transition endpoint' => strpos($main, '/v1/help-desk/transitions/evaluate') !== false,
 'privacy endpoint' => strpos($main, '/v1/help-desk/privacy/evaluate') !== false,
 'backend tests' => strpos($tests, 'test_case_number_generation_is_deterministic') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.3.0 backend contract passed (' . count($checks) . " checks).\n";
