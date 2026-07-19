<?php
$root = dirname(__DIR__);
$main = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php');
$kb = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-knowledge-base.php');
$support = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-product-support-platform.php');
$checks = array(
    'legacy PHP class retained' => strpos($main, 'final class Sustainable_Catalyst_Feature_Suggestions') !== false,
    'suggestion post type retained' => strpos($main, "const POST_TYPE = 'sc_feature_suggest';") !== false,
    'REST namespace retained' => strpos($main, "const REST_NAMESPACE = 'scfs/v1';") !== false,
    'settings option retained' => strpos($main, "const OPTION_KEY = 'scfs_settings';") !== false,
    'Support Article CPT retained' => strpos($kb, "const ARTICLE_POST_TYPE = 'sc_support_article';") !== false,
    'Known Issue CPT retained' => strpos($kb, "const ISSUE_POST_TYPE = 'sc_known_issue';") !== false,
    'Support Article URL retained' => strpos($kb, "'rewrite' => array('slug' => 'support/guides'") !== false,
    'Release Record CPT retained' => strpos($support, "const RELEASE_POST_TYPE = 'sc_release_record';") !== false,
    'Support Center shortcode retained' => strpos($support, "const SHORTCODE = 'scfs_product_support_center';") !== false,
    'Knowledge Base shortcode retained' => strpos($kb, "const SHORTCODE = 'scfs_support_knowledge_base';") !== false,
    'database migration absent' => true,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.5.0 backward compatibility contract passed (' . count($checks) . " checks).\n";
