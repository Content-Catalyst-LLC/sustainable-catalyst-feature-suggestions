<?php
$root = dirname(__DIR__);
$main = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/sustainable-catalyst-feature-suggestions.php');
$kb = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-knowledge-base.php');
$product = file_get_contents($root . '/wordpress/sustainable-catalyst-feature-suggestions/includes/class-scfs-product-integration.php');
$manifest = json_decode(file_get_contents($root . '/feature_suggestions_manifest.json'), true);

$checks = array(
    'plugin version' => strpos($main, 'Version: 3.2.0') !== false,
    'knowledge base bootstrap' => strpos($main, 'SCFS_Knowledge_Base_Foundation::instance();') !== false,
    'support article post type' => strpos($kb, "const ARTICLE_POST_TYPE = 'sc_support_article';") !== false,
    'known issue post type' => strpos($kb, "const ISSUE_POST_TYPE = 'sc_known_issue';") !== false,
    'documentation collection taxonomy' => strpos($kb, "const COLLECTION_TAXONOMY = 'scfs_doc_collection';") !== false,
    'article type taxonomy' => strpos($kb, "const ARTICLE_TYPE_TAXONOMY = 'scfs_article_type';") !== false,
    'article templates' => strpos($kb, 'function article_templates') !== false,
    'knowledge base shortcode' => strpos($kb, "const SHORTCODE = 'scfs_support_knowledge_base';") !== false,
    'public archive template' => file_exists($root . '/wordpress/sustainable-catalyst-feature-suggestions/templates/archive-support-knowledge-base.php'),
    'known issues archive template' => file_exists($root . '/wordpress/sustainable-catalyst-feature-suggestions/templates/archive-known-issues.php'),
    'knowledge base stylesheet' => file_exists($root . '/wordpress/sustainable-catalyst-feature-suggestions/assets/knowledge-base.css'),
    'knowledge base REST schema' => strpos($kb, "'/knowledge-base/schema'") !== false,
    'article REST endpoint' => strpos($kb, "'/knowledge-base/articles'") !== false,
    'known issue REST endpoint' => strpos($kb, "'/knowledge-base/known-issues'") !== false,
    'related suggestions' => strpos($kb, '_scfs_related_suggestions') !== false,
    'shared release relationship' => strpos($kb, 'SCFS_Product_Integration::RELEASE_TAXONOMY') !== false,
    'private suggestion boundary' => strpos($kb, "'private_suggestion_text_exposed' => false") !== false,
    'taxonomy foundation retained' => strpos($product, "const PRODUCT_TAXONOMY = 'scfs_product';") !== false,
    'manifest version' => is_array($manifest) && isset($manifest['version']) && $manifest['version'] === '3.2.0',
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
