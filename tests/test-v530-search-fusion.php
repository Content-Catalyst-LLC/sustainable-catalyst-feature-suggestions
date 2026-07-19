<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-unified-support-search.php');
$discovery = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-support-discovery.php');
$guided = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-guided-resolution.php');
$checks = array(
    'guided resolution search reused' => strpos($class, 'SCFS_Guided_Resolution::instance()->search') !== false,
    'support discovery score reused' => strpos($class, 'SCFS_Support_Discovery::instance()') !== false && strpos($class, 'score_article') !== false,
    'guided score preserved' => strpos($class, "'guided_resolution_score'") !== false,
    'discovery score exposed' => strpos($class, "'discovery_score'") !== false,
    'discovery reasons exposed' => strpos($class, "'discovery_reasons'") !== false,
    'known issue group' => strpos($class, "'known_issues'") !== false,
    'support article group' => strpos($class, "'support_articles'") !== false,
    'release group' => strpos($class, "'releases'") !== false,
    'public suggestion group' => strpos($class, "'public_suggestions'") !== false,
    'discovery compatibility retained' => strpos($discovery, '/knowledge-base/discovery/search') !== false,
    'guided compatibility retained' => strpos($guided, '/guided-resolution/search') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.4.0 search fusion contract passed (' . count($checks) . " checks).\n";
