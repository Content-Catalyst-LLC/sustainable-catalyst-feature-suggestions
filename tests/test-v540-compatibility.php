<?php
$root = dirname(__DIR__);
$main = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php');
$knowledge = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-knowledge-base.php');
$support = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-product-support-platform.php');
$checks = array(
    'legacy PHP class preserved' => strpos($main, 'final class Sustainable_Catalyst_Feature_Suggestions') !== false,
    'legacy suggestion CPT preserved' => strpos($main, "const POST_TYPE = 'sc_feature_suggest';") !== false,
    'REST namespace preserved' => strpos($main, "const REST_NAMESPACE = 'scfs/v1';") !== false,
    'support article CPT preserved' => strpos($knowledge, "const ARTICLE_POST_TYPE = 'sc_support_article';") !== false,
    'known issue CPT preserved' => strpos($knowledge, "const ISSUE_POST_TYPE = 'sc_known_issue';") !== false,
    'release CPT preserved' => strpos($support, "const RELEASE_POST_TYPE = 'sc_release_record';") !== false,
    'support article URL base preserved' => strpos($knowledge, "'slug' => 'support/guides'") !== false,
    'Knowledge Base shortcode preserved' => strpos($knowledge, "const SHORTCODE = 'scfs_support_knowledge_base';") !== false,
    'Support Center shortcode preserved' => strpos($support, "const SHORTCODE = 'scfs_product_support_center';") !== false,
    'existing release metadata retained' => strpos($support, "'_scfs_release_related_issues'") !== false && strpos($support, "'_scfs_release_related_articles'") !== false,
    'database migration not introduced' => strpos($main, 'dbDelta(') === false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.4.0 backward compatibility contract passed (' . count($checks) . " checks).\n";
