<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-support-discovery.php');
$browser = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-integrated-knowledge-base.php');
$checks = array(
    'weighted scoring' => strpos($class, 'score_article') !== false && strpos($class, '$score += 140') !== false,
    'synonym expansion' => strpos($class, 'function synonyms') !== false && strpos($class, "'api' => array('endpoint'") !== false,
    'version context' => strpos($class, "'_scfs_last_verified_version'") !== false,
    'relevance ranking' => strpos($class, 'rank_articles') !== false,
    'sort modes' => strpos($browser, "'recent' => __('Recently updated'") !== false && strpos($browser, "'title' => __('Title A–Z'") !== false,
    'breadcrumbs' => strpos($browser, 'scfs-kb-discovery-breadcrumbs') !== false,
    'active filters' => strpos($browser, 'scfs-kb-active-filters') !== false,
    'no result recovery' => strpos($browser, 'render_no_results_recovery') !== false,
    'deep link sort' => strpos($browser, 'scfs_kb_sort') !== false,
    'aria live results' => strpos($browser, 'aria-live="polite"') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.2.9 search quality contract passed (' . count($checks) . " checks).\n";
