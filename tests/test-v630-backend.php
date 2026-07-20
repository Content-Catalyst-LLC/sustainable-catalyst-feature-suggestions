<?php
$root = dirname(__DIR__);
$module = file_get_contents($root . '/backend/app/help_desk_customer_portal.py');
$main = file_get_contents($root . '/backend/app/main.py');
$tests = file_get_contents($root . '/backend/tests/test_help_desk_customer_portal.py');
$checks = array(
 'backend schema' => strpos($module, 'scfs-help-desk-customer-portal/1.0') !== false,
 'access evaluator' => strpos($module, 'def assess_access_link(') !== false,
 'session evaluator' => strpos($module, 'def assess_session(') !== false,
 'conversation evaluator' => strpos($module, 'def assess_conversation_visibility(') !== false,
 'transition evaluator' => strpos($module, 'def evaluate_requester_transition(') !== false,
 'satisfaction evaluator' => strpos($module, 'def assess_satisfaction(') !== false,
 'report verifier' => strpos($module, 'def verify_portal_report(') !== false,
 'capabilities endpoint' => strpos($main, '/v1/help-desk/portal/capabilities') !== false,
 'access endpoint' => strpos($main, '/v1/help-desk/portal/access-links/evaluate') !== false,
 'session endpoint' => strpos($main, '/v1/help-desk/portal/sessions/evaluate') !== false,
 'conversation endpoint' => strpos($main, '/v1/help-desk/portal/conversations/evaluate') !== false,
 'transition endpoint' => strpos($main, '/v1/help-desk/portal/transitions/evaluate') !== false,
 'satisfaction endpoint' => strpos($main, '/v1/help-desk/portal/satisfaction/evaluate') !== false,
 'backend tests' => strpos($tests, 'test_conversation_visibility_hides_internal_notes') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.3.0 Customer Portal backend contract passed (' . count($checks) . " checks).\n";
