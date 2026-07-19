<?php
$root = dirname(__DIR__);
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-known-issue-release-intelligence.php');
$support = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-product-support-platform.php');
$unified = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-unified-support-search.php');
$css = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/issue-release-intelligence.css');
$checks = array(
    'public shortcode' => strpos($class, "const SHORTCODE = 'scfs_issue_release_intelligence';") !== false,
    'legacy shortcode alias' => strpos($class, "const LEGACY_SHORTCODE = 'scfs_known_issue_release_intelligence';") !== false,
    'publication issue decoration' => strpos($class, 'decorate_issue_content') !== false,
    'publication release decoration' => strpos($class, 'decorate_release_content') !== false,
    'Support Center issue integration' => strpos($support, 'SCFS_Known_Issue_Release_Intelligence::instance()->issue_record') !== false,
    'Support Center release integration' => strpos($support, 'SCFS_Known_Issue_Release_Intelligence::instance()->release_record') !== false,
    'unified search enrichment' => strpos($unified, 'enrich_operational_intelligence') !== false,
    'affected version result context' => strpos($unified, "'affected_versions'") !== false,
    'open issue result context' => strpos($unified, "'open_issue_count'") !== false,
    'responsive interface CSS' => strpos($css, '@media (max-width: 720px)') !== false,
    'print interface CSS' => strpos($css, '@media print') !== false,
    'publication metadata CSS' => strpos($css, '.scfs-iri-publication__meta') !== false,
    'admin relationship CSS' => strpos($css, '.scfs-iri-editor-grid') !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.4.0 operational interface contract passed (' . count($checks) . " checks).\n";
