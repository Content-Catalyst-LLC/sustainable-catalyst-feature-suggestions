<?php
$root = dirname(__DIR__);
$module = file_get_contents($root . '/backend/app/help_desk_agent_workspace.py');
$main = file_get_contents($root . '/backend/app/main.py');
$checks = array(
 'backend schema' => strpos($module, 'scfs-help-desk-agent-workspace/1.0') !== false,
 'queue evaluator' => strpos($module, 'def evaluate_queue(') !== false,
 'assignment planner' => strpos($module, 'def plan_assignment(') !== false,
 'workload assessment' => strpos($module, 'def assess_workload(') !== false,
 'saved view assessment' => strpos($module, 'def assess_saved_view(') !== false,
 'report verifier' => strpos($module, 'def verify_workspace_report(') !== false,
 'capability endpoint' => strpos($main, "/v1/help-desk/workspace/capabilities") !== false,
 'queue endpoint' => strpos($main, "/v1/help-desk/workspace/queues/evaluate") !== false,
 'assignment endpoint' => strpos($main, "/v1/help-desk/workspace/assignments/plan") !== false,
 'workload endpoint' => strpos($main, "/v1/help-desk/workspace/workload/evaluate") !== false,
 'view endpoint' => strpos($main, "/v1/help-desk/workspace/views/evaluate") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.2.0 Agent Workspace backend contract passed (' . count($checks) . " checks).\n";
