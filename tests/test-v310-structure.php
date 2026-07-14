<?php
$root = dirname(__DIR__);
$main = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php');
$integration = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-product-integration.php');
$manifest = json_decode(file_get_contents($root . '/feature_suggestions_manifest.json'), true);

$checks = array(
    'plugin version' => strpos($main, "Version: 3.1.0") !== false,
    'integration bootstrap' => strpos($main, 'SCFS_Product_Integration::instance();') !== false,
    'product taxonomy' => strpos($integration, "const PRODUCT_TAXONOMY = 'scfs_product';") !== false,
    'version taxonomy' => strpos($integration, "const VERSION_TAXONOMY = 'scfs_product_version';") !== false,
    'component taxonomy' => strpos($integration, "const COMPONENT_TAXONOMY = 'scfs_component';") !== false,
    'issue taxonomy' => strpos($integration, "const ISSUE_TAXONOMY = 'scfs_issue_type';") !== false,
    'release taxonomy' => strpos($integration, "const RELEASE_TAXONOMY = 'scfs_release';") !== false,
    'migration implementation' => strpos($integration, 'function migrate_suggestion') !== false,
    'taxonomy REST schema' => strpos($integration, "'/taxonomy/schema'") !== false,
    'handoff schema' => strpos($integration, 'sc-contact-engagement-handoff/') !== false,
    'protected handoff endpoint' => strpos($integration, "contact-engagement-handoff'") !== false,
    'product-aware intelligence' => strpos($main, "'products'=>array()") !== false,
    'manifest version' => is_array($manifest) && isset($manifest['version']) && $manifest['version'] === '3.1.0',
);

$failed = array();
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . " - {$label}\n";
    if (!$passed) {
        $failed[] = $label;
    }
}
if ($failed) {
    fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n");
    exit(1);
}
echo count($checks) . " checks passed.\n";
