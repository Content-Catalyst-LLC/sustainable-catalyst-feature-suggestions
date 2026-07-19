<?php
$root = dirname(__DIR__);
$support = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-product-support-platform.php');
$class = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-unified-support-search.php');
$checks = array(
    'support platform current version' => strpos($support, "const VERSION = '5.3.0';") !== false,
    'unified assets preloaded' => strpos($support, "wp_enqueue_style('scfs-unified-support-search')") !== false && strpos($support, "wp_enqueue_script('scfs-unified-support-search')") !== false,
    'resolve view unified renderer' => strpos($support, "Search and build a resolution path") !== false,
    'overview unified renderer' => substr_count($support, 'SCFS_Unified_Support_Search::instance()->render_shortcode') >= 2,
    'legacy renderer fallback' => strpos($support, 'SCFS_Guided_Resolution::instance()->render_shortcode') !== false,
    'support product context' => strpos($support, "with_request_value('scfs_support_product'") !== false,
    'unified capability exposed' => strpos($support, "'unified_support_search'") !== false,
    'resolution journey capability exposed' => strpos($support, "'resolution_journey'") !== false,
    'primary shortcode registered' => strpos($class, "const SHORTCODE = 'scfs_unified_support_search';") !== false,
    'legacy shortcode registered' => strpos($class, "const LEGACY_SHORTCODE = 'scfs_unified_guided_resolution';") !== false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.3.0 Support Center integration contract passed (' . count($checks) . " checks).\n";
