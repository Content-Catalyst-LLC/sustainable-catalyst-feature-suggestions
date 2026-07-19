<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-known-issue-release-intelligence.php');
$main = file_get_contents($root . '/backend/app/main.py');
$backend = file_get_contents($root . '/backend/app/issue_release_intelligence.py');
$checks = array(
    'WordPress schema route' => strpos($class, '/issue-release-intelligence/schema') !== false,
    'WordPress issue route' => strpos($class, '/issue-release-intelligence/issues') !== false,
    'WordPress release route' => strpos($class, '/issue-release-intelligence/releases') !== false,
    'WordPress record route' => strpos($class, '/issue-release-intelligence/record/(?P<id>\\d+)') !== false,
    'protected sync route' => strpos($class, '/issue-release-intelligence/sync') !== false && strpos($class, "current_user_can('manage_options')") !== false,
    'FastAPI capabilities' => strpos($main, '/v1/issue-release-intelligence/capabilities') !== false,
    'FastAPI evaluation' => strpos($main, '/v1/issue-release-intelligence/evaluate') !== false,
    'backend schema' => strpos($backend, 'scfs-known-issue-release-intelligence/1.0') !== false,
    'WordPress source of truth' => strpos($backend, 'wordpress_source_of_truth: bool = True') !== false,
    'no automatic incident declaration' => strpos($backend, 'automatic_incident_declaration: bool = False') !== false,
    'no automatic release status changes' => strpos($backend, 'automatic_release_status_changes: bool = False') !== false,
    'no automatic publication' => strpos($backend, 'automatic_publication: bool = False') !== false,
    'human review required' => strpos($backend, 'human_review_required: bool = True') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.4.0 API and governance contract passed (' . count($checks) . " checks).\n";
