<?php
$root = dirname(__DIR__);
$plugin = $root . '/wordpress/sustainable-catalyst-feature-suggestions';
$main = file_get_contents($plugin . '/sustainable-catalyst-feature-suggestions.php');
$kb = file_get_contents($plugin . '/includes/class-scfs-knowledge-base.php');
$support = file_get_contents($plugin . '/includes/class-scfs-product-support-platform.php');
$manifest = json_decode(file_get_contents($root . '/release-manifest-v5.2.8.json'), true);
$checks = array(
    'legacy PHP class preserved' => strpos($main, 'final class Sustainable_Catalyst_Feature_Suggestions') !== false,
    'suggestion CPT preserved' => strpos($main, "const POST_TYPE = 'sc_feature_suggest';") !== false,
    'REST namespace preserved' => strpos($main, "const REST_NAMESPACE = 'scfs/v1';") !== false,
    'Support Article CPT preserved' => strpos($kb, "const ARTICLE_POST_TYPE = 'sc_support_article';") !== false,
    'Known Issue CPT preserved' => strpos($kb, "const ISSUE_POST_TYPE = 'sc_known_issue';") !== false,
    'Knowledge Base shortcode preserved' => strpos($kb, "const SHORTCODE = 'scfs_support_knowledge_base';") !== false,
    'Support Center shortcode preserved' => strpos($support, "const SHORTCODE = 'scfs_product_support_center';") !== false,
    'Support Article URLs preserved' => strpos($kb, "'rewrite' => array('slug' => 'support/guides'") !== false,
    'canonical Support page preserved' => strpos($support, "const SUPPORT_PAGE_SLUG = 'support';") !== false,
    'manifest no migration' => ($manifest['compatibility']['database_migration_required'] ?? null) === false,
    'manifest URLs preserved' => ($manifest['compatibility']['existing_article_urls_preserved'] ?? null) === true,
    'automatic publication disabled' => ($manifest['publication_integrity']['automatic_publication'] ?? null) === false,
);
$failed = array_keys(array_filter($checks, static function ($ok) { return !$ok; }));
foreach ($checks as $label => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$label}\n";
if ($failed) { fwrite(STDERR, 'Failed checks: ' . implode(', ', $failed) . "\n"); exit(1); }
echo 'v5.2.8 backward compatibility contract passed (' . count($checks) . " checks).\n";
