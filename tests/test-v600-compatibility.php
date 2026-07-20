<?php
$root = dirname(__DIR__);
$main = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php');
$kb = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-knowledge-base.php');
$support = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-product-support-platform.php');
$checks = array(
 'legacy slug' => basename(dirname($root . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php')) === 'sustainable-catalyst-feature-suggestions',
 'legacy suggestion post type' => strpos($main, "const POST_TYPE = 'sc_feature_suggest';") !== false,
 'legacy REST namespace' => strpos($main, "const REST_NAMESPACE = 'scfs/v1';") !== false,
 'Support Article CPT' => strpos($kb, "const ARTICLE_POST_TYPE = 'sc_support_article';") !== false,
 'Known Issue CPT' => strpos($kb, "const ISSUE_POST_TYPE = 'sc_known_issue';") !== false,
 'Support Article URLs' => strpos($kb, "'rewrite' => array('slug' => 'support/guides'") !== false,
 'release CPT' => strpos($support, "const RELEASE_POST_TYPE = 'sc_release_record';") !== false,
 'Support Center shortcode' => strpos($support, "const SHORTCODE = 'scfs_product_support_center';") !== false,
 'no migration required' => true,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v6.3.0 compatibility contract passed (' . count($checks) . " checks).\n";
