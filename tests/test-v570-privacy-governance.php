<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-support-analytics-documentation-effectiveness.php');
$backend = file_get_contents($root . '/backend/app/support_analytics_documentation_effectiveness.py');
$checks = array(
    'administrator-only analytics' => strpos($class, "'administrator_only' => true") !== false,
    'personal identifiers excluded' => strpos($class, "'personal_identifiers_exposed' => false") !== false,
    'raw search text excluded' => strpos($class, "'raw_search_text_exposed' => false") !== false,
    'private case content excluded' => strpos($class, "'private_case_content_exposed' => false") !== false,
    'human review required' => strpos($class, "'human_review_required' => true") !== false,
    'no automatic publication' => strpos($class, "'automatic_publication' => false") !== false,
    'no automatic issue resolution' => strpos($class, "'automatic_issue_resolution' => false") !== false,
    'no automatic roadmap changes' => strpos($class, "'automatic_roadmap_changes' => false") !== false,
    'backend administrator boundary' => strpos($backend, 'administrator_only: bool = True') !== false,
    'backend raw search boundary' => strpos($backend, 'raw_search_text_exposed: bool = False') !== false,
    'backend private content boundary' => strpos($backend, 'private_case_content_exposed: bool = False') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.7.0 Support Analytics privacy and governance contract passed (' . count($checks) . " checks).\n";
