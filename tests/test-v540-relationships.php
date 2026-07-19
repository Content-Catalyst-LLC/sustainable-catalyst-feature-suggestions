<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-known-issue-release-intelligence.php');
$checks = array(
    'target release relationship' => strpos($class, "ISSUE_TARGET_RELEASES = '_scfs_issue_target_release_ids'") !== false,
    'fixed release relationship' => strpos($class, "ISSUE_FIXED_RELEASES = '_scfs_issue_fixed_release_ids'") !== false,
    'related article relationship' => strpos($class, "ISSUE_RELATED_ARTICLES = '_scfs_issue_related_article_ids'") !== false,
    'open issue grouping' => strpos($class, "RELEASE_OPEN_ISSUES = '_scfs_release_open_issue_ids'") !== false,
    'resolved issue grouping' => strpos($class, "RELEASE_RESOLVED_ISSUES = '_scfs_release_resolved_issue_ids'") !== false,
    'reverse synchronization' => strpos($class, 'sync_issue_to_releases') !== false,
    'release grouping synchronization' => strpos($class, 'sync_release_issue_groups') !== false,
    'existing release relationship reused' => strpos($class, "'_scfs_release_related_issues'") !== false,
    'affected version taxonomy used' => strpos($class, 'SCFS_Product_Integration::VERSION_TAXONOMY') !== false,
    'component taxonomy used' => strpos($class, 'SCFS_Product_Integration::COMPONENT_TAXONOMY') !== false,
    'relationship health checks' => strpos($class, 'issue_health') !== false && strpos($class, 'release_health') !== false,
    'bulk synchronization' => strpos($class, 'sync_all') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.4.0 relationship integration contract passed (' . count($checks) . " checks).\n";
